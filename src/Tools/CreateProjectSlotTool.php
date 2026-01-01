<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolDependencyContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;

/**
 * Tool zum Erstellen von Projekt-Slots im Planner-Modul
 * 
 * Slots strukturieren Aufgaben innerhalb eines Projekts (z.B. "To Do", "Hold", "In Progress").
 * Standard-Slots wie "To Do" und "Hold" können erstellt werden, aber auch beliebige weitere Slots.
 * 
 * LOOSE COUPLED: Definiert seine Dependencies selbst via ToolDependencyContract.
 */
class CreateProjectSlotTool implements ToolContract, ToolDependencyContract
{
    public function getName(): string
    {
        return 'planner.project_slots.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt einen neuen Slot in einem Projekt. Slots strukturieren Aufgaben innerhalb eines Projekts (z.B. "To Do", "Hold", "In Progress"). RUF DIESES TOOL AUF, wenn der Nutzer einen Slot erstellen möchte oder wenn ein Projekt noch keine Slots hat und welche benötigt werden. Der Slot-Name ist erforderlich. Wenn kein Projekt angegeben ist, frage dialog-mäßig nach dem Projekt oder nutze "planner.projects.GET" um Projekte zu finden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Projekts, in dem der Slot erstellt werden soll (ERFORDERLICH). Wenn nicht angegeben, nutze "planner.projects.GET" um Projekte zu finden und frage dann nach der Projekt-ID.'
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Slots (ERFORDERLICH). Beispiele: "To Do", "Hold", "In Progress", "Done". Frage den Nutzer explizit nach dem Namen, wenn er nicht angegeben wurde.'
                ],
                'order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Reihenfolge des Slots. Wenn nicht angegeben, wird der Slot ans Ende gesetzt.'
                ]
            ],
            'required' => ['project_id', 'name']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Validierung
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Slot-Name ist erforderlich');
            }

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

            // Team aus Projekt holen
            $teamId = $project->team_id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Das Projekt hat kein Team zugeordnet.');
            }

            // Order berechnen (neuer Slot kommt ans Ende)
            $order = $arguments['order'] ?? null;
            if ($order === null) {
                $maxOrder = PlannerProjectSlot::where('project_id', $project->id)->max('order') ?? 0;
                $order = $maxOrder + 1;
            }

            // Slot erstellen
            $slot = PlannerProjectSlot::create([
                'project_id' => $project->id,
                'name' => $arguments['name'],
                'order' => $order,
                'user_id' => $context->user->id,
                'team_id' => $teamId,
            ]);

            return ToolResult::success([
                'id' => $slot->id,
                'uuid' => $slot->uuid,
                'name' => $slot->name,
                'project_id' => $slot->project_id,
                'project_name' => $project->name,
                'order' => $slot->order,
                'created_at' => $slot->created_at->toIso8601String(),
                'message' => "Slot '{$slot->name}' erfolgreich im Projekt '{$project->name}' erstellt."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Slots: ' . $e->getMessage());
        }
    }

    /**
     * Definiert die Dependencies dieses Tools (loose coupled)
     * 
     * Wenn project_id fehlt, wird automatisch planner.projects.GET aufgerufen.
     */
    public function getDependencies(): array
    {
        return [
            'required_fields' => ['project_id'],
            'dependencies' => [
                [
                    'tool_name' => 'planner.projects.GET',
                    'condition' => function(array $arguments, ToolContext $context): bool {
                        return empty($arguments['project_id']) || ($arguments['project_id'] ?? null) === 0;
                    },
                    'args' => function(array $arguments, ToolContext $context): array {
                        return [];
                    },
                    'merge_result' => function(string $mainToolName, ToolResult $depResult, array $arguments): ?array {
                        // Wenn project_id noch fehlt, gib Dependency-Ergebnis zurück (AI soll Projekte zeigen)
                        if (empty($arguments['project_id']) && $depResult->success) {
                            return null; // Dependency-Ergebnis direkt zurückgeben
                        }
                        return $arguments;
                    }
                ]
            ]
        ];
    }
}

