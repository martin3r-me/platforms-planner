<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\ActivityLog\Models\ActivityLogActivity;

class Dashboard extends Component
{
    public $perspective = 'team'; // legacy
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
                'perspective' => $this->perspective,
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->subMonth()->endOfMonth();

        // === ZEIT-AGGREGATE (Team) ===
        $baseTimeEntries = OrganizationTimeEntry::query()->where('team_id', $team->id);

        $totalLoggedMinutes = (int) (clone $baseTimeEntries)->sum('minutes');
        $totalLoggedAmountCents = (int) (clone $baseTimeEntries)->sum('amount_cents');

        $billedMinutes = (int) (clone $baseTimeEntries)->where('is_billed', true)->sum('minutes');
        $billedAmountCents = (int) (clone $baseTimeEntries)->where('is_billed', true)->sum('amount_cents');

        $monthlyLoggedMinutes = (int) (clone $baseTimeEntries)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('minutes');

        $lastMonthLoggedMinutes = (int) (clone $baseTimeEntries)
            ->whereBetween('work_date', [$startOfLastMonth->toDateString(), $endOfLastMonth->toDateString()])
            ->sum('minutes');

        $monthlyBilledMinutes = (int) (clone $baseTimeEntries)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where('is_billed', true)
            ->sum('minutes');

        $unbilledMinutes = max(0, $totalLoggedMinutes - $billedMinutes);
        $unbilledAmountCents = max(0, $totalLoggedAmountCents - $billedAmountCents);

        // === PROJEKTE (Team) ===
        // Bugfix: 'is_active' existiert nicht am Model → 'done' Feld verwenden
        $projects = PlannerProject::where('team_id', $team->id)->orderBy('name')->get();
        $activeProjectsCollection = $projects->where('done', false)->values();
        $completedProjects = $projects->where('done', true)->values();

        $activeProjects = $activeProjectsCollection->count();
        $totalProjects = $projects->count();

        $recentlyCompletedProjects = $projects
            ->filter(fn($p) => $p->done && $p->done_at && $p->done_at->gte(now()->subDays(30)))
            ->sortByDesc('done_at')
            ->values();

        // === TEAM-AUFGABEN ===
        $teamTasksQuery = fn() => PlannerTask::query()
            ->where('team_id', $team->id)
            ->where(function ($q) {
                $q->whereNotNull('project_slot_id')
                  ->orWhere(function ($slotQ) {
                      $slotQ->whereNull('project_slot_id')
                            ->whereNull('sprint_slot_id');
                  });
            });

        $teamTasks = $teamTasksQuery()->get();

        $openTasks = $teamTasks->where('is_done', false)->count();
        $completedTasks = $teamTasks->where('is_done', true)->count();
        $totalTasks = $teamTasks->count();
        $frogTasks = $teamTasks->where('is_done', false)->where('is_frog', true)->count();

        $overdueTasksCount = $teamTasks->where('is_done', false)
            ->filter(fn($task) => $task->due_date && $task->due_date->isPast())
            ->count();

        // Überfällige Tasks (als Collection, mit Details)
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

        // Story Points
        $totalStoryPoints = $teamTasks->sum(fn($t) => $t->story_points?->points() ?? 0);
        $completedStoryPoints = $teamTasks->where('is_done', true)
            ->sum(fn($t) => $t->story_points?->points() ?? 0);
        $openStoryPoints = $teamTasks->where('is_done', false)
            ->sum(fn($t) => $t->story_points?->points() ?? 0);

        // Monatliche Performance
        $monthlyCreatedTasks = (clone $teamTasksQuery())
            ->whereDate('created_at', '>=', $startOfMonth)
            ->count();

        $monthlyCompletedTasks = (clone $teamTasksQuery())
            ->whereDate('done_at', '>=', $startOfMonth)
            ->count();

        $lastMonthCompletedTasks = (clone $teamTasksQuery())
            ->whereBetween('done_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $monthlyCreatedPoints = (clone $teamTasksQuery())
            ->whereDate('created_at', '>=', $startOfMonth)
            ->get()
            ->sum(fn($t) => $t->story_points?->points() ?? 0);

        $monthlyCompletedPoints = (clone $teamTasksQuery())
            ->whereDate('done_at', '>=', $startOfMonth)
            ->get()
            ->sum(fn($t) => $t->story_points?->points() ?? 0);

        // === TEAM-MITGLIEDER-ÜBERSICHT ===
        $teamMembers = $team->users()->get()->map(function ($member) use ($team, $startOfMonth, $endOfMonth) {
            $memberTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) use ($member) {
                    $q->where(function ($q) use ($member) {
                        $q->whereNull('project_id')
                          ->where('user_id', $member->id);
                    })->orWhere(function ($q) use ($member) {
                        $q->whereNotNull('project_id')
                          ->where('user_in_charge_id', $member->id)
                          ->where(function ($subQ) {
                              $subQ->whereNotNull('project_slot_id')
                                   ->orWhere(function ($slotQ) {
                                       $slotQ->whereNull('project_slot_id')
                                             ->whereNull('sprint_slot_id');
                                   });
                          });
                    });
                })
                ->get();

            $openTasks = $memberTasks->filter(fn($t) => !$t->is_done)->count();
            $completedTasks = $memberTasks->filter(fn($t) => (bool)$t->is_done)->count();
            $totalTasks = $memberTasks->count();
            $openStoryPoints = $memberTasks->filter(fn($t) => !$t->is_done)
                ->sum(fn($t) => $t->story_points?->points() ?? 0);

            $monthlyMinutes = (int) OrganizationTimeEntry::query()
                ->where('team_id', $team->id)
                ->where('user_id', $member->id)
                ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->sum('minutes');

            $progressPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'profile_photo_url' => $member->profile_photo_url,
                'open_tasks' => $openTasks,
                'completed_tasks' => $completedTasks,
                'total_tasks' => $totalTasks,
                'open_story_points' => $openStoryPoints,
                'monthly_minutes' => $monthlyMinutes,
                'progress_percent' => $progressPercent,
            ];
        })->sortByDesc('open_tasks')->values();

        // === PROJEKTE MIT FORTSCHRITT (alle aktiven) ===
        $projectsWithProgress = $activeProjectsCollection
            ->map(fn($p) => $this->buildProjectProgress($p, $team, $startOfMonth, $endOfMonth))
            ->sortByDesc('open_tasks')
            ->values();

        $recentlyCompletedWithProgress = $recentlyCompletedProjects
            ->map(fn($p) => $this->buildProjectProgress($p, $team, $startOfMonth, $endOfMonth))
            ->values();

        // === SIDEBAR: SCHNELLSTATISTIKEN ===
        $todayCreatedTasks = PlannerTask::where('team_id', $team->id)
            ->whereDate('created_at', today())
            ->count();

        $todayCompletedTasks = PlannerTask::where('team_id', $team->id)
            ->whereDate('done_at', today())
            ->count();

        // === ECHTE AKTIVITÄTEN ===
        // Hinweis: ActivityLogActivity::subject() ist morphTo() ohne Argument und passt damit
        // nicht zu den tatsächlichen Spalten activityable_type/activityable_id. Daher manuell auflösen.
        $teamProjectIds = PlannerProject::where('team_id', $team->id)->pluck('id');
        $teamTaskIds = PlannerTask::where('team_id', $team->id)->pluck('id');

        $recentActivities = ActivityLogActivity::query()
            ->where(function ($q) use ($teamProjectIds, $teamTaskIds) {
                $q->where(function ($sq) use ($teamProjectIds) {
                    $sq->where('activityable_type', PlannerProject::class)
                       ->whereIn('activityable_id', $teamProjectIds);
                });
                if ($teamTaskIds->isNotEmpty()) {
                    $q->orWhere(function ($sq) use ($teamTaskIds) {
                        $sq->where('activityable_type', PlannerTask::class)
                           ->whereIn('activityable_id', $teamTaskIds);
                    });
                }
            })
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        // Subjekte manuell auflösen (Name/Title)
        $activityProjectIds = $recentActivities
            ->where('activityable_type', PlannerProject::class)
            ->pluck('activityable_id');
        $activityTaskIds = $recentActivities
            ->where('activityable_type', PlannerTask::class)
            ->pluck('activityable_id');

        $projectNameMap = PlannerProject::whereIn('id', $activityProjectIds)->pluck('name', 'id');
        $taskTitleMap = PlannerTask::whereIn('id', $activityTaskIds)->pluck('title', 'id');

        $recentActivities->each(function ($activity) use ($projectNameMap, $taskTitleMap) {
            $activity->subject_label = match ($activity->activityable_type) {
                PlannerProject::class => $projectNameMap[$activity->activityable_id] ?? 'Projekt',
                PlannerTask::class => $taskTitleMap[$activity->activityable_id] ?? 'Aufgabe',
                default => 'Element',
            };
            $activity->subject_kind = match ($activity->activityable_type) {
                PlannerProject::class => 'Projekt',
                PlannerTask::class => 'Aufgabe',
                default => null,
            };
        });

        // Trends
        $monthlyCompletedTrend = $this->calcTrend($monthlyCompletedTasks, $lastMonthCompletedTasks);
        $monthlyHoursTrend = $this->calcTrend($monthlyLoggedMinutes, $lastMonthLoggedMinutes);

        return view('planner::livewire.dashboard', [
            'currentDate' => now()->format('d.m.Y'),
            'currentDay' => now()->format('l'),
            'perspective' => $this->perspective,
            'showCompletedProjects' => $this->showCompletedProjects,

            // Hero-Tiles
            'activeProjects' => $activeProjects,
            'totalProjects' => $totalProjects,
            'openTasks' => $openTasks,
            'completedTasks' => $completedTasks,
            'totalTasks' => $totalTasks,
            'frogTasks' => $frogTasks,
            'overdueTasksCount' => $overdueTasksCount,
            'monthlyCompletedTasks' => $monthlyCompletedTasks,
            'monthlyLoggedMinutes' => $monthlyLoggedMinutes,
            'monthlyCompletedTrend' => $monthlyCompletedTrend,
            'monthlyHoursTrend' => $monthlyHoursTrend,

            // Aktionable Listen
            'overdueTasksList' => $overdueTasksList,
            'upcomingTasksList' => $upcomingTasksList,

            // Projekte
            'projectsWithProgress' => $projectsWithProgress,
            'recentlyCompletedWithProgress' => $recentlyCompletedWithProgress,

            // Story Points
            'totalStoryPoints' => $totalStoryPoints,
            'completedStoryPoints' => $completedStoryPoints,
            'openStoryPoints' => $openStoryPoints,
            'monthlyCreatedPoints' => $monthlyCreatedPoints,
            'monthlyCompletedPoints' => $monthlyCompletedPoints,
            'monthlyCreatedTasks' => $monthlyCreatedTasks,

            // Zeit
            'totalLoggedMinutes' => $totalLoggedMinutes,
            'billedMinutes' => $billedMinutes,
            'monthlyBilledMinutes' => $monthlyBilledMinutes,
            'unbilledMinutes' => $unbilledMinutes,
            'totalLoggedAmountCents' => $totalLoggedAmountCents,
            'billedAmountCents' => $billedAmountCents,
            'unbilledAmountCents' => $unbilledAmountCents,

            // Team
            'teamMembers' => $teamMembers,

            // Sidebar
            'todayCreatedTasks' => $todayCreatedTasks,
            'todayCompletedTasks' => $todayCompletedTasks,
            'recentActivities' => $recentActivities,
        ])->layout('platform::layouts.app');
    }

    private function buildProjectProgress(PlannerProject $project, $team, $startOfMonth, $endOfMonth): array
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

        $taskIds = $projectTasks->pluck('id')->toArray();
        $baseTimeEntries = OrganizationTimeEntry::query()
            ->where(function ($q) use ($project, $taskIds) {
                $q->where(function ($sq) use ($project) {
                    $sq->where('context_type', PlannerProject::class)
                       ->where('context_id', $project->id);
                });
                if (!empty($taskIds)) {
                    $q->orWhere(function ($sq) use ($taskIds) {
                        $sq->where('context_type', PlannerTask::class)
                           ->whereIn('context_id', $taskIds);
                    });
                }
            });

        $loggedMinutes = (int) (clone $baseTimeEntries)->sum('minutes');
        $monthlyMinutes = (int) (clone $baseTimeEntries)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('minutes');

        $storyPoints = $projectTasks->sum(fn($t) => $t->story_points?->points() ?? 0);

        return [
            'id' => $project->id,
            'name' => $project->name,
            'project_type' => $project->project_type?->value,
            'project_type_label' => $project->project_type?->label(),
            'color' => $project->color ?? null,
            'done' => (bool) $project->done,
            'done_at' => $project->done_at,
            'open_tasks' => $openTasks,
            'completed_tasks' => $completedTasks,
            'total_tasks' => $totalTasks,
            'progress_percent' => $progressPercent,
            'logged_minutes' => $loggedMinutes,
            'monthly_minutes' => $monthlyMinutes,
            'planned_minutes' => (int) ($project->planned_minutes ?? 0),
            'story_points' => $storyPoints,
        ];
    }

    private function calcTrend(int|float $current, int|float $previous): array
    {
        if ($previous <= 0) {
            return [
                'direction' => $current > 0 ? 'up' : null,
                'percent' => $current > 0 ? 100 : 0,
            ];
        }
        $diff = $current - $previous;
        $percent = (int) round(($diff / $previous) * 100);
        return [
            'direction' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : null),
            'percent' => abs($percent),
        ];
    }
}
