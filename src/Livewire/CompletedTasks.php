<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Enums\TaskStoryPoints;
use Livewire\Attributes\On;

class CompletedTasks extends Component
{
    public $daysFilter = 30; // Standard: letzte 30 Tage
    public $userFilter = null; // Filter nach Person (user_in_charge_id)

    #[On('updateDashboard')] 
    public function updateDashboard()
    {
        
    }

    #[On('taskUpdated')]
    public function tasksUpdated()
    {
        // Optional: neu rendern bei Event
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => 'Platform\Planner\Models\PlannerTask',
            'modelId' => null,
            'subject' => 'Erledigte Aufgaben',
            'description' => 'Übersicht aller kürzlich erledigten Aufgaben aus meinem Umfeld',
            'url' => route('planner.completed-tasks'),
            'source' => 'planner.completed-tasks',
            'recipients' => [],
            'meta' => [
                'view_type' => 'completed_tasks',
                'user_id' => Auth::id(),
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $userId = $user->id;

        // Alle Projekt-IDs, in denen der Benutzer Mitglied ist (team-übergreifend)
        $projectIds = PlannerProjectUser::where('user_id', $userId)
            ->pluck('project_id')
            ->toArray();

        // Zeitfilter: letzte X Tage
        $sinceDate = now()->subDays($this->daysFilter);

        // Basis-Query für alle Aufgaben im Zeitraum (für Personenfilter)
        $baseQuery = PlannerTask::query()
            ->where('is_done', true)
            ->whereNotNull('done_at') // Nur Aufgaben mit done_at
            ->where(function ($q) use ($userId, $projectIds) {
                // Private Aufgaben des Benutzers
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('project_id')
                      ->where('user_id', $userId);
                })
                // ODER Aufgaben aus Projekten, in denen der Benutzer Mitglied ist
                ->orWhere(function ($q) use ($projectIds) {
                    $q->whereNotNull('project_id')
                      ->whereIn('project_id', $projectIds);
                });
            })
            ->where('done_at', '>=', $sinceDate); // Nach done_at filtern

        // Alle verfügbaren Personen für Filter (aus allen Aufgaben im Zeitraum, unabhängig vom Personenfilter)
        $allTasksForUsers = (clone $baseQuery)
            ->with('userInCharge')
            ->get();
        
        $availableUsers = $allTasksForUsers
            ->pluck('userInCharge')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        // Erledigte Aufgaben abrufen (mit Personenfilter)
        $completedTasks = (clone $baseQuery)
            ->when($this->userFilter, function ($q) {
                $q->where('user_in_charge_id', $this->userFilter);
            })
            ->with(['user', 'userInCharge', 'project', 'team'])
            ->orderByDesc('done_at') // Neueste zuerst (zuletzt erledigt)
            ->get();

        // Gruppierung nach Datum (heute, gestern, diese Woche, etc.)
        // done_at ist immer vorhanden, da wir nur Tasks mit done_at laden
        $groupedTasks = $completedTasks->groupBy(function ($task) {
            $date = $task->done_at;
            
            if ($date->isToday()) {
                return 'Heute';
            } elseif ($date->isYesterday()) {
                return 'Gestern';
            } elseif ($date->isCurrentWeek()) {
                return 'Diese Woche';
            } elseif ($date->isCurrentMonth()) {
                return 'Dieser Monat';
            } else {
                return $date->format('F Y'); // z.B. "Januar 2025"
            }
        });

        // Statistiken
        $totalCount = $completedTasks->count();
        $totalPoints = $completedTasks->sum(fn ($task) => $task->story_points instanceof TaskStoryPoints ? $task->story_points->points() : ($task->story_points ? 1 : 0));

        return view('planner::livewire.completed-tasks', [
            'groupedTasks' => $groupedTasks,
            'totalCount' => $totalCount,
            'totalPoints' => $totalPoints,
            'daysFilter' => $this->daysFilter,
            'userFilter' => $this->userFilter,
            'availableUsers' => $availableUsers,
        ])->layout('platform::layouts.app');
    }
}

