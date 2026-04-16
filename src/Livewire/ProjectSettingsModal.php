<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Enums\ProjectRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Platform\Planner\Enums\ProjectType;
use Platform\Core\Services\EntityLinkService;

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

    // Canvas-Links
    public array $linkedCanvases = [];
    public string $canvasSearch = '';
    public array $availableCanvases = [];

    // Public Sharing
    public bool $isPublic = false;
    public ?string $publicUrl = null;

    public $activeTab = 'general';

    #[On('open-modal-project-settings')]
    public function openModalProjectSettings($projectId, $tab = null)
    {
        $this->project = PlannerProject::with(['projectUsers.user'])->findOrFail($projectId);

        // Policy-Berechtigung prüfen - Settings erfordert view-Rechte
        $this->authorize('settings', $this->project);

        // Event für RecurringTasksTab senden
        $this->dispatch('project-loaded', $projectId);

        $this->originalProjectType = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);

        $this->projectType = $this->originalProjectType;

        // Teammitglieder holen (z.B. für Auswahl und Anzeige)
        $this->teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get();

        // Rollen aus aktueller ProjectUser-Tabelle laden
        $this->roles = [];

        // Zuerst alle aktuellen Projekt-User in roles aufnehmen
        foreach ($this->project->projectUsers as $projectUser) {
            $this->roles[$projectUser->user_id] = $projectUser->role;
        }

        // Dann auch alle Team-User hinzufügen (falls noch nicht vorhanden)
        foreach ($this->teamUsers as $user) {
            if (!isset($this->roles[$user->id])) {
                $this->roles[$user->id] = '';
            }
        }

        // Entity-Links laden (read-only)
        $this->loadEntityLinks();

        // Canvas-Links laden
        $this->loadLinkedCanvases();

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
            'project.planned_minutes' => 'nullable|integer|min:0',
            'project.project_type' => 'nullable|in:internal,customer,event,cooking',
            'roles' => 'array',
            'roles.*' => 'nullable|string|in:' . implode(',', array_column(ProjectRole::cases(), 'value')),
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
        $this->originalProjectType = is_string($this->project->project_type)
            ? $this->project->project_type
            : ($this->project->project_type?->value ?? null);

        $this->dispatch('updateSidebar');
        $this->dispatch('updateProject');
        $this->dispatch('updateDashboard');

        // 1. Owner sichern
        $ownerId = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value)?->user_id;
        if (!$ownerId) {
            $ownerId = Auth::id();
        }

        // 2. Neue Zuweisungen: Owner bleibt immer erhalten und immer owner!
        PlannerProjectUser::where('project_id', $this->project->id)
            ->where('role', '!=', ProjectRole::OWNER->value)
            ->delete();

        foreach ($this->roles as $userId => $role) {
            if (!$role || $role === ProjectRole::OWNER->value) {
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

    // ── Canvas Links ──────────────────────────────────────────────

    private function loadLinkedCanvases(): void
    {
        if (!$this->project) {
            $this->linkedCanvases = [];
            return;
        }

        $service = app(EntityLinkService::class);
        $teamId = $this->project->team_id;
        $projectType = $this->project->getMorphClass();
        $projectId = $this->project->getKey();

        $canvases = collect();

        // Canvas-Links laden
        $canvasIds = $service->getLinkedIds($teamId, $projectType, $projectId, 'canvas');
        if (!empty($canvasIds)) {
            $canvasModels = \Platform\Canvas\Models\Canvas::whereIn('id', $canvasIds)->get();
            foreach ($canvasModels as $canvas) {
                $canvases->push([
                    'id' => $canvas->id,
                    'name' => $canvas->name,
                    'type' => 'canvas',
                    'status' => $canvas->status ?? '',
                    'url' => route('canvas.canvases.show', $canvas->id),
                ]);
            }
        }

        // PcCanvas-Links laden
        $pcCanvasIds = $service->getLinkedIds($teamId, $projectType, $projectId, 'pc_canvas');
        if (!empty($pcCanvasIds)) {
            $pcCanvasModels = \Platform\ProjectCanvas\Models\PcCanvas::whereIn('id', $pcCanvasIds)->get();
            foreach ($pcCanvasModels as $canvas) {
                $canvases->push([
                    'id' => $canvas->id,
                    'name' => $canvas->name,
                    'type' => 'pc_canvas',
                    'status' => $canvas->status ?? '',
                    'url' => route('project-canvas.canvases.show', $canvas->id),
                ]);
            }
        }

        $this->linkedCanvases = $canvases->toArray();
    }

    public function updatedCanvasSearch(): void
    {
        $this->searchCanvases();
    }

    public function searchCanvases(): void
    {
        if (!$this->project || strlen($this->canvasSearch) < 2) {
            $this->availableCanvases = [];
            return;
        }

        $teamId = $this->project->team_id;
        $search = $this->canvasSearch;

        // Bereits verknüpfte IDs sammeln
        $linkedCanvasIds = collect($this->linkedCanvases)
            ->where('type', 'canvas')
            ->pluck('id')
            ->toArray();
        $linkedPcCanvasIds = collect($this->linkedCanvases)
            ->where('type', 'pc_canvas')
            ->pluck('id')
            ->toArray();

        $results = collect();

        // Canvas suchen
        $canvases = \Platform\Canvas\Models\Canvas::where('team_id', $teamId)
            ->where('name', 'like', "%{$search}%")
            ->whereNotIn('id', $linkedCanvasIds)
            ->limit(10)
            ->get();
        foreach ($canvases as $canvas) {
            $results->push([
                'id' => $canvas->id,
                'name' => $canvas->name,
                'type' => 'canvas',
                'type_label' => 'Canvas',
            ]);
        }

        // PcCanvas suchen
        $pcCanvases = \Platform\ProjectCanvas\Models\PcCanvas::where('team_id', $teamId)
            ->where('name', 'like', "%{$search}%")
            ->whereNotIn('id', $linkedPcCanvasIds)
            ->limit(10)
            ->get();
        foreach ($pcCanvases as $canvas) {
            $results->push([
                'id' => $canvas->id,
                'name' => $canvas->name,
                'type' => 'pc_canvas',
                'type_label' => 'Project Canvas',
            ]);
        }

        $this->availableCanvases = $results->toArray();
    }

    public function attachCanvas(int $canvasId, string $canvasType): void
    {
        $this->authorize('update', $this->project);

        app(EntityLinkService::class)->link(
            $this->project->team_id,
            $this->project->getMorphClass(),
            $this->project->getKey(),
            $canvasType,
            $canvasId,
        );

        $this->loadLinkedCanvases();
        $this->canvasSearch = '';
        $this->availableCanvases = [];
    }

    public function detachCanvas(int $canvasId, string $canvasType): void
    {
        $this->authorize('update', $this->project);

        app(EntityLinkService::class)->unlink(
            $this->project->team_id,
            $this->project->getMorphClass(),
            $this->project->getKey(),
            $canvasType,
            $canvasId,
        );

        $this->loadLinkedCanvases();
    }

    // ── Entity Links (read-only) ─────────────────────────────────

    private function loadEntityLinks(): void
    {
        if (!$this->project) {
            $this->entityLinks = [];
            return;
        }

        $links = collect();

        // a) OrganizationContext (primäre Quelle – UI)
        $orgContext = $this->project->organizationContext()
            ->where('is_active', true)
            ->with('organizationEntity.type')
            ->first();
        if ($orgContext && $orgContext->organizationEntity) {
            $links->push([
                'id' => $orgContext->id,
                'entity_name' => $orgContext->organizationEntity->name ?? 'Unbekannt',
                'entity_type' => $orgContext->organizationEntity->type?->name ?? '',
            ]);
        }

        // b) OrganizationEntityLink (sekundäre Quelle – DimensionLinker / LLM Tools)
        $entityLinkResults = \Platform\Organization\Models\OrganizationEntityLink::query()
            ->whereIn('linkable_type', ['project', 'planner_project', get_class($this->project)])
            ->where('linkable_id', $this->project->id)
            ->with(['entity.type'])
            ->get();
        foreach ($entityLinkResults as $link) {
            $links->push([
                'id' => $link->id,
                'entity_name' => $link->entity?->name ?? 'Unbekannt',
                'entity_type' => $link->entity?->type?->name ?? '',
            ]);
        }

        $this->entityLinks = $links->unique('entity_name')->values()->toArray();
    }

    // ── Existing methods (unchanged) ─────────────────────────────

    public function removeProjectUser($userId)
    {
        $this->authorize('removeMember', $this->project);

        $ownerId = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value)?->user_id;
        if ($userId == $ownerId) {
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
        $this->authorize('delete', $this->project);

        $this->project->delete();
        $this->redirect(route('planner.dashboard'), navigate: true);
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function addProjectUser($userId, $role = 'member')
    {
        $this->authorize('invite', $this->project);

        $existingUser = $this->project->projectUsers()->where('user_id', $userId)->first();
        if ($existingUser) {
            return;
        }

        PlannerProjectUser::create([
            'project_id' => $this->project->id,
            'user_id' => $userId,
            'role' => $role
        ]);

        $this->roles[$userId] = $role;
        $this->project->refresh();
    }

    public function changeUserRole($userId, $newRole)
    {
        $this->authorize('changeRole', $this->project);

        $ownerId = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value)?->user_id;

        if ($userId == $ownerId && $newRole !== ProjectRole::OWNER->value) {
            return;
        }

        PlannerProjectUser::where('project_id', $this->project->id)
            ->where('user_id', $userId)
            ->update(['role' => $newRole]);

        $this->roles[$userId] = $newRole;
        $this->project->refresh();
    }

    public function transferOwnership($newOwnerId)
    {
        $this->authorize('transferOwnership', $this->project);

        $currentOwner = $this->project->projectUsers->firstWhere('role', ProjectRole::OWNER->value);

        if ($currentOwner) {
            $currentOwner->update(['role' => ProjectRole::ADMIN->value]);
        }

        $newOwner = $this->project->projectUsers()->where('user_id', $newOwnerId)->first();
        if ($newOwner) {
            $newOwner->update(['role' => ProjectRole::OWNER->value]);
        } else {
            PlannerProjectUser::create([
                'project_id' => $this->project->id,
                'user_id' => $newOwnerId,
                'role' => ProjectRole::OWNER->value
            ]);
        }

        $this->roles[$newOwnerId] = ProjectRole::OWNER->value;
        $this->project->refresh();
    }

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

    public function getCurrentUserRole()
    {
        if (!$this->project) {
            return null;
        }

        $projectUser = $this->project->projectUsers()
            ->where('user_id', Auth::id())
            ->first();

        return $projectUser?->role;
    }

    public function render()
    {
        return view('planner::livewire.project-settings-modal', [
            'currentUserRole' => $this->getCurrentUserRole(),
        ])->layout('platform::layouts.app');
    }
}
