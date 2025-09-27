<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject as Project;
use Platform\Planner\Models\PlannerProjectSlot as ProjectSlot;
use Livewire\Attributes\On; 


class Sidebar extends Component
{

    #[On('updateSidebar')] 
    public function updateSidebar()
    {
        
    }

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

        return redirect()->route('planner.projects.show', ['plannerProject' => $project->id]);
    }

    public function render()
    {
        // Dynamische Projekte holen, z. B. team-basiert
        $projects = Project::query()
            ->where('team_id', auth()->user()?->currentTeam->id ?? null)
            ->orderBy('name')
            ->get();

        $customerProjects = $projects->filter(function ($p) {
            $type = is_string($p->project_type) ? $p->project_type : ($p->project_type?->value ?? null);
            return $type === 'customer';
        });

        $internalProjects = $projects->filter(function ($p) {
            $type = is_string($p->project_type) ? $p->project_type : ($p->project_type?->value ?? null);
            return $type !== 'customer';
        });

        return view('planner::livewire.sidebar', [
            'projects' => $projects,
            'customerProjects' => $customerProjects,
            'internalProjects' => $internalProjects,
        ]);
    }
}
