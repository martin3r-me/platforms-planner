<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\StoryPoints;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Platform\Core\Contracts\CrmCompanyResolverInterface;

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
        // Sprints werden nicht mehr geladen - nur Project-Slots
    }

    public function render()
    {
        $user = Auth::user();

        // === 1. BACKLOG ===
        $backlogTasks = PlannerTask::where('project_id', $this->project->id)
            ->whereNull('project_slot_id')
            ->where('is_done', false)
            ->orderBy('project_slot_order')
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

        // === 2. PROJECT-SLOTS ===
        $slots = PlannerProjectSlot::with(['tasks' => function ($q) {
                $q->where('is_done', false)->orderBy('project_slot_order');
            }])
            ->where('project_id', $this->project->id)
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

        // === 3. ERLEDIGTE AUFGABEN ===
        $doneTasks = PlannerTask::where('project_id', $this->project->id)
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

        // Kundenprojekt-Company anzeigen
        /** @var CrmCompanyResolverInterface $companyResolver */
        $companyResolver = app(CrmCompanyResolverInterface::class);
        $companyId = $this->project?->customerProject?->company_id;
        $customerCompanyName = $companyResolver->displayName($companyId);
        $customerCompanyUrl = $companyResolver->url($companyId);

        return view('planner::livewire.project', [
            'groups' => $groups,
            'customerCompanyName' => $customerCompanyName,
            'customerCompanyUrl' => $customerCompanyUrl,
        ])->layout('platform::layouts.app');
    }

    /**
     * Legt einen neuen Project-Slot an und lädt State neu.
     */
    public function createProjectSlot()
    {
        $user = Auth::user();
        $maxOrder = $this->project->projectSlots()->max('order') ?? 0;

        $this->project->projectSlots()->create([
            'name' => 'Neuer Slot',
            'order' => $maxOrder + 1,
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
        ]);

        // Slots/State neu laden (Livewire 3 Way)
        $this->mount($this->project);
    }

    /**
     * Legt eine neue Aufgabe an, optional direkt in einem Slot.
     */
    public function createTask($projectSlotId = null)
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
            'project_slot_id' => $projectSlotId,
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

                $task->project_slot_order = $item['order'];
                $task->project_slot_id    = $taskGroupId;
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
            $taskGroupDb = PlannerProjectSlot::find($taskGroup['value']);
            if ($taskGroupDb) {
                $taskGroupDb->order = $taskGroup['order'];
                $taskGroupDb->save();
            }
        }

        // Nach Update optional State refresh
        $this->mount($this->project);
    }
}