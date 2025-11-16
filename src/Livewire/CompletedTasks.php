<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProjectUser;
use Livewire\Attributes\On;

class CompletedTasks extends Component
{
    public $daysFilter = 30; // Standard: letzte 30 Tage

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

        // Erledigte Aufgaben abrufen:
        // 1. Private Aufgaben des Benutzers (user_id = userId, kein project_id)
        // 2. Aufgaben aus Projekten, in denen der Benutzer Mitglied ist (unabhängig vom Team)
        $completedTasks = PlannerTask::query()
            ->where('is_done', true)
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
            ->where(function ($q) use ($sinceDate) {
                // Nach done_at filtern, falls vorhanden, sonst updated_at
                $q->where(function ($q) use ($sinceDate) {
                    $q->whereNotNull('done_at')
                      ->where('done_at', '>=', $sinceDate);
                })
                ->orWhere(function ($q) use ($sinceDate) {
                    $q->whereNull('done_at')
                      ->where('updated_at', '>=', $sinceDate);
                });
            })
            ->with(['user', 'userInCharge', 'project', 'team'])
            ->orderByDesc('done_at') // Neueste zuerst (zuletzt erledigt)
            ->orderByDesc('updated_at') // Fallback für Tasks ohne done_at
            ->get();

        // Gruppierung nach Datum (heute, gestern, diese Woche, etc.)
        $groupedTasks = $completedTasks->groupBy(function ($task) {
            $date = $task->done_at ?? $task->updated_at;
            
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
        $totalPoints = $completedTasks->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1);

        return view('planner::livewire.completed-tasks', [
            'groupedTasks' => $groupedTasks,
            'totalCount' => $totalCount,
            'totalPoints' => $totalPoints,
            'daysFilter' => $this->daysFilter,
        ])->layout('platform::layouts.app');
    }
}

