<?php

namespace Platform\Planner\Livewire\Concerns;

use Platform\Planner\Models\PlannerTask;

trait QuickTogglesDone
{
    public function quickToggleDone(int $taskId): void
    {
        $task = PlannerTask::findOrFail($taskId);
        $this->authorize('complete', $task);
        $task->is_done = !$task->is_done;
        $task->done_at = $task->is_done ? now() : null;
        $task->save();
    }
}
