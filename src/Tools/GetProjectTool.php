<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Abrufen eines einzelnen Projekts mit vollständiger Struktur
 * 
 * Gibt die komplette Struktur eines Projekts zurück:
 * - Alle Slots mit IDs und Aufgaben-Anzahl
 * - Backlog-Aufgaben-Anzahl
 * - Alle IDs
 */
class GetProjectTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project.GET';
    }

    public function getDescription(): string
    {
        return 'GET /projects/{id} - Ruft ein einzelnes Projekt mit vollständiger Struktur ab. REST-Parameter: id (required, integer) - Projekt-ID. Gibt zurück: Projekt-Details, alle Slots mit IDs und Aufgaben-Anzahl, Backlog-Aufgaben-Anzahl. Nutze "planner.projects.GET" um verfügbare Projekt-IDs zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'REST-Parameter (required): ID des Projekts. Beispiel: id=123. Nutze "planner.projects.GET" um verfügbare Projekt-IDs zu sehen.'
                ]
            ],
            'required' => ['id']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Projekt-ID ist erforderlich. Nutze "planner.projects.GET" um Projekte zu finden.');
            }

            // Projekt holen
            $project = PlannerProject::with(['user', 'team', 'projectUsers.user', 'projectSlots'])
                ->find($arguments['id']);

            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Das angegebene Projekt wurde nicht gefunden. Nutze "planner.projects.GET" um alle verfügbaren Projekte zu sehen.');
            }

            // Policy wie in der UI: Project::mount() nutzt authorize('view', $project)
            try {
                Gate::forUser($context->user)->authorize('view', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Projekt (Policy).');
            }

            // Projekt-User formatieren
            $projectUsers = $project->projectUsers->map(function($pu) {
                return [
                    'user_id' => $pu->user_id,
                    'user_name' => $pu->user->name ?? 'Unbekannt',
                    'role' => $pu->role,
                ];
            })->toArray();

            // Slots mit IDs und Aufgaben-Anzahl formatieren
            $slots = $project->projectSlots()->orderBy('order')->get();
            $slotsCount = $slots->count();
            $totalTasksInSlots = $slots->sum(function($slot) {
                return $slot->tasks()->count();
            });
            
            $slotsStructure = $slots->map(function($slot) {
                $tasksCount = $slot->tasks()->count();
                $tasksOpen = $slot->tasks()->where('is_done', false)->count();
                $tasksDone = $slot->tasks()->where('is_done', true)->count();
                
                return [
                    'id' => $slot->id,
                    'uuid' => $slot->uuid,
                    'name' => $slot->name,
                    'order' => $slot->order,
                    'tasks_count' => $tasksCount,
                    'tasks_open' => $tasksOpen,
                    'tasks_done' => $tasksDone,
                ];
            })->values()->toArray();
            
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

            return ToolResult::success([
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
                // Komplette Struktur-Informationen
                'structure' => [
                    'slots_count' => $slotsCount,
                    'slots' => $slotsStructure, // Alle Slots mit IDs und Aufgaben-Anzahl
                    'tasks_in_slots' => $totalTasksInSlots,
                    'backlog_tasks' => $backlogTasksCount,
                    'backlog_tasks_open' => $backlogTasksOpen,
                    'total_tasks' => $totalTasks,
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Projekts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'project', 'get'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

