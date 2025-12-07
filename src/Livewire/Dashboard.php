<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Organization\Models\OrganizationTimeEntry;
use Carbon\Carbon;

class Dashboard extends Component
{
    public $perspective = 'team'; // legacy, nicht mehr umschaltbar

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

        $baseTimeEntries = OrganizationTimeEntry::query()
            ->where('team_id', $team->id);

        $totalLoggedMinutes = (clone $baseTimeEntries)->sum('minutes');
        $totalLoggedAmountCents = (int) (clone $baseTimeEntries)->sum('amount_cents');

        $billedMinutes = (clone $baseTimeEntries)
            ->where('is_billed', true)
            ->sum('minutes');
        $billedAmountCents = (int) (clone $baseTimeEntries)
            ->where('is_billed', true)
            ->sum('amount_cents');

        $monthlyLoggedMinutes = (clone $baseTimeEntries)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('minutes');

        $monthlyBilledMinutes = (clone $baseTimeEntries)
            ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where('is_billed', true)
            ->sum('minutes');

        $unbilledMinutes = max(0, $totalLoggedMinutes - $billedMinutes);
        $unbilledAmountCents = max(0, $totalLoggedAmountCents - $billedAmountCents);

        // === PROJEKTE (nur Team-Projekte) ===
        $projects = PlannerProject::where('team_id', $team->id)->orderBy('name')->get();
        $activeProjects = $projects->filter(function($project) {
            return $project->is_active === null || $project->is_active === true;
        })->count();
        $totalProjects = $projects->count();

        {
            // === TEAM-AUFGABEN ===
            $teamTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id') // Project-Slot Aufgaben
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id'); // oder ohne Slot-Zuordnung (Backlog)
                      });
                })
                ->get();

            $openTasks = $teamTasks->where('is_done', false)->count();
            $completedTasks = $teamTasks->where('is_done', true)->count();
            $totalTasks = $teamTasks->count();
            $frogTasks = $teamTasks->where('is_frog', true)->count();
            $overdueTasks = $teamTasks->where('is_done', false)
                ->filter(fn($task) => $task->due_date && $task->due_date->isPast())
                ->count();

            // === TEAM STORY POINTS ===
            $totalStoryPoints = $teamTasks->sum(fn($task) => $task->story_points?->points() ?? 0);
            $completedStoryPoints = $teamTasks->where('is_done', true)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);
            $openStoryPoints = $teamTasks->where('is_done', false)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            // === TEAM MONATLICHE PERFORMANCE ===
            $monthlyCreatedTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('created_at', '>=', $startOfMonth)
                ->count();

            $monthlyCompletedTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('done_at', '>=', $startOfMonth)
                ->count();

            $monthlyCreatedPoints = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('created_at', '>=', $startOfMonth)
                ->get()
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            $monthlyCompletedPoints = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('done_at', '>=', $startOfMonth)
                ->get()
                ->sum(fn($task) => $task->story_points?->points() ?? 0);
        }

        // === TEAM-MITGLIEDER-ÜBERSICHT ===
        $teamMembers = $team->users()->get()->map(function ($member) use ($team, $startOfMonth, $endOfMonth) {
            // Alle Aufgaben des Team-Mitglieds (private + zuständige Projektaufgaben)
            $memberTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) use ($member) {
                    $q->where(function ($q) use ($member) {
                        $q->whereNull('project_id')
                          ->where('user_id', $member->id); // private Aufgaben
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
            $totalStoryPoints = $memberTasks->sum(fn($task) => $task->story_points?->points() ?? 0);
            $completedStoryPoints = $memberTasks->filter(fn($t) => (bool)$t->is_done)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);
            $openStoryPoints = $memberTasks->filter(fn($t) => !$t->is_done)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            // Zeiten für diesen Nutzer berechnen - Gesamt
            $totalMinutes = (int) OrganizationTimeEntry::query()
                ->where('team_id', $team->id)
                ->where('user_id', $member->id)
                ->sum('minutes');
            
            // Zeiten des laufenden Monats
            $monthlyMinutes = (int) OrganizationTimeEntry::query()
                ->where('team_id', $team->id)
                ->where('user_id', $member->id)
                ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->sum('minutes');
            
            $billedMinutes = (int) OrganizationTimeEntry::query()
                ->where('team_id', $team->id)
                ->where('user_id', $member->id)
                ->where('is_billed', true)
                ->sum('minutes');

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'profile_photo_url' => $member->profile_photo_url,
                'open_tasks' => $openTasks,
                'completed_tasks' => $completedTasks,
                'total_tasks' => $totalTasks,
                'total_story_points' => $totalStoryPoints,
                'completed_story_points' => $completedStoryPoints,
                'open_story_points' => $openStoryPoints,
                // Für Tailwind-Komponente x-ui-team-members-list (expects tasks/points)
                'tasks' => $totalTasks,
                'points' => $totalStoryPoints,
                'total_minutes' => $totalMinutes,
                'monthly_minutes' => $monthlyMinutes,
                'billed_minutes' => $billedMinutes,
                'unbilled_minutes' => max(0, $totalMinutes - $billedMinutes),
            ];
        })->sortByDesc('open_tasks');

        // === PROJEKT-ÜBERSICHT (nur aktive Projekte) ===
        $perspective = 'team';
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        $activeProjectsList = $projects->filter(function($project) {
            return $project->is_active === null || $project->is_active === true;
        })
        ->map(function ($project) use ($user, $perspective, $team, $startOfMonth, $endOfMonth) {
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
            }
            
            // Zeiten für dieses Projekt berechnen (Root-Context) - Gesamt
            $baseTimeEntries = OrganizationTimeEntry::query()
                ->where('root_context_type', \Platform\Planner\Models\PlannerProject::class)
                ->where('root_context_id', $project->id);

            $totalMinutes = (int) (clone $baseTimeEntries)->sum('minutes');
            
            // Zeiten des laufenden Monats
            $monthlyMinutes = (int) (clone $baseTimeEntries)
                ->whereBetween('work_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->sum('minutes');
            
            $billedMinutes = (int) (clone $baseTimeEntries)
                ->where('is_billed', true)
                ->sum('minutes');
            
            return [
                'id' => $project->id,
                'name' => $project->name,
                'open_tasks' => $projectTasks->filter(fn($t) => !$t->is_done)->count(),
                'total_tasks' => $projectTasks->count(),
                'story_points' => $projectTasks->sum(fn($task) => $task->story_points?->points() ?? 0),
                // Für Tailwind-Komponente x-ui-project-list (expects tasks/points)
                'tasks' => $projectTasks->count(),
                'points' => $projectTasks->sum(fn($task) => $task->story_points?->points() ?? 0),
                'total_minutes' => $totalMinutes,
                'monthly_minutes' => $monthlyMinutes,
                'billed_minutes' => $billedMinutes,
                'unbilled_minutes' => max(0, $totalMinutes - $billedMinutes),
            ];
        })
        ->sortByDesc('open_tasks')
        ->take(5);

        // Additional properties for sidebar
        $todayCreatedTasks = PlannerTask::where('team_id', $team->id)
            ->whereDate('created_at', today())
            ->count();
            
        $todayCompletedTasks = PlannerTask::where('team_id', $team->id)
            ->whereDate('done_at', today())
            ->count();

        return view('planner::livewire.dashboard', [
            'currentDate' => now()->format('d.m.Y'),
            'currentDay' => now()->format('l'),
            'perspective' => $this->perspective,
            'activeProjects' => $activeProjects,
            'totalProjects' => $totalProjects,
            'openTasks' => $openTasks,
            'completedTasks' => $completedTasks,
            'totalTasks' => $totalTasks,
            'frogTasks' => $frogTasks,
            'overdueTasks' => $overdueTasks,
            'totalStoryPoints' => $totalStoryPoints,
            'completedStoryPoints' => $completedStoryPoints,
            'openStoryPoints' => $openStoryPoints,
            'monthlyCreatedTasks' => $monthlyCreatedTasks,
            'monthlyCompletedTasks' => $monthlyCompletedTasks,
            'monthlyCreatedPoints' => $monthlyCreatedPoints,
            'monthlyCompletedPoints' => $monthlyCompletedPoints,
            'teamMembers' => $teamMembers,
            'activeProjectsList' => $activeProjectsList,
            'todayCreatedTasks' => $todayCreatedTasks,
            'todayCompletedTasks' => $todayCompletedTasks,
            'totalLoggedMinutes' => $totalLoggedMinutes,
            'monthlyLoggedMinutes' => $monthlyLoggedMinutes,
            'billedMinutes' => $billedMinutes,
            'monthlyBilledMinutes' => $monthlyBilledMinutes,
            'unbilledMinutes' => $unbilledMinutes,
            'totalLoggedAmountCents' => $totalLoggedAmountCents,
            'billedAmountCents' => $billedAmountCents,
            'unbilledAmountCents' => $unbilledAmountCents,
        ])->layout('platform::layouts.app');
    }
}