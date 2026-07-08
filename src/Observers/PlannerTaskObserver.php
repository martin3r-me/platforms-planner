<?php

namespace Platform\Planner\Observers;

use Platform\Notifications\NotificationDispatcher;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerTask;

class PlannerTaskObserver
{
    /**
     * Fired before save. Handles:
     *   - Postpone-Tracking: manual due_date shifts count as a postpone.
     *
     * Done-state bookkeeping (setting done_at, is_done etc.) is now the
     * job of LifecycleService — no observer magic on that path anymore.
     */
    public function updating(PlannerTask $task): void
    {
        // Manual due-date shift on an active task counts as a postpone.
        if ($task->isDirty('due_date')
            && $task->lifecycle_state === TaskLifecycleState::ACTIVE
        ) {
            $previousDue = $task->getOriginal('due_date');

            if ($previousDue) {
                if (! $task->original_due_date) {
                    $task->original_due_date = $previousDue;
                }
                $task->postpone_count = (int) ($task->postpone_count ?? 0) + 1;
            }
        }
    }

    /**
     * Fired after save. Dispatches assignment / completion notifications.
     */
    public function updated(PlannerTask $task): void
    {
        // Task assignment
        if ($task->wasChanged('user_in_charge_id') && $task->user_in_charge_id) {
            if ($task->user_in_charge_id !== auth()->id()) {
                $recipient = $task->userInCharge;

                if ($recipient) {
                    app(NotificationDispatcher::class)->dispatch(
                        'planner.task.assigned',
                        [
                            'title'          => 'Aufgabe zugewiesen',
                            'message'        => "Dir wurde die Aufgabe \"{$task->title}\" zugewiesen.",
                            'noticable_type' => PlannerTask::class,
                            'noticable_id'   => $task->id,
                            'team_id'        => $task->team_id,
                            'metadata'       => ['url' => route('planner.tasks.show', $task->id)],
                        ],
                        [$recipient]
                    );
                }
            }
        }

        // Task completed — notify the creator if someone else did it.
        $justCompleted = $task->wasChanged('lifecycle_state')
            && $task->lifecycle_state === TaskLifecycleState::COMPLETED;

        if ($justCompleted && $task->user_in_charge_id) {
            $creatorId = $task->created_by ?? $task->user_id ?? null;

            if ($creatorId && $creatorId !== auth()->id() && $creatorId !== $task->user_in_charge_id) {
                $creator = app(config('auth.providers.users.model'))::find($creatorId);

                if ($creator) {
                    app(NotificationDispatcher::class)->dispatch(
                        'planner.task.completed',
                        [
                            'title'          => 'Aufgabe erledigt',
                            'message'        => "Die Aufgabe \"{$task->title}\" wurde als erledigt markiert.",
                            'noticable_type' => PlannerTask::class,
                            'noticable_id'   => $task->id,
                            'team_id'        => $task->team_id,
                            'metadata'       => ['url' => route('planner.tasks.show', $task->id)],
                        ],
                        [$creator]
                    );
                }
            }
        }
    }
}
