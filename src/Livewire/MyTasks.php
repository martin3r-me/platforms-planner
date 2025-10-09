<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerTaskGroup;
use Livewire\Attributes\On;

class MyTasks extends Component
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
            'subject' => 'Meine Aufgaben',
            'description' => 'Übersicht aller persönlichen Aufgaben',
            'url' => route('planner.my-tasks'),
            'source' => 'planner.my-tasks',
            'recipients' => [],
            'meta' => [
                'view_type' => 'my_tasks',
                'user_id' => Auth::id(),
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $userId = $user->id;
        $startOfMonth = now()->startOfMonth();

        // === 1. INBOX ===
        $inboxTasks = PlannerTask::query()
            ->whereNull('task_group_id')
            ->where('is_done', false)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('project_id')
                      ->where('user_id', $userId); // private Aufgabe
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('project_id')
                      ->where('user_in_charge_id', $userId)
                      ->where(function ($subQ) {
                          $subQ->whereNotNull('project_slot_id') // zuständige Projektaufgabe im Project-Slot
                               ->orWhere(function ($slotQ) {
                                   $slotQ->whereNull('project_slot_id')
                                         ->whereNull('sprint_slot_id'); // oder ohne Slot-Zuordnung (Backlog)
                               });
                      });
                });
            })
            ->orderBy('order')
            ->get();

        $inbox = (object) [
            'id' => null,
            'label' => 'INBOX',
            'isInbox' => true,
            'tasks' => $inboxTasks,
            'open_count' => $inboxTasks->count(),
            'open_points' => $inboxTasks->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1),
        ];

        // === 2. GRUPPEN ===
        $grouped = PlannerTaskGroup::with(['tasks' => function ($q) use ($userId) {
            $q->where('is_done', false)
              ->where(function ($q) use ($userId) {
                  $q->where(function ($q) use ($userId) {
                      $q->whereNull('project_id')
                        ->where('user_id', $userId);
                  })->orWhere(function ($q) use ($userId) {
                      $q->whereNotNull('project_id')
                        ->where('user_in_charge_id', $userId)
                        ->where(function ($subQ) {
                            $subQ->whereNotNull('project_slot_id') // zuständige Projektaufgabe im Project-Slot
                                 ->orWhere(function ($slotQ) {
                                     $slotQ->whereNull('project_slot_id')
                                           ->whereNull('sprint_slot_id'); // oder ohne Slot-Zuordnung (Backlog)
                                 });
                        });
                  });
              })
              ->orderBy('order');
        }])
        ->where('user_id', $userId)
        ->orderBy('order')
        ->get()
        ->map(fn ($group) => (object) [
            'id' => $group->id,
            'label' => $group->label,
            'isInbox' => false,
            'tasks' => $group->tasks,
            'open_count' => $group->tasks->count(),
            'open_points' => $group->tasks->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1),
        ]);

        // === 3. ERLEDIGT ===
        $doneTasks = PlannerTask::query()
            ->where('is_done', true)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('project_id')
                      ->where('user_id', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('project_id')
                      ->where('user_in_charge_id', $userId)
                      ->where(function ($subQ) {
                          $subQ->whereNotNull('project_slot_id') // zuständige Projektaufgabe im Project-Slot
                               ->orWhere(function ($slotQ) {
                                   $slotQ->whereNull('project_slot_id')
                                         ->whereNull('sprint_slot_id'); // oder ohne Slot-Zuordnung (Backlog)
                               });
                      });
                });
            })
            ->orderByDesc('done_at')
            ->get();

        $completedGroup = (object) [
            'id' => 'done',
            'label' => 'Erledigt',
            'isInbox' => false,
            'isDoneGroup' => true,
            'tasks' => $doneTasks,
        ];

        // === 4. KOMPLETTE GRUPPENLISTE ===
        $groups = collect([$inbox])->concat($grouped)->push($completedGroup);

        // === 5. PERFORMANCE-BERECHNUNG ===
        $createdPoints = PlannerTask::query()
            ->withTrashed()
            ->whereDate('created_at', '>=', $startOfMonth)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('project_id')
                      ->where('user_id', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('project_id')
                      ->where('user_in_charge_id', $userId)
                      ->whereNotNull('sprint_slot_id');
                });
            })
            ->get()
            ->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1);

        $donePoints = PlannerTask::query()
            ->withTrashed()
            ->whereDate('done_at', '>=', $startOfMonth)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('project_id')
                      ->where('user_id', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('project_id')
                      ->where('user_in_charge_id', $userId)
                      ->whereNotNull('sprint_slot_id');
                });
            })
            ->get()
            ->sum(fn ($task) => $task->story_points instanceof \App\Enums\StoryPoints ? $task->story_points->points() : 1);

        $monthlyPerformanceScore = $createdPoints > 0 ? round($donePoints / $createdPoints, 2) : null;

        return view('planner::livewire.my-tasks', [
            'groups' => $groups,
            'monthlyPerformanceScore' => $monthlyPerformanceScore,
            'createdPoints' => $createdPoints,
            'donePoints' => $donePoints,
        ])->layout('platform::layouts.app');
    }

    public function createTaskGroup()
    {
        $user = Auth::user();

        $newTaskGroup = new PlannerTaskGroup();
        $newTaskGroup->label = "Neue Gruppe";
        $newTaskGroup->user_id = $user->id;
        $newTaskGroup->team_id = $user->currentTeam->id;
        $newTaskGroup->order = PlannerTaskGroup::where('user_id', $user->id)->max('order') + 1;
        $newTaskGroup->save();
    }

    public function createTask($taskGroupId = null)
    {
        $user = Auth::user();
        
        $lowestOrder = PlannerTask::where('user_id', Auth::id())
            ->where('team_id', Auth::user()->currentTeam->id)
            ->min('order') ?? 0;

        $order = $lowestOrder - 1;

        $newTask = PlannerTask::create([
            'user_id' => Auth::id(),
            'user_in_charge_id' => $user->id,
            'project_id' => null,
            'task_group_id' => $taskGroupId,
            'title' => 'Neue Aufgabe',
            'description' => null,
            'due_date' => null,
            'priority' => null,
            'story_points' => null,
            'team_id' => Auth::user()->currentTeam->id,
            'order' => $order,
        ]);
    }

    public function toggleDone($taskId)
    {
        $task = PlannerTask::findOrFail($taskId);

        if ($task->user_id !== auth()->id()) {
            abort(403);
        }

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

                $task->order = $item['order'];
                $task->task_group_id = $taskGroupId;
                $task->save();
            }
        }
    }

    public function updateTaskGroupOrder($groups)
    {
        foreach ($groups as $taskGroup) {
            $taskGroupDb = PlannerTaskGroup::find($taskGroup['value']);
            if ($taskGroupDb) {
                $taskGroupDb->order = $taskGroup['order'];
                $taskGroupDb->save();
            }
        }
    }
}
