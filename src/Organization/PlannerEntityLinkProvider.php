<?php

namespace Platform\Planner\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Organization\Contracts\HasPersonMetrics;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

class PlannerEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions, HasPersonMetrics
{
    public function morphAliases(): array
    {
        return ['project', 'planner_task'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'project' => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'route' => 'planner.projects.show'],
            'planner_task' => ['label' => 'Aufgaben', 'singular' => 'Aufgabe', 'icon' => 'clipboard-document-check', 'route' => null],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        match ($morphAlias) {
            'project' => $query
                ->with([
                    'user:id,name',
                    'tasks' => fn($q) => $q
                        ->with('userInCharge:id,name')
                        ->selectRaw("planner_tasks.*, ({$this->timeMinutesSubquery('planner_tasks', PlannerTask::class)}) as logged_minutes_sum")
                        ->orderBy('order'),
                ])
                ->withCount([
                    'tasks',
                    'tasks as done_tasks_count' => fn($q) => $q->where('lifecycle_state', TaskLifecycleState::COMPLETED->value),
                ]),
            'planner_task' => $query
                ->with('userInCharge:id,name')
                ->selectRaw("planner_tasks.*, ({$this->timeMinutesSubquery('planner_tasks', $fqcn)}) as logged_minutes_sum"),
            default => null,
        };
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return match ($morphAlias) {
            'project' => $this->extractProjectMetadata($model),
            'planner_task' => [
                'is_done' => $model->lifecycle_state === TaskLifecycleState::COMPLETED,
                'lifecycle_state' => $model->lifecycle_state?->value,
                'is_frog' => (bool) ($model->is_frog ?? false),
                'responsible' => $model->userInCharge?->name,
                'priority' => $model->priority?->value ?? null,
                'story_points' => $model->story_points?->value ?? null,
                'logged_minutes' => (int) ($model->logged_minutes_sum ?? 0),
                'due_date' => $model->due_date?->format('d.m.Y'),
            ],
            default => [],
        };
    }

    public function metadataDisplayRules(): array
    {
        return [
            'project' => [
                ['field' => 'responsible', 'format' => 'text'],
                ['field' => 'task_count', 'format' => 'count_ratio', 'done_field' => 'done_task_count', 'suffix' => 'Tasks'],
                ['field' => 'logged_minutes', 'format' => 'time'],
                ['field' => 'done', 'format' => 'boolean_done'],
                ['field' => 'task_items', 'format' => 'expandable_children', 'child_type' => 'planner_task', 'name_field' => 'name', 'done_field' => 'is_done'],
            ],
            'planner_task' => [
                ['field' => 'responsible', 'format' => 'text'],
                ['field' => 'priority', 'format' => 'text'],
                ['field' => 'story_points', 'format' => 'text', 'suffix' => 'SP'],
                ['field' => 'is_frog', 'format' => 'boolean_frog'],
                ['field' => 'logged_minutes', 'format' => 'time'],
                ['field' => 'due_date', 'format' => 'text'],
                ['field' => 'is_done', 'format' => 'boolean_done'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [
            'project' => [PlannerProject::class, ['tasks', 'projectSlots.tasks']],
            'planner_task' => [PlannerTask::class, []],
        ];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        if ($morphAlias !== 'project' || empty($linkableIds)) {
            return [];
        }

        $taskIds = PlannerTask::whereIn('project_id', $linkableIds)->pluck('id')->all();

        if (empty($taskIds)) {
            return [];
        }

        return [PlannerTask::class => $taskIds];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'project') {
            return [];
        }

        // Collect all project IDs
        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        $projects = PlannerProject::whereIn('id', $allIds)
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn($q) => $q->where('lifecycle_state', TaskLifecycleState::COMPLETED->value),
            ])
            ->get()
            ->keyBy('id');

        // Batch-load story points per project
        $spByProject = PlannerTask::whereIn('project_id', $allIds)
            ->whereNotNull('story_points')
            ->select('project_id', 'story_points', 'lifecycle_state')
            ->get()
            ->groupBy('project_id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $done = 0;
            $spTotal = 0;
            $spDone = 0;
            foreach ($ids as $id) {
                $p = $projects[$id] ?? null;
                if ($p) {
                    $total += $p->tasks_count;
                    $done += $p->done_tasks_count;
                }
                foreach (($spByProject[$id] ?? []) as $task) {
                    $sp = $task->story_points->points();
                    $spTotal += $sp;
                    if ($task->lifecycle_state === TaskLifecycleState::COMPLETED) {
                        $spDone += $sp;
                    }
                }
            }
            $result[$entityId] = [
                'items_total' => $total,
                'items_done' => $done,
                'story_points_total' => $spTotal,
                'story_points_done' => $spDone,
            ];
        }

        return $result;
    }

    protected function timeMinutesSubquery(string $table, string $fqcn): string
    {
        $quoted = DB::getPdo()->quote($fqcn);
        return "SELECT COALESCE(SUM(minutes), 0) FROM organization_time_entries WHERE context_id = {$table}.id AND context_type = {$quoted} AND deleted_at IS NULL";
    }

    protected function extractProjectMetadata(mixed $project): array
    {
        $tasks = $project->tasks ?? collect();
        $loggedMinutes = $tasks->sum(fn($t) => (int) ($t->logged_minutes_sum ?? 0));

        $taskItems = [];
        foreach ($tasks as $task) {
            $taskMinutes = (int) ($task->logged_minutes_sum ?? 0);
            $taskItems[] = [
                'id' => $task->id,
                'name' => $task->title ?? '—',
                'is_done' => $task->lifecycle_state === TaskLifecycleState::COMPLETED,
                'lifecycle_state' => $task->lifecycle_state?->value,
                'is_frog' => (bool) ($task->is_frog ?? false),
                'responsible' => $task->userInCharge?->name,
                'priority' => $task->priority?->value ?? null,
                'story_points' => $task->story_points?->value ?? null,
                'logged_minutes' => $taskMinutes,
                'due_date' => $task->due_date?->format('d.m.Y'),
            ];
        }

        return [
            'done' => $project->lifecycle_state === ProjectLifecycleState::COMPLETED,
            'lifecycle_state' => $project->lifecycle_state?->value,
            'responsible' => $project->user?->name,
            'task_count' => $project->tasks_count ?? 0,
            'done_task_count' => $project->done_tasks_count ?? 0,
            'logged_minutes' => $loggedMinutes,
            'budget_amount' => $project->budget_amount,
            'has_tasks' => count($taskItems) > 0,
            'task_items' => $taskItems,
        ];
    }

    public function metricDefinitions(): array
    {
        return [
            'items_total'        => ['label' => 'Items (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'is_dimension_primary' => true],
            'items_done'         => ['label' => 'Items (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'count', 'pair' => 'items_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start', 'is_dimension_primary' => true],
            'story_points_total' => ['label' => 'Story Points (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'points', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'story_points_done'  => ['label' => 'Story Points (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'points', 'pair' => 'story_points_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],
        ];
    }

    public function personMetrics(array $userIds, int $teamId): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = PlannerTask::whereIn('user_in_charge_id', $userIds)
            ->where('team_id', $teamId)
            ->select(
                'user_in_charge_id',
                DB::raw("SUM(CASE WHEN lifecycle_state = 'aktiv' THEN 1 ELSE 0 END) as active_items"),
                DB::raw("SUM(CASE WHEN lifecycle_state = 'erledigt' THEN 1 ELSE 0 END) as completed_items"),
                DB::raw("SUM(CASE WHEN lifecycle_state = 'aktiv' THEN COALESCE(story_points, 0) ELSE 0 END) as story_points_total"),
                DB::raw("SUM(CASE WHEN lifecycle_state = 'erledigt' THEN COALESCE(story_points, 0) ELSE 0 END) as story_points_done"),
            )
            ->groupBy('user_in_charge_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->user_in_charge_id] = [
                'active_items' => (int) $row->active_items,
                'completed_items' => (int) $row->completed_items,
                'story_points_total' => (int) $row->story_points_total,
                'story_points_done' => (int) $row->story_points_done,
            ];
        }

        return $result;
    }

    public function personMetricDefinitions(): array
    {
        return [
            'active_items'       => ['label' => 'Aktive Items', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'count'],
            'completed_items'    => ['label' => 'Erledigte Items', 'group' => 'persons', 'direction' => 'up', 'unit' => 'count'],
            'story_points_total' => ['label' => 'Story Points gesamt', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'points'],
            'story_points_done'  => ['label' => 'Story Points erledigt', 'group' => 'persons', 'direction' => 'up', 'unit' => 'points'],
        ];
    }
}
