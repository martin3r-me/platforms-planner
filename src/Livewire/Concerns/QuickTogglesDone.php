<?php

namespace Platform\Planner\Livewire\Concerns;

use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Exceptions\InvalidLifecycleTransitionException;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\LifecycleService;

/**
 * Toggles a task between active and completed from list views.
 *
 * Compatibility layer for consumers that call `quickToggleDone($taskId)`.
 * Internally now routes through LifecycleService — no more direct
 * is_done / done_at writes.
 */
trait QuickTogglesDone
{
    public function quickToggleDone(int $taskId): void
    {
        $task = PlannerTask::findOrFail($taskId);
        $this->authorize('complete', $task);

        $lifecycle = app(LifecycleService::class);

        try {
            if ($task->lifecycle_state === TaskLifecycleState::COMPLETED) {
                $lifecycle->reopenTask($task);
            } else {
                $lifecycle->completeTask($task);
            }
        } catch (InvalidLifecycleTransitionException) {
            // Discarded tasks stay discarded — no silent flip through this trait.
        }
    }
}
