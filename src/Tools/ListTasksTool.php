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
 * Tool zum Auflisten von Aufgaben im Planner-Modul
 * 
 * Ermöglicht es der AI, Aufgaben zu finden und anzuzeigen.
 */
class ListTasksTool implements ToolContract
{
    use HasStandardGetOperations;
    public function getName(): string
    {
        return 'planner.tasks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /tasks?project_id={id}&project_slot_id={id}&user_in_charge_id={id}&filters=[...]&search=...&sort=[...] - Listet Aufgaben auf. REST-Parameter: project_id (optional, integer) - Filter nach Projekt. project_slot_id (optional, integer) - Filter nach Slot. user_in_charge_id (optional, integer) - Filter nach User (wenn nicht angegeben, aktueller User). filters (optional, array) - Filter-Array mit field, op, value. search (optional, string) - Suchbegriff. sort (optional, array) - Sortierung mit field, dir. limit/offset (optional) - Pagination.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    // Legacy-Parameter (für Backwards-Kompatibilität und einfache Nutzung)
                    'project_id' => [
                        'type' => 'integer',
                        'description' => 'REST-Parameter (optional): Filter nach Projekt-ID. Beispiel: project_id=123. Nutze "planner.projects.GET" um verfügbare Projekt-IDs zu sehen.'
                    ],
                    'project_slot_id' => [
                        'type' => 'integer',
                        'description' => 'REST-Parameter (optional): Filter nach Slot-ID. Beispiel: project_slot_id=456. Nutze "planner.project_slots.GET" um verfügbare Slot-IDs zu sehen.'
                    ],
                    'user_in_charge_id' => [
                        'type' => 'integer',
                        'description' => 'REST-Parameter (optional): Filter nach User-ID (zuständiger User). Beispiel: user_in_charge_id=789. Wenn nicht angegeben, werden Aufgaben des aktuellen Users angezeigt.'
                    ],
                    'is_done' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach Status (Legacy - nutze stattdessen filters mit field="is_done" und op="eq"). true = erledigte Aufgaben, false = offene Aufgaben. Wenn nicht angegeben, werden alle Aufgaben angezeigt.'
                    ],
                    'is_personal' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach persönlichen Aufgaben (Legacy - nutze stattdessen filters mit field="project_id" und op="is_null" für persönliche Aufgaben). true = nur persönliche Aufgaben, false = nur Projekt-Aufgaben. Wenn nicht angegeben, werden alle angezeigt.'
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

            // Query aufbauen
            $query = PlannerTask::query()
                ->with(['project', 'projectSlot', 'user', 'userInCharge']);

            // Standard-Operationen anwenden
            $this->applyStandardFilters($query, $arguments, [
                'project_id', 'project_slot_id', 'user_in_charge_id', 'is_done', 
                'title', 'description', 'due_date', 'created_at', 'updated_at'
            ]);
            
            // Legacy: project_id (für Backwards-Kompatibilität)
            if (!empty($arguments['project_id'])) {
                $query->where('project_id', $arguments['project_id']);
            }
            
            // Legacy: project_slot_id (für Backwards-Kompatibilität)
            if (!empty($arguments['project_slot_id'])) {
                $query->where('project_slot_id', $arguments['project_slot_id']);
            }
            
            // Legacy: user_in_charge_id (Standard: aktueller User)
            // WICHTIG: 0 bedeutet "nicht gesetzt", nicht "User mit ID 0"
            $userInChargeId = null;
            if (isset($arguments['user_in_charge_id']) && $arguments['user_in_charge_id'] !== 0 && $arguments['user_in_charge_id'] !== '0') {
                $userInChargeId = (int)$arguments['user_in_charge_id'];
            }
            
            // Prüfe, ob user_in_charge_id bereits in Standard-Filters ist
            $hasUserFilterInStandard = $this->hasFilterForField($arguments['filters'] ?? [], 'user_in_charge_id');
            
            // Wenn explizit angegeben: IMMER filtern (auch wenn project_id gesetzt ist)
            if ($userInChargeId !== null && !$hasUserFilterInStandard) {
                $query->where('user_in_charge_id', $userInChargeId);
            } elseif ($userInChargeId === null && !$hasUserFilterInStandard) {
                // Wenn NICHT angegeben: Standard-Verhalten
                $hasProjectFilter = !empty($arguments['project_id']) || $this->hasFilterForField($arguments['filters'] ?? [], 'project_id');
                if (!$hasProjectFilter) {
                    // Kein Projekt-Filter: Zeige nur Aufgaben des aktuellen Users (Standard)
                    $query->where('user_in_charge_id', $context->user->id);
                }
                // Wenn Projekt-Filter vorhanden: Zeige ALLE Aufgaben des Projekts (kein User-Filter)
            }
            
            // Legacy: is_done (für Backwards-Kompatibilität)
            // WICHTIG: Nur anwenden, wenn explizit gesetzt (nicht wenn null)
            if (isset($arguments['is_done']) && $arguments['is_done'] !== null) {
                $query->where('is_done', (bool)$arguments['is_done']);
            }
            
            // Legacy: is_personal (für Backwards-Kompatibilität)
            // WICHTIG: Nur anwenden, wenn explizit gesetzt (nicht wenn null)
            if (isset($arguments['is_personal']) && $arguments['is_personal'] !== null) {
                if ($arguments['is_personal']) {
                    $query->whereNull('project_id');
                } else {
                    $query->whereNotNull('project_id');
                }
            }
            
            // Standard-Suche anwenden
            $this->applyStandardSearch($query, $arguments, ['title', 'description']);
            
            // Standard-Sortierung anwenden (Default: due_date asc, dann created_at desc)
            $this->applyStandardSort($query, $arguments, [
                'title', 'description', 'due_date', 'created_at', 'updated_at', 
                'is_done', 'done_at', 'user_in_charge_id'
            ], 'due_date', 'asc');
            
            // Wenn keine explizite Sortierung, füge created_at desc hinzu
            if (empty($arguments['sort'])) {
                $query->orderBy('created_at', 'desc');
            }
            
            // Standard-Pagination anwenden
            $this->applyStandardPagination($query, $arguments);

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
    
    /**
     * Prüft ob ein Filter für ein bestimmtes Feld vorhanden ist
     */
    private function hasFilterForField(array $filters, string $field): bool
    {
        if (empty($filters) || !is_array($filters)) {
            return false;
        }
        
        foreach ($filters as $filter) {
            if (($filter['field'] ?? null) === $field) {
                return true;
            }
        }
        return false;
    }
}

