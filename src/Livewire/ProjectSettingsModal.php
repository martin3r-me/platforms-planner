<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Platform\Planner\Enums\ProjectType;
use Platform\Planner\Enums\ProjectKind;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Exceptions\InvalidLifecycleTransitionException;
use Platform\Planner\Services\LifecycleService;

use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\StorePlannedTime;

class ProjectSettingsModal extends Component
{
    public $modalShow = false;
    public $project;
    public $teamUsers = [];
    public $roles = [];
    public $originalProjectType = null;
    public $projectType = null;
    public $billingMethodOptions = [];

    // Entity-Links (read-only Anzeige)
    public $entityLinks = [];

    // Planned Time (zentral)
    public $plannedMinutes = null;

    // Public Sharing
    public bool $isPublic = false;
    public ?string $publicUrl = null;

    public $activeTab = 'general';

    #[On('open-modal-project-settings')]
    public function openModalProjectSettings($projectId, $tab = null)
    {
        $this->project = PlannerProject::findOrFail($projectId);

        // Policy-Berechtigung prüfen - Settings erfordert view-Rechte
        $this->authorize('settings', $this->project);

        // Event für RecurringTasksTab senden
        $this->dispatch('project-loaded', $projectId);

        $this->originalProjectType = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);

        $this->projectType = $this->originalProjectType;

        // Keine Projekt-Mitgliedschaft/Rollen mehr (Zugriff = Graph).
        $this->teamUsers = [];
        $this->roles = [];

        // Entity-Links laden (read-only)
        $this->loadEntityLinks();

        // Planned Time aus zentralem System laden
        $this->plannedMinutes = $this->project->totalPlannedMinutes() ?: null;

        // Public Sharing laden
        $this->isPublic = (bool) $this->project->is_public;
        $this->publicUrl = $this->project->getPublicUrl();

        // Tab setzen (default oder übergeben)
        $this->activeTab = $tab ?? 'general';

        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;
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
            'plannedMinutes' => 'nullable|integer|min:0',
            'project.project_type' => 'nullable|in:internal,customer,event,cooking',
            'project.kind' => 'nullable|in:run,project',
            // Lebenszyklus wird ausschließlich über die Transition-Buttons
            // (completeProject/discardProject/…) und den LifecycleService gesetzt,
            // NICHT über eine gebundene Property. Eine Rule für 'project.status'
            // würde Livewire erlauben, das (gelöschte) Legacy-Feld zu hydrieren
            // → "Unknown column 'status'" beim Speichern.
            // Billing-Felder direkt am Projekt
            'project.billing_method' => 'nullable|in:time_and_material,fixed_price,retainer',
            'project.hourly_rate' => 'nullable|numeric|min:0',
            'project.budget_amount' => 'nullable|numeric|min:0',
            'project.currency' => 'nullable|string|size:3',
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

        // Soll-Zeit über zentrales System speichern
        $minutes = $this->plannedMinutes ? (int) $this->plannedMinutes : 0;
        if ($minutes > 0) {
            $existing = OrganizationTimePlanned::where('context_type', PlannerProject::class)
                ->where('context_id', $this->project->id)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                app(StorePlannedTime::class)->update($existing, ['planned_minutes' => $minutes]);
            } else {
                app(StorePlannedTime::class)->store([
                    'team_id' => $this->project->team_id,
                    'user_id' => Auth::id(),
                    'context_type' => PlannerProject::class,
                    'context_id' => $this->project->id,
                    'planned_minutes' => $minutes,
                    'note' => null,
                    'is_active' => true,
                ]);
            }
        } else {
            OrganizationTimePlanned::where('context_type', PlannerProject::class)
                ->where('context_id', $this->project->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $this->originalProjectType = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);

        $this->dispatch('updateSidebar');
        $this->dispatch('updateProject');
        $this->dispatch('updateDashboard');

        // Keine Rollen-/Mitglieder-Reconciliation mehr — Zugriff kommt aus dem Graphen.

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

    public function setKind(string $kind): void
    {
        $this->authorize('update', $this->project);
        $enum = ProjectKind::tryFrom($kind);
        if (! $enum) {
            return;
        }
        $this->project->kind = $enum;
        $this->project->save();

        $this->dispatch('updateSidebar');
        $this->dispatch('updateProject');
    }

    // ── Lifecycle actions (project detail modal) ─────────────────

    public function completeProject(): void
    {
        $this->runLifecycle('complete', 'Projekt abgeschlossen');
    }

    public function discardProject(): void
    {
        $this->runLifecycle('discard', 'Projekt verworfen (offene Tasks kaskadiert)');
    }

    public function reopenProject(): void
    {
        $this->runLifecycle('reopen', 'Projekt wieder aktiviert');
    }

    public function reviveProject(): void
    {
        $this->runLifecycle('revive', 'Projekt zurückgeholt');
    }

    protected function runLifecycle(string $verb, string $successMessage): void
    {
        $this->authorize('update', $this->project);
        try {
            app(LifecycleService::class)->{$verb}($this->project);
            $this->project->refresh();
        } catch (InvalidLifecycleTransitionException) {
            return;
        }
        $this->dispatch('updateSidebar');
        $this->dispatch('updateProject');
        $this->dispatch('updateDashboard');
        $this->dispatch('notifications:store', [
            'title' => $successMessage,
            'message' => '',
            'notice_type' => 'success',
            'noticable_type' => get_class($this->project),
            'noticable_id'   => $this->project->getKey(),
        ]);
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
        $this->projectType = $type;
    }

    // ── Public Sharing ──────────────────────────────────────────────

    public function enablePublicLink(): void
    {
        $this->authorize('update', $this->project);

        $this->project->generatePublicToken();
        $this->isPublic = true;
        $this->publicUrl = $this->project->getPublicUrl();
    }

    public function disablePublicLink(): void
    {
        $this->authorize('update', $this->project);

        $this->project->revokePublicToken();
        $this->isPublic = false;
        $this->publicUrl = null;
    }

    public function regeneratePublicLink(): void
    {
        $this->authorize('update', $this->project);

        $this->project->generatePublicToken();
        $this->publicUrl = $this->project->getPublicUrl();
    }

    // ── Entity Links (read-only) ─────────────────────────────────

    private function loadEntityLinks(): void
    {
        if (!$this->project) {
            $this->entityLinks = [];
            return;
        }

        $links = collect();

        $entityLinkResults = \Platform\Organization\Services\EntityDimensionBridge::linksForLinkables(
            ['project'],
            [$this->project->id]
        );
        foreach ($entityLinkResults as $link) {
            $links->push([
                'id' => $link->id,
                'entity_name' => $link->entity?->name ?? 'Unbekannt',
                'entity_type' => $link->entity?->type?->name ?? '',
            ]);
        }

        $this->entityLinks = $links->unique('entity_name')->values()->toArray();
    }

    public function markAsDone()
    {
        $this->authorize('update', $this->project);

        try {
            app(LifecycleService::class)->complete($this->project);
            $this->project->refresh();
        } catch (InvalidLifecycleTransitionException) {
            return;
        }

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
        $this->authorize('delete', $this->project);

        $this->project->delete();
        $this->redirect(route('planner.dashboard'), navigate: true);
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function getCurrentUserRole()
    {
        if (!$this->project) {
            return null;
        }

        // Ersteller = owner, sonst keine Rolle (Zugriff kommt aus dem Graphen).
        return ((int) $this->project->user_id === (int) Auth::id()) ? 'owner' : null;
    }

    public function render()
    {
        return view('planner::livewire.project-settings-modal', [
            'currentUserRole' => $this->getCurrentUserRole(),
        ])->layout('platform::layouts.app');
    }
}
