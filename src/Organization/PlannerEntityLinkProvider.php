<?php

namespace Platform\Planner\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

class PlannerEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['project', 'planner_task'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'project' => ['label' => 'Projekte', 'icon' => 'folder', 'route' => 'planner.projects.show'],
            'planner_task' => ['label' => 'Aufgaben', 'icon' => 'clipboard-document-check', 'route' => null],
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
                    'tasks as done_tasks_count' => fn($q) => $q->where('is_done', true),
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
                'is_done' => $model->is_done ?? false,
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
                'is_done' => (bool) $task->is_done,
                'is_frog' => (bool) ($task->is_frog ?? false),
                'responsible' => $task->userInCharge?->name,
                'priority' => $task->priority?->value ?? null,
                'story_points' => $task->story_points?->value ?? null,
                'logged_minutes' => $taskMinutes,
                'due_date' => $task->due_date?->format('d.m.Y'),
            ];
        }

        return [
            'done' => $project->done ?? false,
            'responsible' => $project->user?->name,
            'task_count' => $project->tasks_count ?? 0,
            'done_task_count' => $project->done_tasks_count ?? 0,
            'logged_minutes' => $loggedMinutes,
            'budget_amount' => $project->budget_amount,
            'has_tasks' => count($taskItems) > 0,
            'task_items' => $taskItems,
        ];
    }
}
