<?php

namespace Platform\Planner\Organization;

use Illuminate\Support\Collection;
use Platform\Organization\Contracts\ProvidesShareView;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerTask;

/**
 * Kundenansicht für geteilte Knoten (Kunden-Tor): Projekte mit Fortschritt,
 * zuletzt erledigten und nächsten Schritten. Bewusst ohne Interna — keine
 * Zeiten, Budgets, Verantwortlichen oder Beschreibungen.
 */
class PlannerShareView implements ProvidesShareView
{
    public function shareTypes(): array
    {
        return [
            'project' => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'featured' => true],
            'planner_task' => ['label' => 'Aufgaben', 'singular' => 'Aufgabe', 'icon' => 'clipboard-document-check', 'featured' => false],
        ];
    }

    public function shareItems(string $alias, Collection $models, array $options): array
    {
        return match ($alias) {
            'project' => $this->projects($models, (bool) ($options['details'] ?? true)),
            'planner_task' => $this->tasks($models),
            default => [],
        };
    }

    protected function projects(Collection $projects, bool $details): array
    {
        $projects = $projects->filter(fn ($p) => $p->lifecycle_state !== ProjectLifecycleState::DISCARDED);
        if ($projects->isEmpty()) {
            return [];
        }

        $tasksByProject = PlannerTask::whereIn('project_id', $projects->pluck('id'))
            ->where('lifecycle_state', '!=', TaskLifecycleState::DISCARDED->value)
            ->get(['id', 'project_id', 'title', 'lifecycle_state', 'lifecycle_state_changed_at', 'due_date', 'order', 'updated_at'])
            ->groupBy('project_id');

        return $projects->map(function ($project) use ($tasksByProject, $details) {
            $tasks = $tasksByProject->get($project->id, collect());
            [$done, $open] = $tasks->partition(fn ($t) => $t->lifecycle_state === TaskLifecycleState::COMPLETED);

            $state = match ($project->lifecycle_state) {
                ProjectLifecycleState::COMPLETED => ['done', 'Abgeschlossen'],
                ProjectLifecycleState::DORMANT => ['paused', 'Pausiert'],
                default => ['active', 'Läuft'],
            };

            $item = [
                'id' => $project->id,
                'title' => $project->name,
                'subtitle' => null,
                'state' => $state[0],
                'state_label' => $state[1],
                'progress' => ['done' => $done->count(), 'total' => $tasks->count()],
                'due' => null,
                'updated_at' => collect([$project->updated_at, $tasks->max('updated_at')])->filter()->max(),
                'counts' => ['open' => $open->count(), 'done' => $done->count()],
            ];

            if ($details) {
                $item['recent'] = $done->sortByDesc(fn ($t) => $t->lifecycle_state_changed_at?->timestamp ?? 0)
                    ->take(3)
                    ->map(fn ($t) => ['title' => $t->title, 'at' => $t->lifecycle_state_changed_at])
                    ->values()->all();
                $item['next'] = $open->sortBy(fn ($t) => [$t->due_date ? 0 : 1, $t->due_date?->timestamp ?? 0, $t->order ?? 0])
                    ->take(3)
                    ->map(fn ($t) => ['title' => $t->title, 'due' => $t->due_date])
                    ->values()->all();
            }

            return $item;
        })->values()->all();
    }

    protected function tasks(Collection $tasks): array
    {
        return $tasks->filter(fn ($t) => $t->lifecycle_state !== TaskLifecycleState::DISCARDED)
            ->map(function ($t) {
                $done = $t->lifecycle_state === TaskLifecycleState::COMPLETED;

                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'state' => $done ? 'done' : 'active',
                    'state_label' => $done ? 'Erledigt' : ($t->due_date ? 'bis ' . $t->due_date->copy()->locale('de')->isoFormat('D. MMM') : 'Offen'),
                    'due' => $t->due_date,
                    'updated_at' => $t->updated_at,
                ];
            })->values()->all();
    }
}
