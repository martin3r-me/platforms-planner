<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;

/**
 * Tool zum Abrufen eines einzelnen Projekt-Slots mit vollständiger Struktur
 * 
 * Gibt die komplette Struktur eines Slots zurück:
 * - Slot-Details (id, uuid, name, order, project_id)
 * - Alle Aufgaben im Slot mit IDs und Details
 * - Statistiken (tasks_count, tasks_open, tasks_done)
 */
class GetProjectSlotTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project_slot.GET';
    }

    public function getDescription(): string
    {
        return 'GET /project_slots/{id} - Ruft einen einzelnen Slot mit vollständiger Struktur ab. REST-Parameter: id (required, integer) - Slot-ID. Gibt zurück: Slot-Details, alle Aufgaben im Slot mit IDs und Details, Statistiken. Nutze "planner.project_slots.GET" um verfügbare Slot-IDs zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'REST-Parameter (required): ID des Slots. Beispiel: id=123. Nutze "planner.project_slots.GET" um verfügbare Slot-IDs zu sehen.'
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
                return ToolResult::error('VALIDATION_ERROR', 'Slot-ID ist erforderlich. Nutze "planner.project_slots.GET" um Slots zu finden.');
            }

            // Slot holen
            $slot = PlannerProjectSlot::with(['project', 'project.projectUsers'])
                ->find($arguments['id']);

            if (!$slot) {
                return ToolResult::error('SLOT_NOT_FOUND', 'Der angegebene Slot wurde nicht gefunden. Nutze "planner.project_slots.GET" um alle verfügbaren Slots zu sehen.');
            }

            // Prüfe, ob User Zugriff auf Projekt hat
            $project = $slot->project;
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Das zugehörige Projekt wurde nicht gefunden.');
            }

            $hasAccess = $project->projectUsers()
                ->where('user_id', $context->user->id)
                ->exists();
            
            if (!$hasAccess && $project->user_id !== $context->user->id) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Projekt. Nutze "planner.projects.GET" um alle verfügbaren Projekte zu sehen.');
            }

            // Aufgaben im Slot holen
            $tasks = PlannerTask::where('project_slot_id', $slot->id)
                ->with(['userInCharge'])
                ->orderBy('order')
                ->orderBy('created_at')
                ->get();

            // Aufgaben formatieren
            $tasksList = $tasks->map(function($task) {
                return [
                    'id' => $task->id,
                    'uuid' => $task->uuid,
                    'title' => $task->title,
                    'description' => $task->description,
                    'dod' => $task->dod,
                    'is_done' => $task->is_done,
                    'due_date' => $task->due_date?->toIso8601String(),
                    'user_in_charge_id' => $task->user_in_charge_id,
                    'user_in_charge_name' => $task->userInCharge?->name ?? 'Unbekannt',
                    'planned_minutes' => $task->planned_minutes,
                    'order' => $task->order,
                    'created_at' => $task->created_at->toIso8601String(),
                    'updated_at' => $task->updated_at->toIso8601String(),
                ];
            })->values()->toArray();

            // Statistiken
            $tasksCount = $tasks->count();
            $tasksOpen = $tasks->where('is_done', false)->count();
            $tasksDone = $tasks->where('is_done', true)->count();

            return ToolResult::success([
                'id' => $slot->id,
                'uuid' => $slot->uuid,
                'name' => $slot->name,
                'order' => $slot->order,
                'project_id' => $slot->project_id,
                'project_name' => $project->name,
                'created_at' => $slot->created_at->toIso8601String(),
                'updated_at' => $slot->updated_at->toIso8601String(),
                // Komplette Struktur-Informationen
                'structure' => [
                    'tasks_count' => $tasksCount,
                    'tasks_open' => $tasksOpen,
                    'tasks_done' => $tasksDone,
                    'tasks' => $tasksList, // Alle Aufgaben mit Details
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Slots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'project_slot', 'get'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

