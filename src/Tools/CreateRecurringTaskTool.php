<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\StoreRecurringTask;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Legt eine WIEDERKEHRENDE Aufgabe (Vorlage) an. Anders als planner.tasks.POST erzeugt dies keine
 * einzelne Task, sondern ein Muster (PlannerRecurringTask), aus dem der Cron regelmäßig Tasks
 * erstellt. Kern-Werkzeug für autonome Worker, die sich selbst Routinen einrichten.
 */
class CreateRecurringTaskTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.recurring_tasks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /recurring-tasks - Legt eine WIEDERKEHRENDE Aufgabe (Vorlage) an, aus der automatisch in regelmaessigen Abstaenden Tasks erstellt werden. Pflicht: title, recurrence_type (daily|weekly|monthly|yearly), next_due_date (erste Faelligkeit). Optional: recurrence_interval (Default 1, z.B. 2 = alle 2 Wochen), weekday_mask (Wochentag-Bitmaske), project_id, user_in_charge_id (Default: aktueller User), recurrence_end_date, story_points, planned_minutes, lead_time_days, skip_weekends, max_occurrences. Fuer eine EINZELNE Aufgabe stattdessen planner.tasks.POST nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Titel der wiederkehrenden Aufgabe (ERFORDERLICH).'],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung.'],
                'recurrence_type' => ['type' => 'string', 'enum' => ['daily', 'weekly', 'monthly', 'yearly'], 'description' => 'ERFORDERLICH: Wiederholungsmuster.'],
                'recurrence_interval' => ['type' => 'integer', 'description' => 'Optional: alle N Einheiten (Default 1; z.B. 2 = alle 2 Wochen).'],
                'next_due_date' => ['type' => 'string', 'description' => 'ERFORDERLICH: erste Faelligkeit (YYYY-MM-DD oder ISO 8601).'],
                'recurrence_end_date' => ['type' => 'string', 'description' => 'Optional: Enddatum der Wiederholung.'],
                'weekday_mask' => ['type' => 'integer', 'description' => 'Optional (daily/weekly): Bitmaske erlaubter Wochentage (Mo=1,Di=2,Mi=4,Do=8,Fr=16,Sa=32,So=64; Mo-Fr=31, alle=127).'],
                'project_id' => ['type' => 'integer', 'description' => 'Optional: Projekt-ID. Ohne = persoenliche Aufgabe. Nutze planner.projects.GET.'],
                'project_slot_id' => ['type' => 'integer', 'description' => 'Optional: Slot-ID (project_id muss dann gesetzt sein).'],
                'user_in_charge_id' => ['type' => 'integer', 'description' => 'Optional: verantwortlicher User (Default: aktueller User).'],
                'story_points' => ['type' => 'string', 'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'], 'description' => 'Optional.'],
                'planned_minutes' => ['type' => 'integer', 'description' => 'Optional: geplante Minuten je Instanz.'],
                'lead_time_days' => ['type' => 'integer', 'description' => 'Optional: wie viele Tage VOR Faelligkeit die Task schon angelegt wird (Default 0).'],
                'skip_weekends' => ['type' => 'boolean', 'description' => 'Optional: Termine auf Sa/So auf den naechsten Montag verschieben.'],
                'max_occurrences' => ['type' => 'integer', 'description' => 'Optional: Limit auf die Anzahl erzeugter Instanzen.'],
            ],
            'required' => ['title', 'recurrence_type', 'next_due_date'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['title'])) {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }
            $type = strtolower((string) ($arguments['recurrence_type'] ?? ''));
            if (! in_array($type, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
                return ToolResult::error('VALIDATION_ERROR', 'recurrence_type muss daily|weekly|monthly|yearly sein.');
            }
            if (empty($arguments['next_due_date'])) {
                return ToolResult::error('VALIDATION_ERROR', 'next_due_date ist erforderlich (erste Faelligkeit).');
            }

            // Projekt/Slot auflösen + Policy (wie CreateTaskTool): nur wer im Projekt Tasks anlegen darf.
            $project = null;
            if (! empty($arguments['project_id'])) {
                $project = PlannerProject::withStale()->find($arguments['project_id']);
                if (! $project) {
                    return ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden. Nutze planner.projects.GET.');
                }
            }
            $slot = null;
            if (! empty($arguments['project_slot_id'])) {
                $slot = PlannerProjectSlot::find($arguments['project_slot_id']);
                if (! $slot) {
                    return ToolResult::error('SLOT_NOT_FOUND', 'Slot nicht gefunden.');
                }
                if ($project && $slot->project_id !== $project->id) {
                    return ToolResult::error('SLOT_MISMATCH', 'Slot gehoert nicht zum angegebenen Projekt.');
                }
                if (! $project) {
                    $project = $slot->project;
                }
            }
            try {
                Gate::forUser($context->user)->authorize('create', [PlannerTask::class, $project]);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', $project
                    ? 'Du darfst in diesem Projekt keine Aufgaben erstellen (Policy).'
                    : 'Du darfst keine Aufgaben erstellen (Policy).');
            }

            // Story points validieren
            $sp = null;
            if (array_key_exists('story_points', $arguments) && $arguments['story_points'] !== null && $arguments['story_points'] !== '') {
                $enum = TaskStoryPoints::tryFrom(strtolower((string) $arguments['story_points']));
                if (! $enum) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungueltige story_points. Erlaubt: xs|s|m|l|xl|xxl.');
                }
                $sp = $enum->value;
            }

            $teamId = $project?->team_id ?? $context->team?->id;
            if (! $teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team gefunden. Persoenliche Aufgaben benoetigen ein Team im Kontext.');
            }

            $recurring = app(StoreRecurringTask::class)->store([
                'user_id'             => $context->user->id,
                'user_in_charge_id'   => $arguments['user_in_charge_id'] ?? $context->user->id,
                'team_id'             => $teamId,
                'title'               => $arguments['title'],
                'description'         => $arguments['description'] ?? null,
                'recurrence_type'     => $type,
                'recurrence_interval' => $arguments['recurrence_interval'] ?? 1,
                'next_due_date'       => $arguments['next_due_date'],
                'recurrence_end_date' => $arguments['recurrence_end_date'] ?? null,
                'weekday_mask'        => $arguments['weekday_mask'] ?? null,
                'project_id'          => $project?->id,
                'project_slot_id'     => $slot?->id,
                'story_points'        => $sp,
                'planned_minutes'     => $arguments['planned_minutes'] ?? null,
                'lead_time_days'      => $arguments['lead_time_days'] ?? null,
                'skip_weekends'       => $arguments['skip_weekends'] ?? null,
                'max_occurrences'     => $arguments['max_occurrences'] ?? null,
            ]);

            return ToolResult::success([
                'id' => $recurring->id,
                'uuid' => $recurring->uuid,
                'title' => $recurring->title,
                'recurrence_type' => $recurring->recurrence_type,
                'recurrence_interval' => $recurring->recurrence_interval,
                'next_due_date' => optional($recurring->next_due_date)->toIso8601String(),
                'project_id' => $recurring->project_id,
                'user_in_charge_id' => $recurring->user_in_charge_id,
                'message' => "Wiederkehrende Aufgabe '{$recurring->title}' angelegt ({$recurring->recurrence_type}, alle {$recurring->recurrence_interval}).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der wiederkehrenden Aufgabe: ' . $e->getMessage());
        }
    }
}
