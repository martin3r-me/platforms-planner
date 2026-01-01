<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;

/**
 * Tool zum Auflisten von Aufgaben im Planner-Modul
 * 
 * Ermöglicht es der AI, Aufgaben zu finden und anzuzeigen.
 */
class ListTasksTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.tasks.list';
    }

    public function getDescription(): string
    {
        return 'Listet Aufgaben auf. Aufgaben können nach Projekt, Slot, User oder Status gefiltert werden. RUF DIESES TOOL AUF, wenn der Nutzer nach Aufgaben fragt oder wenn du Aufgaben anzeigen musst. Wenn kein Filter angegeben ist, werden die Aufgaben des aktuellen Users angezeigt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Projekt-ID. Nutze "planner.projects.list" um Projekte zu finden.'
                ],
                'project_slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Slot-ID. Nutze "planner.project_slots.list" um Slots zu finden.'
                ],
                'user_in_charge_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach User-ID (zuständiger User). Wenn nicht angegeben, werden Aufgaben des aktuellen Users angezeigt.'
                ],
                'is_done' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Filter nach Status. true = erledigte Aufgaben, false = offene Aufgaben. Wenn nicht angegeben, werden alle Aufgaben angezeigt.'
                ],
                'is_personal' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Filter nach persönlichen Aufgaben (ohne Projekt). true = nur persönliche Aufgaben, false = nur Projekt-Aufgaben. Wenn nicht angegeben, werden alle angezeigt.'
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl der Ergebnisse. Standard: 50.'
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

            // Query aufbauen
            $query = PlannerTask::query()
                ->with(['project', 'projectSlot', 'user', 'userInCharge']);

            // Filter: Projekt
            if (!empty($arguments['project_id'])) {
                $query->where('project_id', $arguments['project_id']);
            }

            // Filter: Slot
            if (!empty($arguments['project_slot_id'])) {
                $query->where('project_slot_id', $arguments['project_slot_id']);
            }

            // Filter: User in Charge (Standard: aktueller User)
            $userInChargeId = $arguments['user_in_charge_id'] ?? $context->user->id;
            $query->where('user_in_charge_id', $userInChargeId);

            // Filter: Status
            if (isset($arguments['is_done'])) {
                $query->where('is_done', $arguments['is_done']);
            }

            // Filter: Persönliche Aufgaben
            if (isset($arguments['is_personal'])) {
                if ($arguments['is_personal']) {
                    $query->whereNull('project_id');
                } else {
                    $query->whereNotNull('project_id');
                }
            }

            // Limit
            $limit = $arguments['limit'] ?? 50;
            $query->limit($limit);

            // Sortierung: zuerst nach Fälligkeitsdatum, dann nach Erstellungsdatum
            $query->orderBy('due_date', 'asc')
                ->orderBy('created_at', 'desc');

            // Aufgaben holen
            $tasks = $query->get();

            // Aufgaben formatieren
            $tasksList = $tasks->map(function($task) {
                return [
                    'id' => $task->id,
                    'uuid' => $task->uuid,
                    'title' => $task->title,
                    'description' => $task->description,
                    'dod' => $task->dod,
                    'due_date' => $task->due_date?->toIso8601String(),
                    'is_done' => $task->is_done,
                    'done_at' => $task->done_at?->toIso8601String(),
                    'project_id' => $task->project_id,
                    'project_name' => $task->project?->name,
                    'project_slot_id' => $task->project_slot_id,
                    'project_slot_name' => $task->projectSlot?->name,
                    'user_in_charge_id' => $task->user_in_charge_id,
                    'user_in_charge_name' => $task->userInCharge?->name ?? 'Unbekannt',
                    'is_personal' => $task->project_id === null,
                    'planned_minutes' => $task->planned_minutes,
                    'created_at' => $task->created_at->toIso8601String(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'tasks' => $tasksList,
                'count' => count($tasksList),
                'filters' => [
                    'project_id' => $arguments['project_id'] ?? null,
                    'project_slot_id' => $arguments['project_slot_id'] ?? null,
                    'user_in_charge_id' => $userInChargeId,
                    'is_done' => $arguments['is_done'] ?? null,
                    'is_personal' => $arguments['is_personal'] ?? null,
                ],
                'message' => count($tasksList) > 0 
                    ? count($tasksList) . ' Aufgabe(n) gefunden.'
                    : 'Keine Aufgaben gefunden.'
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Aufgaben: ' . $e->getMessage());
        }
    }
}

