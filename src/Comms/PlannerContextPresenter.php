<?php

namespace Platform\Planner\Comms;

use Platform\Comms\Contracts\ContextPresenterInterface;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

class PlannerContextPresenter implements ContextPresenterInterface
{
    public function present(string $contextType, int $contextId): ?array
    {
        if ($contextType === PlannerTask::class) {
            $t = PlannerTask::find($contextId);
            if (!$t) return null;
            return [
                'title' => $t->title ?: ('Task #' . $t->id),
                'subtitle' => 'Planner Task #' . $t->id,
                'url' => route('planner.tasks.show', $t),
            ];
        }

        if ($contextType === PlannerProject::class) {
            $p = PlannerProject::find($contextId);
            if (!$p) return null;
            return [
                'title' => $p->name ?: ('Project #' . $p->id),
                'subtitle' => 'Planner Project #' . $p->id,
                'url' => route('planner.projects.show', $p),
            ];
        }

        return null;
    }
}


