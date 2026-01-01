<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;

/**
 * Tool zum Löschen von Projekt-Slots
 */
class DeleteProjectSlotTool implements ToolContract
{
    use HasStandardizedWriteOperations;
    public function getName(): string
    {
        return 'planner.project_slots.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht einen Slot in einem Projekt. RUF DIESES TOOL AUF, wenn der Nutzer einen Slot löschen möchte. Die Slot-ID ist erforderlich. Nutze "planner.project_slots.GET" um Slots zu finden. WICHTIG: Beim Löschen eines Slots werden alle zugehörigen Aufgaben in das Projekt-Backlog verschoben (project_slot_id wird auf null gesetzt). Frage den Nutzer nach Bestätigung, wenn der Slot viele Aufgaben hat.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu löschenden Slots (ERFORDERLICH). Nutze "planner.project_slots.GET" um Slots zu finden.'
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bestätigung, dass der Slot wirklich gelöscht werden soll. Wenn der Slot viele Aufgaben hat, frage den Nutzer explizit nach Bestätigung.'
                ]
            ],
            'required' => ['slot_id']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Nutze standardisierte ID-Validierung (loose coupled - optional)
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'slot_id',
                PlannerProjectSlot::class,
                'SLOT_NOT_FOUND',
                'Der angegebene Slot wurde nicht gefunden.'
            );
            
            if ($validation['error']) {
                return $validation['error'];
            }
            
            $slot = $validation['model'];
            
            // Projekt laden
            $project = $slot->project;
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Das zugehörige Projekt wurde nicht gefunden.');
            }

            // Prüfe Zugriff über Projekt (optional - kann überschrieben werden)
            $accessCheck = $this->checkAccess($project, $context, function($model, $ctx) {
                // Custom Access-Check: Owner oder Admin des Projekts
                $hasAccess = $model->projectUsers()
                    ->where('user_id', $ctx->user->id)
                    ->whereIn('role', ['owner', 'admin'])
                    ->exists();
                
                return $hasAccess || $model->user_id === $ctx->user->id;
            });
            
            if ($accessCheck) {
                return $accessCheck;
            }

            // Prüfe Anzahl der Aufgaben
            $tasksCount = $slot->tasks()->count();

            // Bestätigung prüfen (wenn viele Aufgaben vorhanden)
            if ($tasksCount > 5 && !($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', "Der Slot hat {$tasksCount} Aufgabe(n). Beim Löschen werden diese in das Projekt-Backlog verschoben (project_slot_id wird auf null gesetzt). Bitte bestätige die Löschung mit 'confirm: true'.");
            }

            $slotName = $slot->name;
            $slotId = $slot->id;
            $projectId = $project->id;

            // Aufgaben in Backlog verschieben (project_slot_id auf null setzen)
            $slot->tasks()->update([
                'project_slot_id' => null,
                'project_slot_order' => null,
            ]);

            // Slot löschen
            $slot->delete();

            return ToolResult::success([
                'slot_id' => $slotId,
                'slot_name' => $slotName,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'moved_tasks_count' => $tasksCount,
                'message' => "Slot '{$slotName}' wurde gelöscht. {$tasksCount} Aufgabe(n) wurden in das Projekt-Backlog verschoben."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Slots: ' . $e->getMessage());
        }
    }
}

