<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;
use Illuminate\Support\Facades\Gate;

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
        return 'GET /projects?team_id={id}&filters=[...]&search=...&sort=[...] - Listet Projekte auf. REST-Parameter: team_id (optional, integer) - Filter nach Team-ID. WICHTIG: Wenn der User ein spezifisches Team nennt (z.B. "TAISTONE"), rufe zuerst "core.teams.GET" auf, um die Team-ID zu finden. Wenn das Team nicht gefunden wird, verwende NICHT automatisch das aktuelle Team - teile dem User stattdessen mit, dass das Team nicht gefunden wurde. Wenn team_id nicht angegeben ist und der User kein spezifisches Team nennt, wird automatisch das aktuelle Team aus dem Kontext verwendet. filters (optional, array) - Filter-Array mit field, op, value. search (optional, string) - Suchbegriff. sort (optional, array) - Sortierung mit field, dir. limit/offset (optional) - Pagination.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'REST-Parameter (optional): Filter nach Team-ID. WICHTIG: Wenn der User ein spezifisches Team nennt (z.B. "TAISTONE"), rufe zuerst "core.teams.GET" auf, um die Team-ID zu finden. Wenn das Team nicht gefunden wird, verwende NICHT automatisch das aktuelle Team - teile dem User stattdessen mit, dass das Team nicht gefunden wurde. Wenn nicht angegeben und der User nennt kein spezifisches Team, wird automatisch das aktuelle Team aus dem Kontext verwendet.'
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

            // Team-Filter bestimmen
            // WICHTIG: Behandle 0 als "nicht gesetzt" (OpenAI sendet manchmal 0 statt null)
            $teamIdArg = $arguments['team_id'] ?? null;
            if ($teamIdArg === 0 || $teamIdArg === '0') {
                $teamIdArg = null;
            }
            
            // Wenn team_id nicht angegeben, verwende aktuelles Team aus Kontext
            if ($teamIdArg === null) {
                $teamIdArg = $context->team?->id;
            }
            
            if (!$teamIdArg) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Nutze "core.teams.GET" um verfügbare Teams zu sehen, oder gib team_id explizit an.');
            }
            
            // Prüfe, ob User Zugriff auf dieses Team hat
            $userHasAccess = $context->user->teams()->where('teams.id', $teamIdArg)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamIdArg}. Nutze 'core.teams.GET' um verfügbare Teams zu sehen.");
            }
            
            // Query aufbauen - nur Projekte dieses Teams
            $query = PlannerProject::query()
                ->where('team_id', $teamIdArg)
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

            // Projekte holen (Team-scope) und dann per Policy filtern (wie UI authorize('view'))
            $projects = $query->get()->filter(function ($project) use ($context) {
                try {
                    return Gate::forUser($context->user)->allows('view', $project);
                } catch (\Throwable $e) {
                    return false;
                }
            })->values();

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
                        'note' => 'Backlog-Aufgaben sind Aufgaben mit Projekt-Bezug, aber ohne Slot-Zuordnung. Nutze "planner.project.GET" mit project_id, um die komplette Struktur eines Projekts zu sehen.'
                    ],
                ];
            })->values()->toArray();

            return ToolResult::success([
                'projects' => $projectsList,
                'count' => count($projectsList),
                'team_id' => $teamIdArg,
                'message' => count($projectsList) > 0 
                    ? count($projectsList) . ' Projekt(e) gefunden (Team-ID: ' . $teamIdArg . ').'
                    : 'Keine Projekte gefunden für Team-ID: ' . $teamIdArg . '.'
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

