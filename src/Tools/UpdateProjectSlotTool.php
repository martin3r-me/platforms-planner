<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;

/**
 * Tool zum Bearbeiten von Projekt-Slots
 */
class UpdateProjectSlotTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.project_slots.PUT';
    }

    public function getDescription(): string
    {
        return 'Bearbeitet einen bestehenden Slot in einem Projekt. RUF DIESES TOOL AUF, wenn der Nutzer einen Slot ändern möchte (Name, Reihenfolge). Die Slot-ID ist erforderlich. Nutze "planner.project_slots.GET" um Slots zu finden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu bearbeitenden Slots (ERFORDERLICH). Nutze "planner.project_slots.GET" um Slots zu finden.'
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name des Slots. Frage nach, wenn der Nutzer den Namen ändern möchte.'
                ],
                'order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Reihenfolge des Slots. Frage nach, wenn der Nutzer die Reihenfolge ändern möchte.'
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
                return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, diesen Slot zu bearbeiten. Nur Owner und Admins können Slots bearbeiten.');
            }

            // Update-Daten sammeln
            $updateData = [];

            if (isset($arguments['name'])) {
                $updateData['name'] = $arguments['name'];
            }

            if (isset($arguments['order'])) {
                $updateData['order'] = $arguments['order'];
            }

            // Slot aktualisieren
            if (!empty($updateData)) {
                $slot->update($updateData);
            }

            // Aktualisierten Slot laden
            $slot->refresh();
            $slot->load('project');

            return ToolResult::success([
                'id' => $slot->id,
                'uuid' => $slot->uuid,
                'name' => $slot->name,
                'project_id' => $slot->project_id,
                'project_name' => $project->name,
                'order' => $slot->order,
                'tasks_count' => $slot->tasks()->count(),
                'updated_at' => $slot->updated_at->toIso8601String(),
                'message' => "Slot '{$slot->name}' erfolgreich aktualisiert."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Slots: ' . $e->getMessage());
        }
    }
}

