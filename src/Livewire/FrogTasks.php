<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Livewire\Concerns\QuickTogglesDone;
use Livewire\Attributes\On;

class FrogTasks extends Component
{
    use QuickTogglesDone;

    public $userFilter = null;
    public $projectFilter = null;
    public $priorityFilter = null;
    public $overdueOnly = false;
    public $groupBy = 'project'; // project, person, priority

    #[On('updateDashboard')]
    public function updateDashboard()
    {

    }

    #[On('taskUpdated')]
    public function tasksUpdated()
    {

    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => 'Platform\Planner\Models\PlannerTask',
            'modelId' => null,
            'subject' => 'Frösche',
            'description' => 'Übersicht aller Frog-Tasks im Team',
            'url' => route('planner.frog-tasks'),
            'source' => 'planner.frog-tasks',
            'recipients' => [],
            'meta' => [
                'view_type' => 'frog_tasks',
                'user_id' => Auth::id(),
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $userId = $user->id;

        // Alle Projekt-IDs, in denen der Benutzer Mitglied ist
        $projectIds = PlannerProjectUser::where('user_id', $userId)
            ->pluck('project_id')
            ->toArray();

        // Basis-Query: alle Frog-Tasks (nicht erledigt) aus Projekten des Users
        $baseQuery = PlannerTask::query()
            ->where('is_frog', true)
            ->where('is_done', false)
            ->where(function ($q) use ($userId, $projectIds) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('project_id')
                      ->where('user_id', $userId);
                })
                ->orWhere(function ($q) use ($projectIds) {
                    $q->whereNotNull('project_id')
                      ->whereIn('project_id', $projectIds);
                });
            });

        // Verfügbare Personen für Filter
        $allTasksForUsers = (clone $baseQuery)
            ->with('userInCharge')
            ->get();

        $availableUsers = $allTasksForUsers
            ->pluck('userInCharge')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        // Verfügbare Projekte für Filter
        $availableProjects = (clone $baseQuery)
            ->with('project')
            ->get()
            ->pluck('project')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        // Frog-Tasks mit Filtern
        $frogTasks = (clone $baseQuery)
            ->when($this->userFilter, function ($q) {
                $q->where('user_in_charge_id', $this->userFilter);
            })
            ->when($this->projectFilter, function ($q) {
                $q->where('project_id', $this->projectFilter);
            })
            ->when($this->priorityFilter, function ($q) {
                $q->where('priority', $this->priorityFilter);
            })
            ->when($this->overdueOnly, function ($q) {
                $q->whereNotNull('due_date')
                  ->where('due_date', '<', now()->startOfDay());
            })
            ->with(['user', 'userInCharge', 'project', 'team'])
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        // Gruppierung
        $groupedTasks = $frogTasks->groupBy(function ($task) {
            return match($this->groupBy) {
                'person' => $task->userInCharge
                    ? ($task->userInCharge->fullname ?? $task->userInCharge->name)
                    : 'Nicht zugewiesen',
                'priority' => $task->priority?->label() ?? 'Ohne Priorität',
                default => $task->project?->name ?? 'Ohne Projekt',
            };
        });

        // Statistiken (ungefiltert für KPIs)
        $allFrogs = (clone $baseQuery)->get();
        $totalCount = $allFrogs->count();
        $forcedFrogCount = $allFrogs->where('is_forced_frog', true)->count();
        $totalPoints = $allFrogs->sum(fn ($task) => $task->story_points instanceof TaskStoryPoints ? $task->story_points->points() : 0);
        $overdueCount = $allFrogs->filter(fn($t) => $t->due_date && $t->due_date->isPast())->count();
        $withoutDueDate = $allFrogs->filter(fn($t) => !$t->due_date)->count();
        $highPriorityCount = $allFrogs->filter(fn($t) => $t->priority?->value === 'high')->count();
        $filteredCount = $frogTasks->count();

        return view('planner::livewire.frog-tasks', [
            'groupedTasks' => $groupedTasks,
            'totalCount' => $totalCount,
            'filteredCount' => $filteredCount,
            'forcedFrogCount' => $forcedFrogCount,
            'totalPoints' => $totalPoints,
            'overdueCount' => $overdueCount,
            'withoutDueDate' => $withoutDueDate,
            'highPriorityCount' => $highPriorityCount,
            'availableUsers' => $availableUsers,
            'availableProjects' => $availableProjects,
        ])->layout('platform::layouts.app');
    }
}
