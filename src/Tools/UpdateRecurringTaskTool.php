<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerRecurringTask;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Bearbeiten einer wiederkehrenden Aufgaben-Vorlage (PlannerRecurringTask).
 * Wichtigster Anwendungsfall: is_active=false setzen, um eine Vorlage zu pausieren,
 * ohne sie zu löschen (z.B. wenn eine Routine durch eine andere Automatisierung
 * redundant geworden ist).
 */
class UpdateRecurringTaskTool implements ToolContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'planner.recurring_tasks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /recurring-tasks/{id} - Aktualisiert eine wiederkehrende Aufgaben-Vorlage. Pflicht: recurring_task_id. is_active=false pausiert die Vorlage (keine neuen Tasks mehr, ohne sie zu löschen). Weitere optionale Parameter: title, description, recurrence_type, recurrence_interval, next_due_date, recurrence_end_date, weekday_mask, project_id, project_slot_id, user_in_charge_id, lead_time_days, skip_weekends, max_occurrences, auto_delete_old_tasks, auto_mark_as_done.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'recurring_task_id' => [
                    'type' => 'integer',
                    'description' => 'ID der zu bearbeitenden Vorlage (ERFORDERLICH). Nutze "planner.recurring_tasks.GET" um Vorlagen zu finden.'
                ],
                'title' => ['type' => 'string', 'description' => 'Optional: Neuer Titel.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Neue Beschreibung. null/"" entfernt sie.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: false pausiert die Vorlage (keine neuen Tasks mehr), true reaktiviert sie.'],
                'recurrence_type' => ['type' => 'string', 'enum' => ['daily', 'weekly', 'monthly', 'yearly'], 'description' => 'Optional: Wiederholungsmuster.'],
                'recurrence_interval' => ['type' => 'integer', 'description' => 'Optional: alle N Einheiten.'],
                'next_due_date' => ['type' => 'string', 'description' => 'Optional: nächste Fälligkeit (YYYY-MM-DD oder ISO 8601).'],
                'recurrence_end_date' => ['type' => 'string', 'description' => 'Optional: Enddatum der Wiederholung. null entfernt es.'],
                'weekday_mask' => ['type' => 'integer', 'description' => 'Optional: Bitmaske erlaubter Wochentage (Mo=1..So=64). null entfernt sie.'],
                'project_id' => ['type' => 'integer', 'description' => 'Optional: Neue Projekt-ID. null macht die Vorlage persönlich.'],
                'project_slot_id' => ['type' => 'integer', 'description' => 'Optional: Neue Slot-ID. null entfernt den Slot.'],
                'user_in_charge_id' => ['type' => 'integer', 'description' => 'Optional: Neuer verantwortlicher User.'],
                'lead_time_days' => ['type' => 'integer', 'description' => 'Optional: Vorlauf in Tagen vor Fälligkeit.'],
                'skip_weekends' => ['type' => 'boolean', 'description' => 'Optional: Termine auf Sa/So auf Montag verschieben.'],
                'max_occurrences' => ['type' => 'integer', 'description' => 'Optional: Limit erzeugter Instanzen. null entfernt das Limit.'],
                'auto_delete_old_tasks' => ['type' => 'boolean', 'description' => 'Optional: vorhandene Instanzen vor Neuanlage löschen.'],
                'auto_mark_as_done' => ['type' => 'boolean', 'description' => 'Optional: neue Instanzen sofort als erledigt anlegen.'],
            ],
            'required' => ['recurring_task_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'recurring_task_id',
                PlannerRecurringTask::class,
                'RECURRING_TASK_NOT_FOUND',
                'Die angegebene wiederkehrende Aufgaben-Vorlage wurde nicht gefunden.'
            );

            if ($validation['error']) {
                return $validation['error'];
            }

            /** @var PlannerRecurringTask $recurring */
            $recurring = $validation['model'];

            try {
                Gate::forUser($context->user)->authorize('update', $recurring);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, diese Vorlage zu bearbeiten (Policy).');
            }

            $updateData = [];

            if (array_key_exists('title', $arguments)) {
                $val = $arguments['title'];
                if ($val === null || $val === 'null') {
                    return ToolResult::error('VALIDATION_ERROR', 'title darf nicht null sein.');
                }
                $valStr = trim((string) $val);
                if ($valStr !== '') {
                    $updateData['title'] = $valStr;
                }
            }

            if (array_key_exists('description', $arguments)) {
                $val = $arguments['description'];
                $updateData['description'] = ($val === null || $val === 'null' || $val === '') ? null : trim((string) $val);
            }

            if (array_key_exists('is_active', $arguments)) {
                $updateData['is_active'] = (bool) $arguments['is_active'];
            }

            if (array_key_exists('recurrence_type', $arguments)) {
                $type = strtolower((string) $arguments['recurrence_type']);
                if (!in_array($type, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'recurrence_type muss daily|weekly|monthly|yearly sein.');
                }
                $updateData['recurrence_type'] = $type;
            }

            if (array_key_exists('recurrence_interval', $arguments)) {
                $interval = (int) $arguments['recurrence_interval'];
                if ($interval < 1) {
                    return ToolResult::error('VALIDATION_ERROR', 'recurrence_interval muss mindestens 1 sein.');
                }
                $updateData['recurrence_interval'] = $interval;
            }

            if (array_key_exists('next_due_date', $arguments)) {
                $raw = $arguments['next_due_date'];
                if ($raw === null || $raw === '' || $raw === 'null') {
                    return ToolResult::error('VALIDATION_ERROR', 'next_due_date darf nicht entfernt werden (Vorlage braeuchte sonst keine Faelligkeit mehr). Nutze is_active=false zum Pausieren.');
                }
                try {
                    $updateData['next_due_date'] = \Carbon\Carbon::parse($raw);
                } catch (\Exception $e) {
                    return ToolResult::error('INVALID_DATE', 'Ungültiges Datumsformat für next_due_date. Verwende YYYY-MM-DD oder ISO 8601.');
                }
            }

            if (array_key_exists('recurrence_end_date', $arguments)) {
                $raw = $arguments['recurrence_end_date'];
                if ($raw === null || $raw === '' || $raw === 'null') {
                    $updateData['recurrence_end_date'] = null;
                } else {
                    try {
                        $updateData['recurrence_end_date'] = \Carbon\Carbon::parse($raw);
                    } catch (\Exception $e) {
                        return ToolResult::error('INVALID_DATE', 'Ungültiges Datumsformat für recurrence_end_date. Verwende YYYY-MM-DD oder ISO 8601.');
                    }
                }
            }

            if (array_key_exists('weekday_mask', $arguments)) {
                $raw = $arguments['weekday_mask'];
                $updateData['weekday_mask'] = ($raw === null || $raw === '' || $raw === 'null') ? null : (int) $raw;
            }

            if (array_key_exists('project_id', $arguments)) {
                $rawProjectId = $arguments['project_id'];
                if ($rawProjectId === null || $rawProjectId === 0 || $rawProjectId === '0' || $rawProjectId === '') {
                    $updateData['project_id'] = null;
                    $updateData['project_slot_id'] = null;
                } else {
                    $newProject = PlannerProject::withStale()->find($rawProjectId);
                    if (!$newProject) {
                        return ToolResult::error('PROJECT_NOT_FOUND', 'Das angegebene Projekt wurde nicht gefunden. Nutze "planner.projects.GET".');
                    }
                    try {
                        Gate::forUser($context->user)->authorize('update', $newProject);
                    } catch (AuthorizationException $e) {
                        return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf das angegebene Projekt (Policy).');
                    }
                    $updateData['project_id'] = $newProject->id;
                    $updateData['team_id'] = $newProject->team_id;
                }
            }

            if (array_key_exists('project_slot_id', $arguments)) {
                $rawSlotId = $arguments['project_slot_id'];
                if ($rawSlotId === null || $rawSlotId === 0 || $rawSlotId === '0' || $rawSlotId === '') {
                    $updateData['project_slot_id'] = null;
                } else {
                    $newSlot = PlannerProjectSlot::find($rawSlotId);
                    if (!$newSlot) {
                        return ToolResult::error('SLOT_NOT_FOUND', 'Der angegebene Slot wurde nicht gefunden. Nutze "planner.project_slots.GET".');
                    }
                    $currentProjectId = $updateData['project_id'] ?? $recurring->project_id;
                    if ($newSlot->project_id !== $currentProjectId) {
                        return ToolResult::error('SLOT_MISMATCH', 'Der angegebene Slot gehört nicht zum angegebenen Projekt.');
                    }
                    $updateData['project_slot_id'] = $newSlot->id;
                }
            }

            if (array_key_exists('user_in_charge_id', $arguments)) {
                $val = $arguments['user_in_charge_id'];
                if ($val === null || $val === 'null') {
                    $updateData['user_in_charge_id'] = null;
                } elseif (is_numeric($val)) {
                    $updateData['user_in_charge_id'] = (int) $val;
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'user_in_charge_id muss eine Zahl sein (oder null zum Entfernen).');
                }
            }

            if (array_key_exists('lead_time_days', $arguments)) {
                $val = $arguments['lead_time_days'];
                $updateData['lead_time_days'] = ($val === null || $val === '' || $val === 'null') ? 0 : max(0, (int) $val);
            }

            if (array_key_exists('skip_weekends', $arguments)) {
                $updateData['skip_weekends'] = (bool) $arguments['skip_weekends'];
            }

            if (array_key_exists('max_occurrences', $arguments)) {
                $val = $arguments['max_occurrences'];
                $updateData['max_occurrences'] = ($val === null || $val === '' || $val === 'null') ? null : max(0, (int) $val);
            }

            if (array_key_exists('auto_delete_old_tasks', $arguments)) {
                $updateData['auto_delete_old_tasks'] = (bool) $arguments['auto_delete_old_tasks'];
            }

            if (array_key_exists('auto_mark_as_done', $arguments)) {
                $updateData['auto_mark_as_done'] = (bool) $arguments['auto_mark_as_done'];
            }

            if (!empty($updateData)) {
                $recurring->update($updateData);
            }

            $recurring->refresh();
            $recurring->load(['project', 'projectSlot', 'userInCharge']);

            return ToolResult::success([
                'id' => $recurring->id,
                'uuid' => $recurring->uuid,
                'title' => $recurring->title,
                'description' => $recurring->description,
                'is_active' => (bool) $recurring->is_active,
                'recurrence_type' => $recurring->recurrence_type,
                'recurrence_interval' => $recurring->recurrence_interval,
                'next_due_date' => optional($recurring->next_due_date)->toIso8601String(),
                'recurrence_end_date' => optional($recurring->recurrence_end_date)->toIso8601String(),
                'project_id' => $recurring->project_id,
                'project_name' => $recurring->project?->name,
                'project_slot_id' => $recurring->project_slot_id,
                'user_in_charge_id' => $recurring->user_in_charge_id,
                'user_in_charge_name' => $recurring->userInCharge?->name ?? 'Unbekannt',
                'updated_at' => $recurring->updated_at->toIso8601String(),
                'message' => "Wiederkehrende Aufgabe '{$recurring->title}' erfolgreich aktualisiert" . ($recurring->is_active ? '.' : ' (pausiert — erzeugt keine neuen Tasks mehr).'),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der wiederkehrenden Aufgabe: ' . $e->getMessage());
        }
    }
}
