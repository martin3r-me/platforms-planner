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

        // Thresholds aus den Models auslesen
        $projectThreshold = now()->subDays((new PlannerProject)->getStalenessThresholdDays());
        $taskThreshold = now()->subDays((new PlannerTask)->getStalenessThresholdDays());

        // === STALE DATA ===
        // "Vergessen" = last_viewed_at IS NULL (nie angesehen) ODER aelter als Threshold

        $staleProjects = PlannerProject::withStale()
            ->where('team_id', $team->id)
            ->where('done', false)
            ->visibleTo($user)
            ->where(function ($q) use ($projectThreshold) {
                $q->whereNull('last_viewed_at')
                  ->orWhere('last_viewed_at', '<', $projectThreshold);
            })
            ->withCount(['tasks as open_tasks_count' => function ($q) {
                $q->where('is_done', false);
            }])
            ->withCount(['tasks as total_tasks_count'])
            ->orderByRaw('last_viewed_at IS NULL DESC, last_viewed_at ASC')
            ->get();

        $staleTasksQuery = PlannerTask::withStale()
            ->where('is_done', false)
            ->where(function ($q) use ($taskThreshold) {
                $q->whereNull('last_viewed_at')
                  ->orWhere('last_viewed_at', '<', $taskThreshold);
            })
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
            ->orderByRaw('last_viewed_at IS NULL DESC, last_viewed_at ASC')
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
        // NULL (nie angesehen) ist "aelter" als alles andere
        $oldestStaleProject = $staleProjects
            ->sortBy(fn($p) => $p->last_viewed_at ?? \Carbon\Carbon::createFromTimestamp(0))
            ->first();
        $oldestStaleTask = $staleTasks
            ->sortBy(fn($t) => $t->last_viewed_at ?? \Carbon\Carbon::createFromTimestamp(0))
            ->first();

        // Counts: nie angesehen vs. tatsaechlich stale
        $neverViewedProjectsCount = $staleProjects->filter(fn($p) => $p->last_viewed_at === null)->count();
        $neverViewedTasksCount = $staleTasks->filter(fn($t) => $t->last_viewed_at === null)->count();

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
            'neverViewedProjectsCount' => $neverViewedProjectsCount,
            'neverViewedTasksCount' => $neverViewedTasksCount,
            'availableProjects' => $availableProjects,
        ])->layout('platform::layouts.app');
    }
}
