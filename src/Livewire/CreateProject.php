<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject as Project;
use Platform\Planner\Models\PlannerProjectSlot as ProjectSlot;

class CreateProject extends Component
{

    public function createProject()
    {
        $user = Auth::user();
        $teamId = $user->currentTeam->id;

        // 1. Neues Projekt anlegen
        $project = new Project();
        $project->name = 'Neues Projekt';
        $project->user_id = $user->id;
        $project->team_id = $teamId;
        $project->order = Project::where('team_id', $teamId)->max('order') + 1;
        $project->save();

        // --> ProjectUser als Owner anlegen!
        $project->projectUsers()->create([
            'user_id' => $user->id,
            'role' => \Platform\Planner\Enums\ProjectRole::OWNER->value,
        ]);
        // Alternativ, falls du direkt das Model nutzen möchtest:
        // \Platform\Planner\Models\PlannerProjectUser::create([
        //     'project_id' => $project->id,
        //     'user_id' => $user->id,
        //     'role' => \Platform\Planner\Enums\ProjectRole::OWNER->value,
        // ]);

        // 2. Standard-Project-Slots erzeugen: To Do, Doing, Done
        $defaultSlots = ['To Do', 'Doing', 'Done'];
        foreach ($defaultSlots as $index => $name) {
            ProjectSlot::create([
                'project_id' => $project->id,
                'name' => $name,
                'order' => $index + 1,
                'user_id' => $user->id,
                'team_id' => $teamId,
            ]);
        }

        $this->dispatch('updateSidebar');
    }

    public function render()
    {        
        return view('planner::livewire.create-project')->layout('platform::layouts.app');
    }
}