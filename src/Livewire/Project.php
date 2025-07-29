<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerSprintSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\StoryPoints;
use Livewire\Attributes\On; 

class Project extends Component
{

    public $project;

    #[On('taskUpdated')]
    public function tasksUpdated()
    {
        // Optional: neu rendern bei Event
    }

    #[On('sprintSlotUpdated')]
    public function sprintSlotUpdated()
    {
        // Optional: neu rendern bei Event
    }

    public function mount(PlannerProject $plannerProject)
    {
        $this->project = $plannerProject;
    }

    public function render()
    {   
        $user = Auth::user();
        $startOfMonth = now()->startOfMonth();

        $groups = collect();

        
        // === 1. BACKLOG ===
        $backlogTasks = PlannerTask::where('user_id', Auth::user()->id)
            ->where('project_id', $this->project->id)
            ->whereNull('sprint_slot_id')
            ->where('is_done', false)
            ->orderBy('sprint_slot_order')
            ->get();

        $backlog = (object) [
            'id' => null,
            'label' => 'Backlog',
            'isBacklog' => true,
            'tasks' => $backlogTasks,
            'open_count' => $backlogTasks->count(),
            'open_points' => $backlogTasks->sum(fn ($task) => $task->story_points instanceof StoryPoints ? $task->story_points->points() : 1),
        ];

        // === 2. Sprint-Slots ===
        $slots = PlannerSprintSlot::with(['tasks' => fn ($q) => $q->where('is_done', false)->orderBy('sprint_slot_order')])
            ->whereHas('sprint', fn ($q) => $q->where('project_id', $this->project->id))
            ->orderBy('order')
            ->get()
            ->map(fn ($slot) => (object) [
                'id' => $slot->id,
                'label' => $slot->name,
                'isBacklog' => false, // <- wichtig
                'tasks' => $slot->tasks,
                'open_count' => $slot->tasks->count(),
                'open_points' => $slot->tasks->sum(fn ($task) => $task->story_points instanceof StoryPoints ? $task->story_points->points() : 1),
            ]);

        // === 3. Erledigte Aufgaben ===
        $doneTasks = PlannerTask::where('user_id', Auth::user()->id)
            ->where('project_id', $this->project->id)
            ->where('is_done', true)
            ->orderByDesc('done_at')
            ->get();

        $completedGroup = (object) [
            'id' => 'done',
            'label' => 'Erledigt',
            'isDoneGroup' => true,
            'isBacklog' => false,
            'tasks' => $doneTasks,
        ];

        $groups = collect([$backlog])->concat($slots)->push($completedGroup);


        return view('planner::livewire.project', [
            'groups' => $groups,
        ])->layout('platform::layouts.app');
    }

    public function createTask($sprintSlotId = null)
    {
        $lowestOrder = PlannerTask::where('user_id', Auth::id())
            ->where('team_id', Auth::user()->currentTeam->id)
            ->min('order') ?? 0;

        $order = $lowestOrder - 1;

        $newTask = PlannerTask::create([
            'user_id' => Auth::id(),
            'project_id' => $this->project->id,
            'sprint_slot_id' => $sprintSlotId,
            'title' => 'Neue Aufgabe',
            'description' => null,
            'due_date' => null,
            'priority' => null,
            'story_points' => null,
            'team_id' => Auth::user()->currentTeam->id,
            'order' => $order,
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

                $task->sprint_slot_order = $item['order'];
                $task->sprint_slot_id = $taskGroupId;
                $task->save();
            }
        }
    }

    public function updateTaskGroupOrder($groups)
    {
        foreach ($groups as $taskGroup) {
            $taskGroupDb = PlannerSprintSlot::find($taskGroup['value']);
            if ($taskGroupDb) {
                $taskGroupDb->order = $taskGroup['order'];
                $taskGroupDb->save();
            }
        }
    }
}