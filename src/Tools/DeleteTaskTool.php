<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerTask;

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
        return 'Löscht eine Aufgabe. RUF DIESES TOOL AUF, wenn der Nutzer eine Aufgabe löschen möchte. Die Task-ID ist erforderlich. Nutze "planner.tasks.GET" um Aufgaben zu finden. WICHTIG: Aufgaben werden soft-deleted (gelöscht), können aber wiederhergestellt werden. Frage den Nutzer nach Bestätigung, wenn die Aufgabe wichtig erscheint.';
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
                    'description' => 'Optional: Bestätigung, dass die Aufgabe wirklich gelöscht werden soll. Frage den Nutzer explizit nach Bestätigung, wenn die Aufgabe wichtig erscheint oder viele Details hat.'
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

            // Prüfe, ob bereits gelöscht
            if ($task->trashed()) {
                return ToolResult::error('ALREADY_DELETED', 'Die Aufgabe wurde bereits gelöscht.');
            }

            // Prüfe Zugriff (optional - kann überschrieben werden)
            $accessCheck = $this->checkAccess($task, $context, function($model, $ctx) {
                // Custom Access-Check: User muss Owner der Aufgabe sein oder Zugriff auf das Projekt haben
                if ($model->user_in_charge_id === $ctx->user->id || $model->user_id === $ctx->user->id) {
                    return true;
                }
                
                if ($model->project_id) {
                    $project = $model->project;
                    $hasAccess = $project->projectUsers()
                        ->where('user_id', $ctx->user->id)
                        ->whereIn('role', ['owner', 'admin'])
                        ->exists();
                    
                    return $hasAccess || $project->user_id === $ctx->user->id;
                }
                
                return false;
            });
            
            if ($accessCheck) {
                return $accessCheck;
            }

            // Bestätigung prüfen (wenn Aufgabe wichtig erscheint)
            $isImportant = $task->is_frog || $task->is_forced_frog || !empty($task->description) || !empty($task->dod);
            if ($isImportant && !($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', "Die Aufgabe '{$task->title}' scheint wichtig zu sein (hat Details, DoD oder ist als Frog markiert). Bitte bestätige die Löschung mit 'confirm: true'.");
            }

            $taskTitle = $task->title;
            $taskId = $task->id;
            $projectName = $task->project?->name;
            $slotName = $task->projectSlot?->name;

            // Task soft-deleten
            $task->delete();

            return ToolResult::success([
                'task_id' => $taskId,
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'slot_name' => $slotName,
                'message' => "Aufgabe '{$taskTitle}' wurde erfolgreich gelöscht. Sie kann wiederhergestellt werden."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Aufgabe: ' . $e->getMessage());
        }
    }
}

