<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\StoryPoints;

class PublicProject extends Component
{
    public PlannerProject $project;
    public bool $showDoneColumn = false;

    public function mount(string $token): void
    {
        $this->project = PlannerProject::where('public_token', $token)
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('public_token_expires_at')
                  ->orWhere('public_token_expires_at', '>', now());
            })
            ->firstOrFail();
    }

    public function toggleShowDoneColumn(): void
    {
        $this->showDoneColumn = !$this->showDoneColumn;
    }

    public function render()
    {
        // === 1. BACKLOG ===
        $backlogTasks = PlannerTask::with(['tags', 'contextColors', 'userInCharge', 'project'])
            ->where('project_id', $this->project->id)
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
                $q->with(['tags', 'contextColors', 'userInCharge', 'project'])
                  ->where('is_done', false)
                  ->whereNotNull('project_slot_id')
                  ->orderBy('project_slot_order');
            }])
            ->where('project_id', $this->project->id)
            ->orderBy('order')
            ->get()
            ->map(function ($slot) {
                $tasks = $slot->tasks->filter(function ($task) use ($slot) {
                    return $task->project_slot_id === $slot->id && $task->project_slot_id !== null;
                });

                return (object) [
                    'id' => $slot->id,
                    'label' => $slot->name,
                    'isBacklog' => false,
                    'tasks' => $tasks,
                    'open_count' => $tasks->count(),
                    'open_points' => $tasks->sum(
                        fn ($task) => $task->story_points instanceof StoryPoints
                            ? $task->story_points->points()
                            : 1
                    ),
                ];
            });

        // === 3. ERLEDIGTE AUFGABEN ===
        $doneTasks = PlannerTask::with(['tags', 'contextColors', 'userInCharge', 'project'])
            ->where('project_id', $this->project->id)
            ->where('is_done', true)
            ->orderByDesc('done_at')
            ->orderByDesc('updated_at')
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

        $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);

        return view('planner::livewire.public-project', [
            'groups' => $groups,
            'openTasks' => $openTasks,
            'doneTasks' => $doneTasks,
        ])->layout('platform::layouts.guest');
    }
}
