<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;

/**
 * Tool zum Auflisten von Projekt-Slots
 * 
 * Ermöglicht es der AI, alle Slots eines Projekts zu sehen.
 */
class ListProjectSlotsTool implements ToolContract
{
    use HasStandardGetOperations;
    public function getName(): string
    {
        return 'planner.project_slots.GET';
    }

    public function getDescription(): string
    {
        return 'Listet alle Slots eines Projekts auf. RUF DIESES TOOL AUF, wenn der Nutzer nach Slots fragt oder wenn du wissen musst, welche Slots in einem Projekt verfügbar sind, bevor du eine Aufgabe erstellst. Wenn kein Projekt angegeben ist, nutze "planner.projects.GET" um Projekte zu finden.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'project_id' => [
                        'type' => 'integer',
                        'description' => 'ID des Projekts, dessen Slots aufgelistet werden sollen (ERFORDERLICH). Wenn nicht angegeben, nutze "planner.projects.GET" um Projekte zu finden.'
                    ]
                ],
                'required' => ['project_id']
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['project_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Projekt-ID ist erforderlich. Nutze "planner.projects.GET" um Projekte zu finden.');
            }

            // Projekt prüfen
            $project = PlannerProject::find($arguments['project_id']);
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Das angegebene Projekt wurde nicht gefunden. Nutze "planner.projects.GET" um alle verfügbaren Projekte zu sehen.');
            }

            // Prüfe, ob User Zugriff auf Projekt hat
            $hasAccess = $project->projectUsers()
                ->where('user_id', $context->user->id)
                ->exists();
            
            if (!$hasAccess && $project->user_id !== $context->user->id) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Projekt. Nutze "planner.projects.GET" um alle verfügbaren Projekte zu sehen.');
            }

            // Query aufbauen
            $query = PlannerProjectSlot::where('project_id', $project->id);
            
            // Standard-Operationen anwenden
            $this->applyStandardFilters($query, $arguments, [
                'name', 'order', 'created_at', 'updated_at'
            ]);
            
            $this->applyStandardSearch($query, $arguments, ['name']);
            
            $this->applyStandardSort($query, $arguments, [
                'name', 'order', 'created_at', 'updated_at'
            ], 'order', 'asc');
            
            $this->applyStandardPagination($query, $arguments);
            
            // Slots holen
            $slots = $query->get();

            // Slots formatieren
            $slotsList = $slots->map(function($slot) {
                return [
                    'id' => $slot->id,
                    'uuid' => $slot->uuid,
                    'name' => $slot->name,
                    'project_id' => $slot->project_id,
                    'order' => $slot->order,
                    'tasks_count' => $slot->tasks()->count(),
                    'created_at' => $slot->created_at->toIso8601String(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'slots' => $slotsList,
                'count' => count($slotsList),
                'project_id' => $project->id,
                'project_name' => $project->name,
                'message' => count($slotsList) > 0 
                    ? count($slotsList) . ' Slot(s) im Projekt gefunden.'
                    : 'Keine Slots im Projekt gefunden. Nutze "planner.project_slots.create" um einen Slot zu erstellen.'
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Slots: ' . $e->getMessage());
        }
    }
}

