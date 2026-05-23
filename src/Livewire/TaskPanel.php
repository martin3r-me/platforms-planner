<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\ActivityLog\Models\ActivityLogActivity;

class TaskPanel extends Component
{
    public ?int $taskId = null;
    public bool $open = false;

    #[On('openTaskPanel')]
    public function openTaskPanel(int $taskId): void
    {
        $this->taskId = $taskId;
        $this->open = true;
    }

    #[On('closeTaskPanel')]
    public function closeTaskPanel(): void
    {
        $this->open = false;
        $this->taskId = null;
    }

    public function render()
    {
        $task = null;
        $activities = collect();

        if ($this->open && $this->taskId) {
            $task = PlannerTask::with(['user', 'userInCharge', 'project', 'team', 'tags', 'contextColors'])
                ->find($this->taskId);

            if ($task) {
                // Check view permission
                $user = Auth::user();
                if (!$user->can('view', $task)) {
                    $task = null;
                } else {
                    // Load recent activities
                    $activities = ActivityLogActivity::query()
                        ->where('activityable_type', PlannerTask::class)
                        ->where('activityable_id', $task->id)
                        ->with('user')
                        ->latest()
                        ->limit(10)
                        ->get();
                }
            }
        }

        return view('planner::livewire.task-panel', [
            'task' => $task,
            'activities' => $activities,
        ]);
    }
}
