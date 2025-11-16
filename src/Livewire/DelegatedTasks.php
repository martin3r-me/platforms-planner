<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerDelegatedTaskGroup;
use Livewire\Attributes\On;

class DelegatedTasks extends Component
{

    #[On('updateDashboard')] 
    public function updateDashboard()
    {
        
    }

    #[On('taskUpdated')]
    public function tasksUpdated()
    {
        // Optional: neu rendern bei Event
    }

    #[On('taskGroupUpdated')]
    public function taskGroupUpdated()
    {
        // Optional: neu rendern bei Event
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => 'Platform\Planner\Models\PlannerTask',
            'modelId' => null,
            'subject' => 'Delegierte Aufgaben',
            'description' => 'Übersicht aller Aufgaben, die ich erstellt habe, aber an andere delegiert wurden',
            'url' => route('planner.delegated-tasks'),
            'source' => 'planner.delegated-tasks',
            'recipients' => [],
            'meta' => [
                'view_type' => 'delegated_tasks',
                'user_id' => Auth::id(),
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $userId = $user->id;
        $startOfMonth = now()->startOfMonth();

        // === 0. FÄLLIGE/ÜBERFÄLLIGE DELEGIERTE AUFGABEN ===
        $tomorrow = now()->addDay()->endOfDay();
        
        $dueTasks = PlannerTask::query()
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where(function ($q) use ($tomorrow) {
                // Überfällig (in der Vergangenheit), heute oder morgen fällig
                $q->where('due_date', '<=', $tomorrow);
            })
            ->where('user_id', $userId) // Vom aktuellen User erstellt
            ->whereNotNull('user_in_charge_id') // Hat einen Verantwortlichen
            ->where('user_in_charge_id', '!=', $userId) // Aber nicht der aktuelle User
            ->orderBy('due_date')
            ->get();

        $dueGroup = (object) [
            'id' => 'due',
            'label' => 'Fällig',
            'isInbox' => false,
            'isBacklog' => false,
            'isDueGroup' => true,
            'tasks' => $dueTasks,
            'open_count' => $dueTasks->count(),
            'open_points' => $dueTasks->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1),
        ];

        // === 1. INBOX ===
        $inboxTasks = PlannerTask::query()
            ->whereNull('delegated_group_id')
            ->where('is_done', false)
            ->where('user_id', $userId) // Vom aktuellen User erstellt
            ->whereNotNull('user_in_charge_id') // Hat einen Verantwortlichen
            ->where('user_in_charge_id', '!=', $userId) // Aber nicht der aktuelle User
            ->orderBy('delegated_group_order')
            ->get();

        $inbox = (object) [
            'id' => null,
            'label' => 'INBOX',
            'isInbox' => true,
            'isBacklog' => true,
            'tasks' => $inboxTasks,
            'open_count' => $inboxTasks->count(),
            'open_points' => $inboxTasks->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1),
        ];

        // === 2. GRUPPEN ===
        // Für delegierte Aufgaben: Separate DelegatedTaskGroups verwenden
        // WICHTIG: Alle Gruppen anzeigen, auch leere, damit User Aufgaben hinzufügen kann
        $grouped = PlannerDelegatedTaskGroup::with(['tasks' => function ($q) use ($userId) {
            $q->where('is_done', false)
              ->where('user_id', $userId) // Vom aktuellen User erstellt
              ->whereNotNull('user_in_charge_id') // Hat einen Verantwortlichen
              ->where('user_in_charge_id', '!=', $userId) // Aber nicht der aktuelle User
              ->orderBy('delegated_group_order');
        }])
        ->where('user_id', $userId)
        ->orderBy('order')
        ->get()
        ->map(fn ($group) => (object) [
            'id' => $group->id,
            'label' => $group->label,
            'isInbox' => false,
            'tasks' => $group->tasks, // Collection (kann leer sein)
            'open_count' => $group->tasks->count(),
            'open_points' => $group->tasks->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1),
        ]);

        // === 3. ERLEDIGT ===
        $doneTasks = PlannerTask::query()
            ->where('is_done', true)
            ->where('user_id', $userId) // Vom aktuellen User erstellt
            ->whereNotNull('user_in_charge_id') // Hat einen Verantwortlichen
            ->where('user_in_charge_id', '!=', $userId) // Aber nicht der aktuelle User
            ->orderByDesc('done_at') // Neueste zuerst (zuletzt erledigt)
            ->orderByDesc('updated_at') // Fallback für Tasks ohne done_at
            ->get();

        $completedGroup = (object) [
            'id' => 'done',
            'label' => 'Erledigt',
            'isInbox' => false,
            'isDoneGroup' => true,
            'tasks' => $doneTasks,
        ];

        // === 4. KOMPLETTE GRUPPENLISTE ===
        // Fällige Aufgaben zuerst, dann Inbox, dann Gruppen, dann Erledigt
        $groups = collect([$dueGroup, $inbox])->concat($grouped)->push($completedGroup);

        // === 5. PERFORMANCE-BERECHNUNG ===
        $createdPoints = PlannerTask::query()
            ->withTrashed()
            ->whereDate('created_at', '>=', $startOfMonth)
            ->where('user_id', $userId) // Vom aktuellen User erstellt
            ->whereNotNull('user_in_charge_id') // Hat einen Verantwortlichen
            ->where('user_in_charge_id', '!=', $userId) // Aber nicht der aktuelle User
            ->get()
            ->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1);

        $donePoints = PlannerTask::query()
            ->withTrashed()
            ->whereDate('done_at', '>=', $startOfMonth)
            ->where('user_id', $userId) // Vom aktuellen User erstellt
            ->whereNotNull('user_in_charge_id') // Hat einen Verantwortlichen
            ->where('user_in_charge_id', '!=', $userId) // Aber nicht der aktuelle User
            ->get()
            ->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1);

        $monthlyPerformanceScore = $createdPoints > 0 ? round($donePoints / $createdPoints, 2) : null;

        return view('planner::livewire.delegated-tasks', [
            'groups' => $groups,
            'monthlyPerformanceScore' => $monthlyPerformanceScore,
            'createdPoints' => $createdPoints,
            'donePoints' => $donePoints,
        ])->layout('platform::layouts.app');
    }

    public function createTaskGroup()
    {
        $user = Auth::user();

        $newTaskGroup = new PlannerDelegatedTaskGroup();
        $newTaskGroup->label = "Neue Gruppe";
        $newTaskGroup->user_id = $user->id;
        $newTaskGroup->team_id = $user->currentTeam->id;
        $newTaskGroup->order = PlannerDelegatedTaskGroup::where('user_id', $user->id)->max('order') + 1;
        $newTaskGroup->save();
    }

    public function createTask($taskGroupId = null)
    {
        $user = Auth::user();
        
        // Konvertiere 0 zu null für INBOX
        $taskGroupId = ($taskGroupId === 0 || $taskGroupId === '0') ? null : $taskGroupId;

        // Für delegierte Aufgaben: delegated_group_order verwenden
        // Wenn in Gruppe: Order innerhalb der Gruppe
        // Wenn in Inbox: Order für delegierte Inbox
        if ($taskGroupId) {
            // In Gruppe: niedrigste Order in dieser Gruppe
            $lowestOrder = PlannerTask::where('user_id', Auth::id())
                ->where('delegated_group_id', $taskGroupId)
                ->min('delegated_group_order') ?? 0;
        } else {
            // In Inbox: niedrigste Order in delegierter Inbox
            $lowestOrder = PlannerTask::where('user_id', Auth::id())
                ->whereNull('delegated_group_id')
                ->whereNotNull('user_in_charge_id')
                ->where('user_in_charge_id', '!=', Auth::id())
                ->min('delegated_group_order') ?? 0;
        }

        $order = $lowestOrder - 1;

        $newTask = PlannerTask::create([
            'user_id' => Auth::id(),
            'user_in_charge_id' => null, // Wird später gesetzt, wenn delegiert
            'project_id' => null,
            'delegated_group_id' => $taskGroupId,
            'delegated_group_order' => $order,
            'title' => 'Neue Aufgabe',
            'description' => null,
            'due_date' => null,
            'priority' => null,
            'story_points' => null,
            'team_id' => Auth::user()->currentTeam->id,
            'order' => 0, // Standard order für normale Aufgaben (wird in "Meine Aufgaben" verwendet)
        ]);
    }

    public function toggleDone($taskId)
    {
        $task = PlannerTask::findOrFail($taskId);

        if ($task->user_id !== auth()->id()) {
            abort(403);
        }

        // done_at wird automatisch vom PlannerTaskObserver gesetzt
        $task->update([
            'is_done' => ! $task->is_done,
        ]);
    }

    public function updateTaskOrder($groups)
    {
        foreach ($groups as $group) {
            $taskGroupId = ($group['value'] === 'null' || (int) $group['value'] === 0)
                ? null
                : (int) $group['value'];

            foreach ($group['items'] as $item) {
                $task = PlannerTask::find($item['value']);

                if (! $task) {
                    continue;
                }

                $task->delegated_group_order = $item['order'];
                $task->delegated_group_id = $taskGroupId;
                $task->save();
            }
        }
    }

    public function updateTaskGroupOrder($groups)
    {
        foreach ($groups as $taskGroup) {
            $taskGroupDb = PlannerDelegatedTaskGroup::find($taskGroup['value']);
            if ($taskGroupDb) {
                $taskGroupDb->order = $taskGroup['order'];
                $taskGroupDb->save();
            }
        }
    }
}

