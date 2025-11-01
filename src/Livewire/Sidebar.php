<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject as Project;
use Platform\Planner\Models\PlannerProjectSlot as ProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Livewire\Attributes\On; 


class Sidebar extends Component
{
    public bool $showAllProjects = false;

    #[On('updateSidebar')] 
    public function updateSidebar()
    {
        
    }

    public function toggleShowAllProjects()
    {
        $this->showAllProjects = !$this->showAllProjects;
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
        $user = auth()->user();
        $teamId = $user?->currentTeam->id ?? null;

        if (!$user || !$teamId) {
            return view('planner::livewire.sidebar', [
                'customerProjects' => collect(),
                'internalProjects' => collect(),
                'hasMoreProjects' => false,
                'allCustomerProjectsCount' => 0,
                'allInternalProjectsCount' => 0,
            ]);
        }

        // Projekte, bei denen der User Aufgaben hat ODER Mitglied ist
        $projectsWithUserTasks = Project::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($user) {
                // 1. Projekte, bei denen der User Aufgaben hat (über ProjectSlots)
                $query->whereHas('projectSlots.tasks', function ($q) use ($user) {
                    $q->where('user_in_charge_id', $user->id)
                      ->where('is_done', false);
                })
                // 2. Oder Aufgaben direkt am Projekt
                ->orWhereHas('tasks', function ($q) use ($user) {
                    $q->where('user_in_charge_id', $user->id)
                      ->where('is_done', false)
                      ->whereNull('project_slot_id');
                })
                // 3. Oder Projekte, bei denen der User Mitglied ist (Owner/Admin/Member/Viewer)
                ->orWhereHas('projectUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            })
            ->orderBy('name')
            ->get();

        // Alle Projekte
        $allProjects = Project::query()
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        // Projekte filtern: nur solche mit User-Aufgaben, oder alle
        $projectsToShow = $this->showAllProjects 
            ? $allProjects 
            : $projectsWithUserTasks;

        // Nach Typ trennen
        $customerProjects = $projectsToShow->filter(function ($p) {
            $type = is_string($p->project_type) ? $p->project_type : ($p->project_type?->value ?? null);
            return $type === 'customer';
        });

        $internalProjects = $projectsToShow->filter(function ($p) {
            $type = is_string($p->project_type) ? $p->project_type : ($p->project_type?->value ?? null);
            return $type !== 'customer';
        });

        // Alle Projekte für den Button
        $allCustomerProjects = $allProjects->filter(function ($p) {
            $type = is_string($p->project_type) ? $p->project_type : ($p->project_type?->value ?? null);
            return $type === 'customer';
        });

        $allInternalProjects = $allProjects->filter(function ($p) {
            $type = is_string($p->project_type) ? $p->project_type : ($p->project_type?->value ?? null);
            return $type !== 'customer';
        });

        $hasMoreProjects = $allProjects->count() > $projectsWithUserTasks->count();

        return view('planner::livewire.sidebar', [
            'customerProjects' => $customerProjects,
            'internalProjects' => $internalProjects,
            'hasMoreProjects' => $hasMoreProjects,
            'allCustomerProjectsCount' => $allCustomerProjects->count(),
            'allInternalProjectsCount' => $allInternalProjects->count(),
        ]);
    }
}
