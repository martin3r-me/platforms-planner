<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Enums\ProjectRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 
use Platform\Planner\Enums\ProjectType;
use Platform\Planner\Models\PlannerCustomerProject;

class ProjectSettingsModal extends Component
{
    public $modalShow = false;
    public $project;
    public $teamUsers = [];
    public $roles = [];
    public $customerProjectForm = [];
    public $hasCustomerProject = false;
    public $originalProjectType = null;
    public $billingMethodOptions = [];

    #[On('open-modal-project-settings')] 
    public function openModalProjectSettings($projectId)
    {
        $this->project = PlannerProject::with(['projectUsers.user', 'customerProject'])->findOrFail($projectId);
        
        // Policy-Berechtigung prüfen
        $this->authorize('view', $this->project);
        
        $this->originalProjectType = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);

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

        // Kundenprojekt-Form vorbereiten
        $this->hasCustomerProject = (bool) $this->project->customerProject;
        $cp = $this->project->customerProject;
        $this->customerProjectForm = [
            'company_id' => $cp?->company_id,
            'contact_id' => $cp?->contact_id,
            'billing_method' => $cp?->billing_method,
            'hourly_rate' => $cp?->hourly_rate,
            'currency' => $cp?->currency ?? 'EUR',
            'budget_amount' => $cp?->budget_amount,
            'cost_center' => $cp?->cost_center,
            'invoice_account' => $cp?->invoice_account,
            'notes' => $cp?->notes,
        ];

        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;
        // Options zentral bereitstellen (verhindert htmlspecialchars-Fehler durch Inline-Arrays)
        $this->billingMethodOptions = [
            ['value' => 'time_and_material', 'label' => 'Zeit & Material'],
            ['value' => 'fixed_price', 'label' => 'Festpreis'],
            ['value' => 'retainer', 'label' => 'Retainer'],
        ];
    }

    public function rules(): array
    {
        return [
            'project.name' => 'required|string|max:255',
            'project.description' => 'nullable|string',
            'project.planned_minutes' => 'nullable|integer|min:0',
            'project.customer_cost_center' => 'nullable|string|max:64',
            'project.project_type' => 'nullable|in:internal,customer',
            'roles' => 'array',
            'roles.*' => 'nullable|string|in:' . implode(',', array_column(ProjectRole::cases(), 'value')),
            // Kundenprojekt Felder
            'customerProjectForm.company_id' => 'nullable|integer',
            'customerProjectForm.contact_id' => 'nullable|integer',
            'customerProjectForm.billing_method' => 'nullable|in:time_and_material,fixed_price,retainer',
            'customerProjectForm.hourly_rate' => 'nullable|numeric',
            'customerProjectForm.currency' => 'nullable|string|size:3',
            'customerProjectForm.budget_amount' => 'nullable|numeric',
            'customerProjectForm.cost_center' => 'nullable|string|max:64',
            'customerProjectForm.invoice_account' => 'nullable|string|max:64',
            'customerProjectForm.notes' => 'nullable|string',
        ];
    }

    public function save()
    {
        $this->validate();
        
        // Policy-Berechtigung prüfen
        $this->authorize('update', $this->project);

        // Kunde -> Intern verhindern (irreversibel)
        $currentType = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);
        if ($this->originalProjectType === 'customer' && $currentType === 'internal') {
            $this->project->project_type = ProjectType::CUSTOMER;
        }

        $this->project->save();
        $this->originalProjectType = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);

        // Kundenprojekt anlegen/aktualisieren, falls Projekttyp = Kunde
        if ($this->project->project_type === ProjectType::CUSTOMER) {
            $payload = array_merge(
                [
                    'team_id' => Auth::user()->currentTeam->id,
                    'user_id' => Auth::id(),
                    'project_id' => $this->project->id,
                ],
                $this->customerProjectForm
            );

            PlannerCustomerProject::updateOrCreate(
                ['project_id' => $this->project->id],
                $payload
            );
        }
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

    public function createCustomerProject()
    {
        if (! $this->project) {
            return;
        }

        if ($this->project->customerProject) {
            $this->hasCustomerProject = true;
            return;
        }

        $cp = PlannerCustomerProject::create([
            'project_id' => $this->project->id,
            'team_id' => Auth::user()->currentTeam->id,
            'user_id' => Auth::id(),
            'currency' => $this->customerProjectForm['currency'] ?? 'EUR',
        ]);

        $this->project->refresh();
        $this->hasCustomerProject = true;
    }

    public function setProjectType(string $type): void
    {
        $current = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);
        if ($current === 'customer' && $type === 'internal') {
            // Nicht zurückwechseln erlaubt
            return;
        }
        $this->project->project_type = $type;
        // Bei Umstellung auf Kunden sofort persistieren und CustomerProject anlegen
        if ($type === 'customer') {
            $this->project->save();
            $this->originalProjectType = 'customer';
            if (! $this->project->customerProject) {
                PlannerCustomerProject::create([
                    'project_id' => $this->project->id,
                    'team_id' => Auth::user()->currentTeam->id,
                    'user_id' => Auth::id(),
                    'currency' => 'EUR',
                ]);
                $this->project->refresh();
                $this->hasCustomerProject = true;
            }
        }
    }

    public function removeProjectUser($userId)
    {
        // Policy-Berechtigung prüfen
        $this->authorize('removeMember', $this->project);
        
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

    public function markAsDone()
    {
        // Policy-Berechtigung prüfen
        $this->authorize('update', $this->project);
        
        $this->project->done = true;
        $this->project->done_at = now();
        $this->project->save();
        
        $this->dispatch('updateSidebar');
        $this->dispatch('updateProject');
        $this->dispatch('updateDashboard');
        
        $this->dispatch('notifications:store', [
            'title' => 'Projekt abgeschlossen',
            'message' => 'Das Projekt wurde erfolgreich als abgeschlossen markiert.',
            'notice_type' => 'success',
            'noticable_type' => get_class($this->project),
            'noticable_id'   => $this->project->getKey(),
        ]);
        
        $this->project->refresh();
    }

    public function deleteProject()
    {
        // Policy-Berechtigung prüfen
        $this->authorize('delete', $this->project);
        
        $this->project->delete();
        // Nach Planner-Dashboard leiten
        $this->redirect(route('planner.dashboard'), navigate: true);
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    /**
     * Fügt einen neuen Teilnehmer zum Projekt hinzu
     */
    public function addProjectUser($userId, $role = 'member')
    {
        // Policy-Berechtigung prüfen
        $this->authorize('invite', $this->project);
        
        // Prüfen ob User bereits im Projekt ist
        $existingUser = $this->project->projectUsers()->where('user_id', $userId)->first();
        if ($existingUser) {
            return; // User bereits im Projekt
        }
        
        // Neuen Teilnehmer hinzufügen
        PlannerProjectUser::create([
            'project_id' => $this->project->id,
            'user_id' => $userId,
            'role' => $role
        ]);
        
        $this->roles[$userId] = $role;
        $this->project->refresh();
    }

    /**
     * Ändert die Rolle eines Teilnehmers
     */
    public function changeUserRole($userId, $newRole)
    {
        // Policy-Berechtigung prüfen
        $this->authorize('changeRole', $this->project);
        
        $ownerId = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value)?->user_id;
        
        // Owner-Rolle kann nicht geändert werden
        if ($userId == $ownerId && $newRole !== ProjectRole::OWNER->value) {
            return;
        }
        
        // Rolle aktualisieren
        PlannerProjectUser::where('project_id', $this->project->id)
            ->where('user_id', $userId)
            ->update(['role' => $newRole]);
        
        $this->roles[$userId] = $newRole;
        $this->project->refresh();
    }

    /**
     * Überträgt das Ownership an einen anderen User
     */
    public function transferOwnership($newOwnerId)
    {
        // Policy-Berechtigung prüfen
        $this->authorize('transferOwnership', $this->project);
        
        $currentOwner = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value);
        
        if ($currentOwner) {
            // Aktueller Owner wird zu Admin
            $currentOwner->update(['role' => ProjectRole::ADMIN->value]);
        }
        
        // Neuer Owner
        $newOwner = $this->project->projectUsers()->where('user_id', $newOwnerId)->first();
        if ($newOwner) {
            $newOwner->update(['role' => ProjectRole::OWNER->value]);
        } else {
            // Falls User noch nicht im Projekt, hinzufügen
            PlannerProjectUser::create([
                'project_id' => $this->project->id,
                'user_id' => $newOwnerId,
                'role' => ProjectRole::OWNER->value
            ]);
        }
        
        $this->roles[$newOwnerId] = ProjectRole::OWNER->value;
        $this->project->refresh();
    }

    /**
     * Lädt verfügbare Team-Mitglieder für Einladung
     */
    public function getAvailableUsers()
    {
        $currentUserIds = $this->project->projectUsers->pluck('user_id')->toArray();
        
        return Auth::user()
            ->currentTeam
            ->users()
            ->whereNotIn('users.id', $currentUserIds)
            ->orderBy('users.name')
            ->get();
    }

    public function render()
    {
        return view('planner::livewire.project-settings-modal')->layout('platform::layouts.app');
    }
}