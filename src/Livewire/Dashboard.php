<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Livewire\Concerns\QuickTogglesDone;
use Platform\Organization\Models\OrganizationTimeEntry;

class Dashboard extends Component
{
    use QuickTogglesDone;
    public $showCompletedProjects = false;

    public function toggleCompletedProjects(): void
    {
        $this->showCompletedProjects = ! $this->showCompletedProjects;
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => 'Platform\Planner\Models\PlannerProject',
            'modelId' => null,
            'subject' => 'Planner Dashboard',
            'description' => 'Übersicht aller Projekte und Aufgaben',
            'url' => route('planner.dashboard'),
            'source' => 'planner.dashboard',
            'recipients' => [],
            'meta' => [
                'view_type' => 'dashboard',
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // === ZEIT-AGGREGATE (Team) ===
        $monthlyLoggedMinutes = (int) OrganizationTimeEntry::query()
            ->where('team_id', $team->id)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('minutes');

        // === PROJEKTE (Team, policy-gefiltert) ===
        $projects = PlannerProject::withStale()->where('team_id', $team->id)->visibleTo($user)->orderBy('name')->get();
        $activeProjectsCollection = $projects->where('done', false)->values();

        $recentlyCompletedProjects = $projects
            ->filter(fn($p) => $p->done && $p->done_at && $p->done_at->gte(now()->subDays(30)))
            ->sortByDesc('done_at')
            ->values();

        // === TEAM-AUFGABEN (policy-gefiltert) ===
        $teamTasksQuery = fn() => PlannerTask::withStale()
            ->where('team_id', $team->id)
            ->visibleTo($user)
            ->where(function ($q) {
                $q->whereNotNull('project_slot_id')
                  ->orWhere(function ($slotQ) {
                      $slotQ->whereNull('project_slot_id')
                            ->whereNull('sprint_slot_id');
                  });
            });

        $teamTasks = $teamTasksQuery()->get();

        $openTasks = $teamTasks->where('is_done', false)->count();

        $overdueTasksCount = $teamTasks->where('is_done', false)
            ->filter(fn($task) => $task->due_date && $task->due_date->isPast())
            ->count();

        // Due today count
        $dueTodayCount = $teamTasks->where('is_done', false)
            ->filter(fn($task) => $task->due_date && $task->due_date->isToday())
            ->count();

        // Überfällige Tasks (with details)
        $overdueTasksList = $teamTasksQuery()
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->with(['project', 'userInCharge'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Anstehende Tasks (nächste 7 Tage)
        $upcomingTasksList = $teamTasksQuery()
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->with(['project', 'userInCharge'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Meine Aufgaben (user is in charge, no due date or future due date)
        $myTasksList = $teamTasksQuery()
            ->where('is_done', false)
            ->where('user_in_charge_id', $user->id)
            ->with(['project'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(10)
            ->get();

        // Meine offenen Aufgaben (Gesamtzahl)
        $myOpenTasksCount = $teamTasksQuery()
            ->where('is_done', false)
            ->where('user_in_charge_id', $user->id)
            ->count();

        // Frösche (meine)
        $myFrogsCount = $teamTasksQuery()
            ->where('is_done', false)
            ->where('user_in_charge_id', $user->id)
            ->where('is_frog', true)
            ->count();

        // Delegierte Aufgaben (von mir erstellt, jemand anders verantwortlich)
        $delegatedOpenCount = PlannerTask::withStale()
            ->where('team_id', $team->id)
            ->where('is_done', false)
            ->where('user_id', $user->id)
            ->whereNotNull('user_in_charge_id')
            ->where('user_in_charge_id', '!=', $user->id)
            ->count();

        // === PROJEKTE MIT FORTSCHRITT ===
        $projectsWithProgress = $activeProjectsCollection
            ->map(fn($p) => $this->buildProjectProgress($p))
            ->sortByDesc('open_tasks')
            ->values();

        $recentlyCompletedWithProgress = $recentlyCompletedProjects
            ->map(fn($p) => $this->buildProjectProgress($p))
            ->values();

        return view('planner::livewire.dashboard', [
            'openTasks' => $openTasks,
            'overdueTasksCount' => $overdueTasksCount,
            'dueTodayCount' => $dueTodayCount,
            'monthlyLoggedMinutes' => $monthlyLoggedMinutes,
            'overdueTasksList' => $overdueTasksList,
            'upcomingTasksList' => $upcomingTasksList,
            'myTasksList' => $myTasksList,
            'myOpenTasksCount' => $myOpenTasksCount,
            'myFrogsCount' => $myFrogsCount,
            'delegatedOpenCount' => $delegatedOpenCount,
            'projectsWithProgress' => $projectsWithProgress,
            'recentlyCompletedWithProgress' => $recentlyCompletedWithProgress,
            'showCompletedProjects' => $this->showCompletedProjects,
        ])->layout('platform::layouts.app');
    }

    private function buildProjectProgress(PlannerProject $project): array
    {
        $projectTasks = PlannerTask::where('project_id', $project->id)
            ->where(function ($q) {
                $q->whereNotNull('project_slot_id')
                  ->orWhere(function ($slotQ) {
                      $slotQ->whereNull('project_slot_id')
                            ->whereNull('sprint_slot_id');
                  });
            })
            ->get();

        $openTasks = $projectTasks->filter(fn($t) => !$t->is_done)->count();
        $completedTasks = $projectTasks->filter(fn($t) => (bool)$t->is_done)->count();
        $totalTasks = $projectTasks->count();
        $progressPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'color' => $project->color ?? null,
            'done' => (bool) $project->done,
            'done_at' => $project->done_at,
            'open_tasks' => $openTasks,
            'completed_tasks' => $completedTasks,
            'total_tasks' => $totalTasks,
            'progress_percent' => $progressPercent,
        ];
    }
}
