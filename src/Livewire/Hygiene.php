<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Enums\TaskStoryPoints;
use Livewire\Attributes\On;

class Hygiene extends Component
{
    public string $tab = 'stale'; // stale, recent
    public string $entityType = 'all'; // all, projects, tasks
    public ?int $projectFilter = null;

    #[On('updateDashboard')]
    public function updateDashboard() {}

    #[On('taskUpdated')]
    public function tasksUpdated() {}

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => 'Platform\Planner\Models\PlannerTask',
            'modelId' => null,
            'subject' => 'Hygiene',
            'description' => 'Übersicht über vergessene und kürzlich betrachtete Projekte & Aufgaben',
            'url' => route('planner.hygiene'),
            'source' => 'planner.hygiene',
            'recipients' => [],
            'meta' => [
                'view_type' => 'hygiene',
                'user_id' => Auth::id(),
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $projectIds = PlannerProjectUser::where('user_id', $user->id)
            ->pluck('project_id')
            ->toArray();

        // === STALE DATA ===

        // Stale Projects (nicht erledigt, last_viewed_at abgelaufen)
        $staleProjects = PlannerProject::onlyStale()
            ->where('team_id', $team->id)
            ->where('done', false)
            ->visibleTo($user)
            ->withCount(['tasks as open_tasks_count' => function ($q) {
                $q->where('is_done', false);
            }])
            ->withCount(['tasks as total_tasks_count'])
            ->orderBy('last_viewed_at', 'asc')
            ->get();

        // Stale Tasks (nicht erledigt, last_viewed_at abgelaufen)
        $staleTasksQuery = PlannerTask::onlyStale()
            ->where('is_done', false)
            ->where(function ($q) use ($user, $projectIds) {
                $q->where(function ($q) use ($user) {
                    $q->whereNull('project_id')
                      ->where('user_id', $user->id);
                })
                ->orWhere(function ($q) use ($projectIds) {
                    $q->whereNotNull('project_id')
                      ->whereIn('project_id', $projectIds);
                });
            });

        if ($this->projectFilter) {
            $staleTasksQuery->where('project_id', $this->projectFilter);
        }

        $staleTasks = $staleTasksQuery
            ->with(['project', 'userInCharge'])
            ->orderBy('last_viewed_at', 'asc')
            ->get();

        // === RECENT DATA ===

        // Recently viewed projects (letzte 14 Tage, sortiert nach last_viewed_at desc)
        $recentProjects = PlannerProject::withStale()
            ->where('team_id', $team->id)
            ->where('done', false)
            ->visibleTo($user)
            ->whereNotNull('last_viewed_at')
            ->where('last_viewed_at', '>=', now()->subDays(14))
            ->withCount(['tasks as open_tasks_count' => function ($q) {
                $q->where('is_done', false);
            }])
            ->orderByDesc('last_viewed_at')
            ->limit(20)
            ->get();

        // Recently viewed tasks (letzte 7 Tage)
        $recentTasks = PlannerTask::withStale()
            ->where('is_done', false)
            ->where(function ($q) use ($user, $projectIds) {
                $q->where(function ($q) use ($user) {
                    $q->whereNull('project_id')
                      ->where('user_id', $user->id);
                })
                ->orWhere(function ($q) use ($projectIds) {
                    $q->whereNotNull('project_id')
                      ->whereIn('project_id', $projectIds);
                });
            })
            ->whereNotNull('last_viewed_at')
            ->where('last_viewed_at', '>=', now()->subDays(7))
            ->with(['project', 'userInCharge'])
            ->orderByDesc('last_viewed_at')
            ->limit(30)
            ->get();

        // === KPIs ===
        $staleProjectsCount = $staleProjects->count();
        $staleTasksCount = $staleTasks->count();
        $staleTasksWithDueDate = $staleTasks->filter(fn($t) => $t->due_date)->count();
        $staleOverdue = $staleTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast())->count();
        $staleSP = $staleTasks->sum(fn($t) => $t->story_points instanceof TaskStoryPoints ? $t->story_points->points() : 0);
        $oldestStaleProject = $staleProjects->sortBy('last_viewed_at')->first();
        $oldestStaleTask = $staleTasks->sortBy('last_viewed_at')->first();

        // Available projects for filter (from stale tasks)
        $availableProjects = PlannerProject::withStale()
            ->where('team_id', $team->id)
            ->whereIn('id', $staleTasks->pluck('project_id')->filter()->unique())
            ->orderBy('name')
            ->get();

        return view('planner::livewire.hygiene', [
            'staleProjects' => $staleProjects,
            'staleTasks' => $staleTasks,
            'recentProjects' => $recentProjects,
            'recentTasks' => $recentTasks,
            'staleProjectsCount' => $staleProjectsCount,
            'staleTasksCount' => $staleTasksCount,
            'staleTasksWithDueDate' => $staleTasksWithDueDate,
            'staleOverdue' => $staleOverdue,
            'staleSP' => $staleSP,
            'oldestStaleProject' => $oldestStaleProject,
            'oldestStaleTask' => $oldestStaleTask,
            'availableProjects' => $availableProjects,
        ])->layout('platform::layouts.app');
    }
}
