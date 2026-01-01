<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;

/**
 * Tool zum Löschen von Projekt-Slots
 */
class DeleteProjectSlotTool implements ToolContract
{
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
            if (empty($arguments['slot_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Slot-ID ist erforderlich. Nutze "planner.project_slots.GET" um Slots zu finden.');
            }

            // Slot finden
            $slot = PlannerProjectSlot::find($arguments['slot_id']);
            if (!$slot) {
                return ToolResult::error('SLOT_NOT_FOUND', 'Der angegebene Slot wurde nicht gefunden. Nutze "planner.project_slots.GET" um alle verfügbaren Slots zu sehen.');
            }

            // Projekt laden
            $project = $slot->project;
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Das zugehörige Projekt wurde nicht gefunden.');
            }

            // Prüfe Zugriff
            $hasAccess = $project->projectUsers()
                ->where('user_id', $context->user->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
            
            if (!$hasAccess && $project->user_id !== $context->user->id) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, diesen Slot zu löschen. Nur Owner und Admins können Slots löschen.');
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

