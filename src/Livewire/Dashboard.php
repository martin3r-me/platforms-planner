<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
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

        $myOpenTasksCount = $myTasksQuery()->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)->count();

        $myOverdueCount = $myTasksQuery()
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->count();

        $myDueTodayCount = $myTasksQuery()
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereDate('due_date', now()->toDateString())
            ->count();

        // Meine überfälligen Aufgaben (mit Details)
        $overdueTasksList = $myTasksQuery()
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->with(['project'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Meine anstehenden Aufgaben (nächste 7 Tage)
        $upcomingTasksList = $myTasksQuery()
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->with(['project'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Meine Aufgaben — Vorschau (alle offenen, sortiert nach Datum)
        $myTasksList = $myTasksQuery()
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->with(['project'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(10)
            ->get();

        // Meine Frösche
        $myFrogsCount = $myTasksQuery()
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->where('is_frog', true)
            ->count();

        // Delegierte Aufgaben (von mir erstellt, jemand anders verantwortlich)
        $delegatedOpenCount = PlannerTask::withStale()
            ->where('team_id', $team->id)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
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
                    ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
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

        $activeProjectsCollection = $projects
            ->filter(fn ($p) => $p->lifecycle_state !== ProjectLifecycleState::COMPLETED
                                && $p->lifecycle_state !== ProjectLifecycleState::DISCARDED)
            ->values();

        // "Kürzlich abgeschlossen" = seit 30 Tagen abgeschlossen. Wir nutzen
        // lifecycle_state_changed_at als "wann fertig geworden"-Zeitpunkt.
        $recentlyCompletedProjects = $projects
            ->filter(fn ($p) => $p->lifecycle_state === ProjectLifecycleState::COMPLETED
                                && $p->lifecycle_state_changed_at
                                && $p->lifecycle_state_changed_at->gte(now()->subDays(30)))
            ->sortByDesc('lifecycle_state_changed_at')
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

        $openTasks      = $projectTasks->filter(fn($t) => $t->lifecycle_state === TaskLifecycleState::ACTIVE)->count();
        $completedTasks = $projectTasks->filter(fn($t) => $t->lifecycle_state === TaskLifecycleState::COMPLETED)->count();
        $totalTasks     = $projectTasks->count();
        $progressPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        $myOpenTasks = $projectTasks
            ->filter(fn($t) => $t->lifecycle_state === TaskLifecycleState::ACTIVE && $t->user_in_charge_id === $user->id)
            ->count();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'color' => $project->color ?? null,
            'done' => $project->lifecycle_state === ProjectLifecycleState::COMPLETED,
            'done_at' => $project->lifecycle_state === ProjectLifecycleState::COMPLETED
                ? $project->lifecycle_state_changed_at : null,
            'open_tasks' => $openTasks,
            'completed_tasks' => $completedTasks,
            'total_tasks' => $totalTasks,
            'progress_percent' => $progressPercent,
            'my_open_tasks' => $myOpenTasks,
        ];
    }
}
