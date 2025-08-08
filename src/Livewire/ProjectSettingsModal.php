<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Enums\ProjectRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 

class ProjectSettingsModal extends Component
{
    public $modalShow = false;
    public $project;
    public $teamUsers = [];
    public $roles = [];

    #[On('open-modal-project-settings')] 
    public function openModalProjectSettings($projectId)
    {
        $this->project = PlannerProject::with('projectUsers.user')->findOrFail($projectId);

        // Teammitglieder holen (z.B. für Auswahl und Anzeige)
        $this->teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get();

        // Rollen aus aktueller ProjectUser-Tabelle laden
        $this->roles = [];
        foreach ($this->teamUsers as $user) {
            $projectUser = $this->project->projectUsers->firstWhere('user_id', $user->id);
            $this->roles[$user->id] = $projectUser?->role ?? '';
        }

        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    public function rules(): array
    {
        return [
            'project.name' => 'required|string|max:255',
            'project.description' => 'nullable|string',
            'roles' => 'array',
            'roles.*' => 'nullable|string|in:' . implode(',', array_column(ProjectRole::cases(), 'value')),
        ];
    }

    public function save()
    {
        $this->validate();

        $this->project->save();
        $this->dispatch('updateSidebar');
        $this->dispatch('updateProject');
        $this->dispatch('updateDashboard');

        // 1. Owner sichern
        $ownerId = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value)?->user_id;
        if (!$ownerId) {
            // Fallback: Setze aktuellen Nutzer als Owner (sollte nie nötig sein, aber zur Sicherheit)
            $ownerId = Auth::id();
        }

        // 2. Neue Zuweisungen: Owner bleibt immer erhalten und immer owner!
        PlannerProjectUser::where('project_id', $this->project->id)
            ->where('role', '!=', ProjectRole::OWNER->value)
            ->delete();

        foreach ($this->roles as $userId => $role) {
            if (!$role || $role === ProjectRole::OWNER->value) {
                // Owner kann nur über Ownership-Transfer gesetzt werden!
                continue;
            }

            PlannerProjectUser::updateOrCreate(
                [
                    'project_id' => $this->project->id,
                    'user_id'    => $userId,
                ],
                [
                    'role' => $role,
                ]
            );
        }

        // Owner-Eintrag sicherstellen (falls nicht mehr vorhanden)
        PlannerProjectUser::updateOrCreate([
            'project_id' => $this->project->id,
            'user_id' => $ownerId,
        ], [
            'role' => ProjectRole::OWNER->value,
        ]);

        $this->dispatch('notifications:store', [
            'title' => 'Projekt gespeichert',
            'message' => 'Das Projekt wurde erfolgreich aktualisiert.',
            'notice_type' => 'success',
            'noticable_type' => get_class($this->project),
            'noticable_id'   => $this->project->getKey(),
        ]);

        $this->reset('project', 'roles', 'teamUsers');
        $this->closeModal();
    }

    public function removeProjectUser($userId)
    {
        $ownerId = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value)?->user_id;
        if ($userId == $ownerId) {
            // Owner kann nicht entfernt werden!
            return;
        }
        PlannerProjectUser::where('project_id', $this->project->id)
            ->where('user_id', $userId)
            ->delete();

        unset($this->roles[$userId]);
        $this->project->refresh();
    }

    public function deleteProject()
    {
        $this->project->delete();
        $this->redirect('/');
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('planner::livewire.project-settings-modal')->layout('platform::layouts.app');
    }
}