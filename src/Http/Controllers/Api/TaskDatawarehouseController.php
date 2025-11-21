<?php

namespace Platform\Planner\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Planner\Models\PlannerTask;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datawarehouse API Controller für Tasks
 * 
 * Stellt flexible Filter und Aggregationen für das Datawarehouse bereit.
 * Unterstützt Team-Hierarchien (inkl. Kind-Teams).
 */
class TaskDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Tasks
     * 
     * Unterstützt komplexe Filter und Aggregationen
     */
    public function index(Request $request)
    {
        $query = PlannerTask::query();

        // ===== FILTER =====
        $this->applyFilters($query, $request);

        // ===== AGGREGATION =====
        if ($request->has('aggregate')) {
            return $this->handleAggregation($query, $request);
        }

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        
        // Validierung der Sort-Spalte (Security)
        $allowedSortColumns = ['id', 'created_at', 'updated_at', 'done_at', 'due_date', 'title'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ===== PAGINATION =====
        $perPage = min($request->get('per_page', 100), 1000); // Max 1000 pro Seite
        $tasks = $query->paginate($perPage);

        // ===== FORMATTING =====
        // Datawarehouse-freundliches Format
        $formatted = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,
                'team_id' => $task->team_id,
                'user_id' => $task->user_id,
                'user_in_charge_id' => $task->user_in_charge_id,
                'project_id' => $task->project_id,
                'task_group_id' => $task->task_group_id,
                'is_done' => $task->is_done,
                'done_at' => $task->done_at?->toIso8601String(),
                'created_at' => $task->created_at->toIso8601String(),
                'updated_at' => $task->updated_at->toIso8601String(),
                'due_date' => $task->due_date?->toIso8601String(),
                'story_points' => $task->story_points?->value,
                'story_points_numeric' => $task->story_points?->points(),
                'priority' => $task->priority?->value,
                'is_frog' => $task->is_frog,
                'planned_minutes' => $task->planned_minutes,
            ];
        });

        return $this->paginated(
            $tasks->setCollection($formatted),
            'Tasks erfolgreich geladen'
        );
    }

    /**
     * Wendet alle Filter auf die Query an
     */
    protected function applyFilters($query, Request $request): void
    {
        // Team-Filter mit Kind-Teams Option
        if ($request->has('team_id')) {
            $teamId = $request->team_id;
            $includeChildren = $request->boolean('include_child_teams', false);
            
            if ($includeChildren) {
                // Team mit Kind-Teams laden
                $team = Team::find($teamId);
                
                if ($team) {
                    // Alle Team-IDs inkl. Kind-Teams sammeln (nutzt Team-Model Methode)
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    $query->whereIn('team_id', $teamIds);
                } else {
                    // Team nicht gefunden - leeres Ergebnis
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Nur das genannte Team
                $query->where('team_id', $teamId);
            }
        }

        // Erledigte Aufgaben (done_at)
        if ($request->has('is_done')) {
            if ($request->is_done === 'true' || $request->is_done === '1') {
                $query->whereNotNull('done_at');
            } elseif ($request->is_done === 'false' || $request->is_done === '0') {
                $query->whereNull('done_at');
            }
        }

        // Datums-Filter für done_at (heute erledigt)
        if ($request->boolean('done_today')) {
            $query->whereDate('done_at', Carbon::today());
        }

        // Datums-Range für done_at
        if ($request->has('done_from')) {
            $query->whereDate('done_at', '>=', $request->done_from);
        }
        if ($request->has('done_to')) {
            $query->whereDate('done_at', '<=', $request->done_to);
        }

        // Erstellt heute
        if ($request->boolean('created_today')) {
            $query->whereDate('created_at', Carbon::today());
        }

        // Erstellt in Range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // User-Filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('user_in_charge_id')) {
            $query->where('user_in_charge_id', $request->user_in_charge_id);
        }

        // Projekt-Filter
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Task Group Filter
        if ($request->has('task_group_id')) {
            $query->where('task_group_id', $request->task_group_id);
        }

        // Story Points Filter
        if ($request->has('has_story_points')) {
            if ($request->has_story_points === 'true' || $request->has_story_points === '1') {
                $query->whereNotNull('story_points');
            } else {
                $query->whereNull('story_points');
            }
        }

        // Priority Filter
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Is Frog Filter
        if ($request->has('is_frog')) {
            $query->where('is_frog', $request->boolean('is_frog'));
        }
    }


    /**
     * Aggregationen (z.B. Story Points Summe)
     */
    protected function handleAggregation($query, Request $request)
    {
        $aggregateType = $request->get('aggregate');
        $groupBy = $request->get('group_by');

        switch ($aggregateType) {
            case 'story_points_sum':
                return $this->aggregateStoryPoints($query, $groupBy);
            
            case 'count':
                return $this->aggregateCount($query, $groupBy);
            
            case 'story_points_avg':
                return $this->aggregateStoryPointsAvg($query, $groupBy);
            
            default:
                return $this->error('Unbekannte Aggregation: ' . $aggregateType);
        }
    }

    /**
     * Story Points Summe aggregieren
     */
    protected function aggregateStoryPoints($query, $groupBy = null)
    {
        // Lade alle Tasks (ohne Pagination für Aggregation)
        $tasks = $query->get();

        if ($groupBy === 'team_id') {
            // Gruppiert nach Team
            $result = $tasks->groupBy('team_id')->map(function ($teamTasks, $teamId) {
                return [
                    'team_id' => $teamId,
                    'total_story_points' => $teamTasks->sum(function ($task) {
                        return $task->story_points?->points() ?? 0;
                    }),
                    'task_count' => $teamTasks->count(),
                ];
            })->values();

            return $this->success($result, 'Story Points nach Team aggregiert');
        }

        if ($groupBy === 'date') {
            // Gruppiert nach Datum (done_at)
            $result = $tasks->groupBy(function ($task) {
                return $task->done_at?->format('Y-m-d') ?? 'no_date';
            })->map(function ($dateTasks, $date) {
                return [
                    'date' => $date === 'no_date' ? null : $date,
                    'total_story_points' => $dateTasks->sum(function ($task) {
                        return $task->story_points?->points() ?? 0;
                    }),
                    'task_count' => $dateTasks->count(),
                ];
            })->values();

            return $this->success($result, 'Story Points nach Datum aggregiert');
        }

        if ($groupBy === 'user_id') {
            // Gruppiert nach User
            $result = $tasks->groupBy('user_id')->map(function ($userTasks, $userId) {
                return [
                    'user_id' => $userId,
                    'total_story_points' => $userTasks->sum(function ($task) {
                        return $task->story_points?->points() ?? 0;
                    }),
                    'task_count' => $userTasks->count(),
                ];
            })->values();

            return $this->success($result, 'Story Points nach User aggregiert');
        }

        // Gesamt-Summe
        $total = $tasks->sum(function ($task) {
            return $task->story_points?->points() ?? 0;
        });

        return $this->success([
            'total_story_points' => $total,
            'task_count' => $tasks->count(),
        ], 'Story Points Summe');
    }

    /**
     * Story Points Durchschnitt aggregieren
     */
    protected function aggregateStoryPointsAvg($query, $groupBy = null)
    {
        $tasks = $query->get();

        if ($groupBy === 'team_id') {
            $result = $tasks->groupBy('team_id')->map(function ($teamTasks, $teamId) {
                $tasksWithPoints = $teamTasks->filter(fn($task) => $task->story_points !== null);
                $avg = $tasksWithPoints->count() > 0 
                    ? $tasksWithPoints->avg(fn($task) => $task->story_points->points())
                    : 0;

                return [
                    'team_id' => $teamId,
                    'avg_story_points' => round($avg, 2),
                    'task_count' => $teamTasks->count(),
                    'tasks_with_points' => $tasksWithPoints->count(),
                ];
            })->values();

            return $this->success($result, 'Durchschnittliche Story Points nach Team');
        }

        // Gesamt-Durchschnitt
        $tasksWithPoints = $tasks->filter(fn($task) => $task->story_points !== null);
        $avg = $tasksWithPoints->count() > 0 
            ? $tasksWithPoints->avg(fn($task) => $task->story_points->points())
            : 0;

        return $this->success([
            'avg_story_points' => round($avg, 2),
            'task_count' => $tasks->count(),
            'tasks_with_points' => $tasksWithPoints->count(),
        ], 'Durchschnittliche Story Points');
    }

    /**
     * Count-Aggregation
     */
    protected function aggregateCount($query, $groupBy = null)
    {
        if ($groupBy === 'team_id') {
            $result = $query->selectRaw('team_id, COUNT(*) as count')
                ->groupBy('team_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'team_id' => $item->team_id,
                        'count' => $item->count,
                    ];
                });

            return $this->success($result, 'Anzahl Tasks nach Team');
        }

        if ($groupBy === 'date') {
            $result = $query->selectRaw('DATE(done_at) as date, COUNT(*) as count')
                ->whereNotNull('done_at')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'count' => $item->count,
                    ];
                });

            return $this->success($result, 'Anzahl Tasks nach Datum');
        }

        if ($groupBy === 'user_id') {
            $result = $query->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'user_id' => $item->user_id,
                        'count' => $item->count,
                    ];
                });

            return $this->success($result, 'Anzahl Tasks nach User');
        }

        $count = $query->count();
        return $this->success(['count' => $count], 'Anzahl Tasks');
    }
}

