<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;

/**
 * Tool zum Auflisten von Projekten im Planner-Modul
 * 
 * Ermöglicht es der AI, Projekte zu finden, bevor Tasks oder Slots erstellt werden.
 */
class ListProjectsTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.projects.list';
    }

    public function getDescription(): string
    {
        return 'Listet alle Projekte auf, auf die der aktuelle User Zugriff hat (entweder als Mitglied oder durch Aufgaben). RUF DIESES TOOL AUF, wenn der Nutzer nach Projekten fragt oder wenn du ein Projekt finden musst, bevor du eine Aufgabe oder einen Slot erstellst. Wenn der Nutzer nur einen Projektnamen angibt, nutze dieses Tool, um die Projekt-ID zu finden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Team-ID. Wenn nicht angegeben, wird das aktuelle Team aus dem Kontext verwendet.'
                ],
                'project_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Projekttyp. Mögliche Werte: "internal", "customer", "event", "cooking".',
                    'enum' => ['internal', 'customer', 'event', 'cooking']
                ],
                'name_search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suche nach Projektnamen (teilweise Übereinstimmung).'
                ]
            ],
            'required' => []
        ];
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
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Nutze das Tool "core.teams.list" um alle verfügbaren Teams zu sehen.');
            }

            // Query aufbauen
            $query = PlannerProject::query()
                ->where('team_id', $teamId)
                ->with(['user', 'team', 'projectUsers.user']);

            // Filter: Projekttyp
            if (!empty($arguments['project_type'])) {
                $query->where('project_type', $arguments['project_type']);
            }

            // Filter: Name-Suche
            if (!empty($arguments['name_search'])) {
                $query->where('name', 'like', '%' . $arguments['name_search'] . '%');
            }

            // Projekte holen (nur solche, auf die User Zugriff hat)
            $projects = $query->orderBy('name')->get();

            // Projekte formatieren
            $projectsList = $projects->map(function($project) use ($context) {
                $projectUsers = $project->projectUsers->map(function($pu) {
                    return [
                        'user_id' => $pu->user_id,
                        'user_name' => $pu->user->name ?? 'Unbekannt',
                        'role' => $pu->role,
                    ];
                })->toArray();

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
}

