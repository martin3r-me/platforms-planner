<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerTask;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Löschen von Aufgaben im Planner-Modul
 * 
 * Verwendet Soft-Delete, sodass Aufgaben wiederhergestellt werden können.
 */
class DeleteTaskTool implements ToolContract
{
    use HasStandardizedWriteOperations;
    public function getName(): string
    {
        return 'planner.tasks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /tasks/{id} - Löscht eine Aufgabe. REST-Parameter: id (required, integer) - Task-ID. Hinweis: Aufgaben werden soft-deleted und können wiederhergestellt werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => [
                    'type' => 'integer',
                    'description' => 'ID der zu löschenden Aufgabe (ERFORDERLICH). Nutze "planner.tasks.GET" um Aufgaben zu finden.'
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bestätigung, dass die Aufgabe wirklich gelöscht werden soll. Frage den Nutzer explizit nach Bestätigung, wenn die Aufgabe wichtig erscheint oder viele Details hat. Bei force=true IMMER erforderlich.'
                ],
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Hard-Delete/Purge statt Soft-Delete. Löscht die Aufgabe unwiderruflich aus der Datenbank (kein Restore möglich). Nur für Owner/Admin. Erfordert zwingend confirm=true.'
                ]
            ],
            'required' => ['task_id']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Nutze standardisierte ID-Validierung (loose coupled - optional)
            // Für Delete mit Soft-Delete müssen wir withTrashed verwenden
            $taskId = $arguments['task_id'] ?? null;
            if (empty($taskId)) {
                return ToolResult::error('VALIDATION_ERROR', 'Task-ID ist erforderlich. Nutze "planner.tasks.GET" um Aufgaben zu finden.');
            }
            
            // Task finden (auch gelöschte Tasks können gefunden werden)
            $task = PlannerTask::withTrashed()->find($taskId);
            if (!$task) {
                return ToolResult::error('TASK_NOT_FOUND', 'Die angegebene Aufgabe wurde nicht gefunden. Nutze "planner.tasks.GET" um alle verfügbaren Aufgaben zu sehen.');
            }

            $force = (bool) ($arguments['force'] ?? false);

            // Prüfe, ob bereits gelöscht (Soft-Delete-Fall). Bei force=true bewusst NICHT
            // blockieren — genau bereits soft-gelöschte Aufgaben sind der Hauptfall für Purge
            // (z.B. DSGVO-Löschpflicht auf zuvor soft-gelöschten Daten).
            if ($task->trashed() && !$force) {
                return ToolResult::error('ALREADY_DELETED', 'Die Aufgabe wurde bereits gelöscht. Nutze force=true, um sie unwiderruflich zu purgen.');
            }

            // Policy wie UI (Task-Livewire nutzt authorize('delete', $task) für Delete);
            // Hard-Delete/Purge nutzt eine eigene Ability (aktuell gleiche Schwelle: Owner/Admin).
            try {
                Gate::forUser($context->user)->authorize($force ? 'forceDelete' : 'delete', $task);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', $force
                    ? 'Du darfst diese Aufgabe nicht unwiderruflich löschen (nur Owner/Admin).'
                    : 'Du hast keine Berechtigung, diese Aufgabe zu löschen (Policy).');
            }

            $taskTitle = $task->title;

            if ($force) {
                // Hard-Delete ist unumkehrbar: Bestätigung immer erforderlich.
                if (!($arguments['confirm'] ?? false)) {
                    return ToolResult::error('CONFIRMATION_REQUIRED', "Hard-Delete/Purge von Aufgabe '{$taskTitle}' ist UNWIDERRUFLICH und nicht wiederherstellbar. Bitte bestätige explizit mit 'confirm: true'.");
                }
            } else {
                // Bestätigung prüfen (wenn Aufgabe wichtig erscheint)
                $isImportant = $task->is_frog || $task->is_forced_frog || !empty($task->description) || !empty($task->dod);
                if ($isImportant && !($arguments['confirm'] ?? false)) {
                    return ToolResult::error('CONFIRMATION_REQUIRED', "Die Aufgabe '{$taskTitle}' scheint wichtig zu sein (hat Details, DoD oder ist als Frog markiert). Bitte bestätige die Löschung mit 'confirm: true'.");
                }
            }

            $taskId = $task->id;
            $projectName = $task->project?->name;
            $slotName = $task->projectSlot?->name;

            if ($force) {
                if (method_exists($task, 'logActivity')) {
                    $task->logActivity("Aufgabe '{$taskTitle}' hart gelöscht (Purge, force=true) durch {$context->user->name}.");
                }
                $task->forceDelete();
            } else {
                // Task soft-deleten
                $task->delete();
            }

            return ToolResult::success([
                'task_id' => $taskId,
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'slot_name' => $slotName,
                'force' => $force,
                'message' => $force
                    ? "Aufgabe '{$taskTitle}' wurde UNWIDERRUFLICH aus der Datenbank gelöscht (Purge)."
                    : "Aufgabe '{$taskTitle}' wurde erfolgreich gelöscht. Sie kann wiederhergestellt werden."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Aufgabe: ' . $e->getMessage());
        }
    }
}

