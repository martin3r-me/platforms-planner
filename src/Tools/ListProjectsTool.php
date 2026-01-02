<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

/**
 * Tool zum Auflisten von Projekten im Planner-Modul
 * 
 * Ermöglicht es der AI, Projekte zu finden, bevor Tasks oder Slots erstellt werden.
 */
class ListProjectsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    public function getName(): string
    {
        return 'planner.projects.GET';
    }

    public function getDescription(): string
    {
        return 'Listet alle Projekte auf, auf die der aktuelle User Zugriff hat (entweder als Mitglied oder durch Aufgaben). RUF DIESES TOOL DIREKT AUF, wenn der Nutzer nach Projekten fragt (z.B. "alle Projekte", "meine Projekte", "Projekte des aktuellen Teams"). Das Tool verwendet automatisch das aktuelle Team aus dem Kontext - du musst team_id NICHT angeben, es sei denn, der Nutzer fragt explizit nach Projekten eines anderen Teams. Wenn der Nutzer nur einen Projektnamen angibt, nutze dieses Tool, um die Projekt-ID zu finden. WICHTIG: Wenn du prüfen musst, ob ein Projekt existiert, rufe dieses Tool auf - nicht planner.tasks.GET!';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Team-ID. Wenn nicht angegeben, wird automatisch das aktuelle Team aus dem Kontext verwendet. Du musst diesen Parameter NICHT angeben, wenn der Nutzer nach Projekten des aktuellen Teams fragt.'
                    ],
                    // Legacy-Parameter (für Backwards-Kompatibilität)
                    'project_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Projekttyp (Legacy - nutze stattdessen filters mit field="project_type" und op="eq"). Mögliche Werte: "internal", "customer", "event", "cooking".',
                        'enum' => ['internal', 'customer', 'event', 'cooking']
                    ],
                    'name_search' => [
                        'type' => 'string',
                        'description' => 'Optional: Suche nach Projektnamen (Legacy - nutze stattdessen search Parameter).'
                    ]
                ]
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Team bestimmen
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Nutze das Tool "core.teams.GET" um alle verfügbaren Teams zu sehen.');
            }

            // Query aufbauen
            $query = PlannerProject::query()
                ->where('team_id', $teamId)
                ->with(['user', 'team', 'projectUsers.user', 'projectSlots']);

            // Standard-Operationen anwenden
            $this->applyStandardFilters($query, $arguments, [
                'project_type', 'name', 'description', 'done', 'created_at', 'updated_at'
            ]);
            
            // Legacy: project_type (für Backwards-Kompatibilität)
            if (!empty($arguments['project_type'])) {
                $query->where('project_type', $arguments['project_type']);
            }
            
            // Standard-Suche anwenden
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            
            // Legacy: name_search (für Backwards-Kompatibilität)
            if (!empty($arguments['name_search'])) {
                $query->where('name', 'like', '%' . $arguments['name_search'] . '%');
            }
            
            // Standard-Sortierung anwenden
            $this->applyStandardSort($query, $arguments, [
                'name', 'created_at', 'updated_at', 'project_type', 'done'
            ], 'name', 'asc');
            
            // Standard-Pagination anwenden
            $this->applyStandardPagination($query, $arguments);

            // Projekte holen (nur solche, auf die User Zugriff hat)
            $projects = $query->get();

            // Projekte formatieren mit Slots- und Backlog-Statistiken
            $projectsList = $projects->map(function($project) use ($context) {
                $projectUsers = $project->projectUsers->map(function($pu) {
                    return [
                        'user_id' => $pu->user_id,
                        'user_name' => $pu->user->name ?? 'Unbekannt',
                        'role' => $pu->role,
                    ];
                })->toArray();

                // Slots-Statistiken
                $slots = $project->projectSlots;
                $slotsCount = $slots->count();
                $totalTasksInSlots = $slots->sum(function($slot) {
                    return $slot->tasks()->count();
                });
                
                // Backlog-Aufgaben (Aufgaben mit Projekt, aber ohne Slot)
                $backlogTasksCount = PlannerTask::where('project_id', $project->id)
                    ->whereNull('project_slot_id')
                    ->count();
                $backlogTasksOpen = PlannerTask::where('project_id', $project->id)
                    ->whereNull('project_slot_id')
                    ->where('is_done', false)
                    ->count();
                
                // Gesamt-Aufgaben im Projekt
                $totalTasks = PlannerTask::where('project_id', $project->id)->count();

                return [
                    'id' => $project->id,
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    'description' => $project->description,
                    'project_type' => $project->project_type?->value,
                    'team_id' => $project->team_id,
                    'owner_user_id' => $project->user_id,
                    'owner_name' => $project->user->name ?? 'Unbekannt',
                    'members' => $projectUsers,
                    'done' => $project->done,
                    'created_at' => $project->created_at->toIso8601String(),
                    // Struktur-Informationen
                    'structure' => [
                        'slots_count' => $slotsCount,
                        'tasks_in_slots' => $totalTasksInSlots,
                        'backlog_tasks' => $backlogTasksCount,
                        'backlog_tasks_open' => $backlogTasksOpen,
                        'total_tasks' => $totalTasks,
                        'note' => 'Backlog-Aufgaben sind Aufgaben mit Projekt-Bezug, aber ohne Slot-Zuordnung. Nutze "planner.project_slots.GET" mit project_id, um alle Slots und Backlog-Aufgaben zu sehen.'
                    ],
                ];
            })->values()->toArray();

            return ToolResult::success([
                'projects' => $projectsList,
                'count' => count($projectsList),
                'team_id' => $teamId,
                'message' => count($projectsList) > 0 
                    ? count($projectsList) . ' Projekt(e) gefunden.'
                    : 'Keine Projekte gefunden.'
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Projekte: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'project', 'list'],
            'read_only' => true, // Explizit: Nur Lese-Operation
            'requires_auth' => true,
            'requires_team' => false, // Team kann optional sein
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

