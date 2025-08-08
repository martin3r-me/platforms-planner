<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerSprintSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\StoryPoints;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

class Project extends Component
{
    public PlannerProject $project;
    public $sprint; // Aktueller Sprint des Projekts

    #[On('updateProject')] 
    public function updateProject()
    {
        
    }

    #[On('sprintSlotUpdated')]
    public function sprintSlotUpdated()
    {
        // Optional: neu rendern bei Event
    }

    public function mount(PlannerProject $plannerProject)
    {

        $this->project = $plannerProject;

        // Aktuellen Sprint laden (derzeit: erster, später ggf. mit Filter)
        $this->sprint = $plannerProject->sprints()->first();

    }

    public function render()
    {
        $user = Auth::user();

        // === 1. BACKLOG ===
        $backlogTasks = PlannerTask::where('user_id', $user->id)
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
            'open_points' => $backlogTasks->sum(
                fn ($task) => $task->story_points instanceof StoryPoints
                    ? $task->story_points->points()
                    : 1
            ),
        ];

        // === 2. SPRINT-SLOTS (nur für aktuellen Sprint) ===
        $slots = collect();
        if ($this->sprint) {
            $slots = PlannerSprintSlot::with(['tasks' => function ($q) {
                    $q->where('is_done', false)->orderBy('sprint_slot_order');
                }])
                ->where('sprint_id', $this->sprint->id)
                ->orderBy('order')
                ->get()
                ->map(fn ($slot) => (object) [
                    'id' => $slot->id,
                    'label' => $slot->name,
                    'isBacklog' => false,
                    'tasks' => $slot->tasks,
                    'open_count' => $slot->tasks->count(),
                    'open_points' => $slot->tasks->sum(
                        fn ($task) => $task->story_points instanceof StoryPoints
                            ? $task->story_points->points()
                            : 1
                    ),
                ]);
        }

        // === 3. ERLEDIGTE AUFGABEN ===
        $doneTasks = PlannerTask::where('user_id', $user->id)
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

        // === BOARD-GRUPPEN ZUSAMMENSTELLEN ===
        $groups = collect([$backlog])->concat($slots)->push($completedGroup);

        return view('planner::livewire.project', [
            'groups' => $groups,
        ])->layout('platform::layouts.app');
    }

    /**
     * Legt einen neuen Sprint-Slot im aktuellen Sprint an und lädt State neu.
     */
    public function createSprintSlot()
    {
        if (! $this->sprint) {
            // Kein aktiver Sprint vorhanden
            return;
        }

        $maxOrder = $this->sprint->sprintSlots()->max('order') ?? 0;

        $this->sprint->sprintSlots()->create([
            'name' => 'Neuer Slot',
            'order' => $maxOrder + 1,
        ]);

        // Slots/State neu laden (Livewire 3 Way)
        $this->mount($this->project);
    }

    /**
     * Legt eine neue Aufgabe an, optional direkt in einem Slot.
     */
    public function createTask($sprintSlotId = null)
    {
        $user = Auth::user();

        $lowestOrder = PlannerTask::where('user_id', $user->id)
            ->where('team_id', $user->currentTeam->id)
            ->min('order') ?? 0;

        $order = $lowestOrder - 1;

        PlannerTask::create([
            'user_id'        => $user->id,
            'user_in_charge_id' => $user->id,
            'project_id'     => $this->project->id,
            'sprint_slot_id' => $sprintSlotId,
            'title'          => 'Neue Aufgabe',
            'description'    => null,
            'due_date'       => null,
            'priority'       => null,
            'story_points'   => null,
            'team_id'        => $user->currentTeam->id,
            'order'          => $order,
        ]);

        // Optional: State neu laden, falls Tasks direkt im UI erscheinen sollen
        $this->mount($this->project);
    }

    /**
     * Aktualisiert Reihenfolge und Slot-Zugehörigkeit der Tasks nach Drag&Drop.
     */
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
                $task->sprint_slot_id    = $taskGroupId;
                $task->save();
            }
        }

        // Nach Update optional State refresh
        $this->mount($this->project);
    }

    /**
     * Aktualisiert Reihenfolge der Slots nach Drag&Drop.
     */
    public function updateTaskGroupOrder($groups)
    {
        foreach ($groups as $taskGroup) {
            $taskGroupDb = PlannerSprintSlot::find($taskGroup['value']);
            if ($taskGroupDb) {
                $taskGroupDb->order = $taskGroup['order'];
                $taskGroupDb->save();
            }
        }

        // Nach Update optional State refresh
        $this->mount($this->project);
    }
}