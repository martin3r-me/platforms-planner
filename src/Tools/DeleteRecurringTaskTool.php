<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerRecurringTask;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Löschen einer wiederkehrenden Aufgaben-Vorlage (PlannerRecurringTask).
 * Für ein reines Pausieren (reversibel, ohne Lösch-Historie) besser
 * "planner.recurring_tasks.PUT" mit is_active=false nutzen.
 */
class DeleteRecurringTaskTool implements ToolContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'planner.recurring_tasks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /recurring-tasks/{id} - Löscht eine wiederkehrende Aufgaben-Vorlage (Soft-Delete, wiederherstellbar). Für reines Pausieren ohne Löschung stattdessen "planner.recurring_tasks.PUT" mit is_active=false nutzen. REST-Parameter: recurring_task_id (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'recurring_task_id' => [
                    'type' => 'integer',
                    'description' => 'ID der zu löschenden Vorlage (ERFORDERLICH). Nutze "planner.recurring_tasks.GET" um Vorlagen zu finden.'
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bestätigung. Bei force=true IMMER erforderlich.'
                ],
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Hard-Delete/Purge statt Soft-Delete. Unwiderruflich, kein Restore möglich. Nur für Owner/Admin. Erfordert confirm=true.'
                ],
            ],
            'required' => ['recurring_task_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $recurringId = $arguments['recurring_task_id'] ?? null;
            if (empty($recurringId)) {
                return ToolResult::error('VALIDATION_ERROR', 'recurring_task_id ist erforderlich. Nutze "planner.recurring_tasks.GET" um Vorlagen zu finden.');
            }

            $recurring = PlannerRecurringTask::withTrashed()->find($recurringId);
            if (!$recurring) {
                return ToolResult::error('RECURRING_TASK_NOT_FOUND', 'Die angegebene wiederkehrende Aufgaben-Vorlage wurde nicht gefunden. Nutze "planner.recurring_tasks.GET".');
            }

            $force = (bool) ($arguments['force'] ?? false);

            if ($recurring->trashed() && !$force) {
                return ToolResult::error('ALREADY_DELETED', 'Die Vorlage wurde bereits gelöscht. Nutze force=true, um sie unwiderruflich zu purgen.');
            }

            try {
                Gate::forUser($context->user)->authorize($force ? 'forceDelete' : 'delete', $recurring);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', $force
                    ? 'Du darfst diese Vorlage nicht unwiderruflich löschen (nur Owner/Admin).'
                    : 'Du hast keine Berechtigung, diese Vorlage zu löschen (Policy).');
            }

            $title = $recurring->title;

            if ($force && !($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', "Hard-Delete/Purge der Vorlage '{$title}' ist UNWIDERRUFLICH und nicht wiederherstellbar. Bitte bestätige explizit mit 'confirm: true'.");
            }

            $recurringId = $recurring->id;

            if ($force) {
                if (method_exists($recurring, 'logActivity')) {
                    $recurring->logActivity("Wiederkehrende Aufgabe '{$title}' hart gelöscht (Purge, force=true) durch {$context->user->name}.");
                }
                $recurring->forceDelete();
            } else {
                $recurring->delete();
            }

            return ToolResult::success([
                'recurring_task_id' => $recurringId,
                'title' => $title,
                'force' => $force,
                'message' => $force
                    ? "Vorlage '{$title}' wurde UNWIDERRUFLICH aus der Datenbank gelöscht (Purge)."
                    : "Vorlage '{$title}' wurde gelöscht. Sie kann wiederhergestellt werden (oder nutze PUT mit is_active=false zum reinen Pausieren).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der wiederkehrenden Aufgabe: ' . $e->getMessage());
        }
    }
}
