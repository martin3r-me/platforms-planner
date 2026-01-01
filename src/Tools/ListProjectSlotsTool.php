<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;

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
        return 'Listet alle Slots eines Projekts auf, inklusive Backlog-Aufgaben (Aufgaben ohne Slot). RUF DIESES TOOL AUF, wenn der Nutzer nach Slots fragt, nach Backlog-Aufgaben fragt, oder wenn du wissen musst, welche Slots in einem Projekt verfügbar sind, bevor du eine Aufgabe erstellst. Das Tool zeigt: 1) Alle Slots mit ihren Aufgaben, 2) Backlog-Aufgaben (Aufgaben mit Projekt-Bezug, aber ohne Slot). Wenn kein Projekt angegeben ist, nutze "planner.projects.GET" um Projekte zu finden.';
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

            // Slots formatieren mit Aufgaben-Statistiken
            $slotsList = $slots->map(function($slot) {
                $tasks = $slot->tasks()->get();
                $openTasks = $tasks->where('is_done', false)->count();
                $doneTasks = $tasks->where('is_done', true)->count();
                
                return [
                    'id' => $slot->id,
                    'uuid' => $slot->uuid,
                    'name' => $slot->name,
                    'project_id' => $slot->project_id,
                    'order' => $slot->order,
                    'tasks_count' => $tasks->count(),
                    'tasks_open' => $openTasks,
                    'tasks_done' => $doneTasks,
                    'created_at' => $slot->created_at->toIso8601String(),
                ];
            })->values()->toArray();

            // Backlog-Aufgaben holen (Aufgaben mit Projekt, aber ohne Slot)
            $backlogQuery = PlannerTask::where('project_id', $project->id)
                ->whereNull('project_slot_id');
            
            // Standard-Operationen für Backlog anwenden
            $this->applyStandardFilters($backlogQuery, $arguments, [
                'title', 'description', 'is_done', 'due_date', 'created_at', 'updated_at'
            ]);
            
            $this->applyStandardSearch($backlogQuery, $arguments, ['title', 'description']);
            
            // Backlog-Aufgaben zählen (ohne Pagination für Statistik)
            $backlogTasksCount = $backlogQuery->count();
            $backlogTasksOpen = (clone $backlogQuery)->where('is_done', false)->count();
            $backlogTasksDone = (clone $backlogQuery)->where('is_done', true)->count();
            
            // Backlog-Aufgaben-Liste (mit Pagination)
            $this->applyStandardSort($backlogQuery, $arguments, [
                'title', 'due_date', 'created_at', 'updated_at'
            ], 'created_at', 'desc');
            
            $this->applyStandardPagination($backlogQuery, $arguments);
            $backlogTasks = $backlogQuery->get();
            
            // Backlog-Aufgaben formatieren
            $backlogList = $backlogTasks->map(function($task) {
                return [
                    'id' => $task->id,
                    'uuid' => $task->uuid,
                    'title' => $task->title,
                    'description' => $task->description,
                    'is_done' => $task->is_done,
                    'due_date' => $task->due_date?->toIso8601String(),
                    'user_in_charge_id' => $task->user_in_charge_id,
                    'user_in_charge_name' => $task->userInCharge?->name ?? 'Unbekannt',
                    'created_at' => $task->created_at->toIso8601String(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'slots' => $slotsList,
                'slots_count' => count($slotsList),
                'backlog' => [
                    'tasks' => $backlogList,
                    'tasks_count' => $backlogTasksCount,
                    'tasks_open' => $backlogTasksOpen,
                    'tasks_done' => $backlogTasksDone,
                    'note' => 'Backlog-Aufgaben sind Aufgaben mit Projekt-Bezug, aber ohne Slot-Zuordnung. Diese können später einem Slot zugeordnet werden.'
                ],
                'project_id' => $project->id,
                'project_name' => $project->name,
                'summary' => [
                    'total_slots' => count($slotsList),
                    'total_tasks_in_slots' => array_sum(array_column($slotsList, 'tasks_count')),
                    'backlog_tasks' => $backlogTasksCount,
                    'total_project_tasks' => array_sum(array_column($slotsList, 'tasks_count')) + $backlogTasksCount,
                ],
                'message' => sprintf(
                    '%d Slot(s) gefunden mit %d Aufgaben. %d Backlog-Aufgabe(n) (ohne Slot).',
                    count($slotsList),
                    array_sum(array_column($slotsList, 'tasks_count')),
                    $backlogTasksCount
                )
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Slots: ' . $e->getMessage());
        }
    }
}

