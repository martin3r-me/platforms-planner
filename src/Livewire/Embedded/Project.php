<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Project as BaseProject;
use Platform\Planner\Models\PlannerTask;
use Illuminate\Support\Facades\Auth;

class Project extends BaseProject
{
    public function createTask($projectSlotId = null)
    {
        // DEBUG: Log dass die embedded createTask aufgerufen wird
        \Log::info("🔍 EMBEDDED CREATE TASK CALLED:", [
            'project_id' => $this->project->id,
            'project_slot_id' => $projectSlotId,
            'timestamp' => now()
        ]);

        $user = Auth::user();

        $lowestOrder = PlannerTask::where('user_id', $user->id)
            ->where('team_id', $user->currentTeam->id)
            ->min('order') ?? 0;

        $order = $lowestOrder - 1;

        $task = PlannerTask::create([
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

        \Log::info("🔍 EMBEDDED TASK CREATED:", [
            'task_id' => $task->id,
            'redirect_to' => route('planner.embedded.task', $task),
            'timestamp' => now()
        ]);

        // Direkte Weiterleitung zur embedded Task-Route OHNE mount()
        return $this->redirect(route('planner.embedded.task', $task), navigate: true);
    }

    public function render()
    {
        return view('planner::livewire.embedded.project');
    }
}


