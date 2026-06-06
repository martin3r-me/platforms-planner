<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // === MEINE ZEIT-AGGREGATE ===
        $myMonthlyMinutes = (int) OrganizationTimeEntry::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('minutes');

        // === MEINE AUFGABEN (nur was mir gehört) ===
        $myTasksQuery = fn() => PlannerTask::withStale()
            ->where('team_id', $team->id)
            ->where('user_in_charge_id', $user->id)
            ->visibleTo($user)
            ->where(function ($q) {
                $q->whereNotNull('project_slot_id')
                  ->orWhere(function ($slotQ) {
                      $slotQ->whereNull('project_slot_id')
                            ->whereNull('sprint_slot_id');
                  });
            });

        $myOpenTasksCount = $myTasksQuery()->where('is_done', false)->count();

        $myOverdueCount = $myTasksQuery()
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->count();

        $myDueTodayCount = $myTasksQuery()
            ->where('is_done', false)
            ->whereDate('due_date', now()->toDateString())
            ->count();

        // Meine überfälligen Aufgaben (mit Details)
        $overdueTasksList = $myTasksQuery()
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->with(['project'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Meine anstehenden Aufgaben (nächste 7 Tage)
        $upcomingTasksList = $myTasksQuery()
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->with(['project'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Meine Aufgaben — Vorschau (alle offenen, sortiert nach Datum)
        $myTasksList = $myTasksQuery()
            ->where('is_done', false)
            ->with(['project'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(10)
            ->get();

        // Meine Frösche
        $myFrogsCount = $myTasksQuery()
            ->where('is_done', false)
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

        // === MEINE PROJEKTE ===
        // Projekte in denen ich offene Aufgaben habe ODER Mitglied bin
        $myProjectIds = collect()
            ->merge(
                PlannerTask::where('team_id', $team->id)
                    ->where('user_in_charge_id', $user->id)
                    ->where('is_done', false)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                DB::table('planner_project_users')
                    ->where('user_id', $user->id)
                    ->pluck('project_id')
            )
            ->unique()
            ->values();

        $projects = PlannerProject::withStale()
            ->where('team_id', $team->id)
            ->whereIn('id', $myProjectIds)
            ->visibleTo($user)
            ->orderBy('name')
            ->get();

        $activeProjectsCollection = $projects->where('done', false)->values();

        $recentlyCompletedProjects = $projects
            ->filter(fn($p) => $p->done && $p->done_at && $p->done_at->gte(now()->subDays(30)))
            ->sortByDesc('done_at')
            ->values();

        $projectsWithProgress = $activeProjectsCollection
            ->map(fn($p) => $this->buildProjectProgress($p, $user))
            ->sortByDesc('my_open_tasks')
            ->values();

        $recentlyCompletedWithProgress = $recentlyCompletedProjects
            ->map(fn($p) => $this->buildProjectProgress($p, $user))
            ->values();

        return view('planner::livewire.dashboard', [
            'myOverdueCount'    => $myOverdueCount,
            'myDueTodayCount'   => $myDueTodayCount,
            'myMonthlyMinutes'  => $myMonthlyMinutes,
            'overdueTasksList'  => $overdueTasksList,
            'upcomingTasksList' => $upcomingTasksList,
            'myTasksList'       => $myTasksList,
            'myOpenTasksCount'  => $myOpenTasksCount,
            'myFrogsCount'      => $myFrogsCount,
            'delegatedOpenCount'=> $delegatedOpenCount,
            'projectsWithProgress' => $projectsWithProgress,
            'recentlyCompletedWithProgress' => $recentlyCompletedWithProgress,
            'showCompletedProjects' => $this->showCompletedProjects,
        ])->layout('platform::layouts.app');
    }

    private function buildProjectProgress(PlannerProject $project, $user): array
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

        $openTasks      = $projectTasks->filter(fn($t) => !$t->is_done)->count();
        $completedTasks = $projectTasks->filter(fn($t) => (bool)$t->is_done)->count();
        $totalTasks     = $projectTasks->count();
        $progressPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        $myOpenTasks = $projectTasks
            ->filter(fn($t) => !$t->is_done && $t->user_in_charge_id === $user->id)
            ->count();

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
            'my_open_tasks' => $myOpenTasks,
        ];
    }
}
