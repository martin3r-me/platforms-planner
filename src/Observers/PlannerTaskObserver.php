<?php

namespace Platform\Planner\Observers;

use Platform\Planner\Models\PlannerTask;
use Illuminate\Support\Carbon;

class PlannerTaskObserver
{
    /**
     * Wird aufgerufen, wenn das Model aktualisiert wird.
     * Setzt done_at automatisch, wenn is_done auf true gesetzt wird.
     */
    public function updating(PlannerTask $task): void
    {
        // Wenn is_done von false auf true gesetzt wurde, done_at automatisch setzen
        if ($task->isDirty('is_done') && $task->is_done && !$task->done_at) {
            $task->done_at = Carbon::now();
        }

        // Wenn is_done auf false gesetzt wird, done_at zurücksetzen
        if ($task->isDirty('is_done') && !$task->is_done) {
            $task->done_at = null;
        }
    }
}

