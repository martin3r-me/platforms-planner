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

        // State neu laden, damit die neue Aufgabe im Board erscheint
        $this->mount($this->project);
        
        // Einfache Weiterleitung ohne JavaScript
        return $this->redirect(route('planner.embedded.task', $task), navigate: true);
    }

    public function updateTaskOrder($groups)
    {
        \Log::info("🔍 EMBEDDED UPDATE TASK ORDER:", [
            'groups' => $groups,
            'timestamp' => now()
        ]);
        
        return parent::updateTaskOrder($groups);
    }

    public function updateTaskGroupOrder($groups)
    {
        \Log::info("🔍 EMBEDDED UPDATE TASK GROUP ORDER:", [
            'groups' => $groups,
            'timestamp' => now()
        ]);
        
        return parent::updateTaskGroupOrder($groups);
    }

    public function render()
    {
        // Basis-Komponente render() aufrufen, aber embedded View verwenden
        $data = parent::render();
        
        // Die Daten aus der Basis-Komponente extrahieren
        $viewData = $data->getData();
        
        // Embedded View mit den gleichen Daten rendern
        return view('planner::livewire.embedded.project', $viewData);
    }
}


