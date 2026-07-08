<?php

namespace Platform\Planner\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;

/**
 * Aufraeum-Cockpit fuer Projekte.
 *
 * Dichte Tabellensicht mit Filter, Bulk-Auswahl und Inline-Aktionen —
 * gedacht fuer die Strategie-/Steuerung-Rolle, die viele Projekte gleichzeitig
 * bewertet, umsortiert oder entsorgt. Nicht fuer Detailarbeit an einem Projekt.
 */
class ProjectsCleanup extends Component
{
    // ── Filter ──────────────────────────────────────────────────
    /** all|red|yellow|green|gray */
    public string $colorFilter = 'all';
    /** all|aktiv|passiv|inaktiv */
    public string $statusFilter = 'all';
    public ?int $ownerFilter = null;
    /** ['no_owner','no_entity','no_tasks','stale'] */
    public array $suspectFlags = [];
    public string $search = '';
    /** name|score_asc|last_view_desc|tasks_desc */
    public string $sort = 'name';
    public bool $includeDone = false;

    // ── Bulk-Selection ──────────────────────────────────────────
    /** @var int[] */
    public array $selectedIds = [];

    // ── Modal: Entity-Zuweisung ─────────────────────────────────
    public ?int $editingProjectId = null;
    public ?int $newEntityId = null;
    public string $entitySearch = '';

    // ── Modal: Bulk-Delete-Bestaetigung ─────────────────────────
    public bool $confirmingBulkDelete = false;

    protected function projectsQuery()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        return PlannerProject::query()
            ->where('team_id', $team->id)
            ->visibleTo($user)
            ->with(['user:id,name', 'projectUsers:project_id,user_id']);
    }

    /**
     * Zieht die neuesten Snapshots fuer die aktuelle Team-Projektmenge —
     * damit wir Score/Farbe pro Projekt ohne N+1 zur Verfuegung haben.
     *
     * @return array<int, PlannerProjectSnapshot>  keyed by project_id
     */
    protected function latestSnapshotsByProjectId(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }
        $latestIds = DB::table('planner_project_snapshots as a')
            ->whereIn('a.project_id', $projectIds)
            ->whereRaw('a.taken_on = (
                SELECT MAX(b.taken_on) FROM planner_project_snapshots b
                WHERE b.project_id = a.project_id
            )')
            ->pluck('a.id');

        return PlannerProjectSnapshot::whereIn('id', $latestIds)
            ->get()
            ->keyBy('project_id')
            ->all();
    }

    /**
     * Fuer jedes Projekt: erster (primary or first) EntityLink samt Entity-Name.
     *
     * @return array<int, array{entity_id: int, entity_name: string}>  keyed by project_id
     */
    protected function primaryEntityLinkByProjectId(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }
        return OrganizationEntityLink::query()
            ->where('linkable_type', PlannerProject::class)
            ->whereIn('linkable_id', $projectIds)
            ->with('entity:id,name')
            ->get()
            ->groupBy('linkable_id')
            ->map(function ($links) {
                $primary = $links->first(fn ($l) => $l->is_primary) ?? $links->first();
                return [
                    'entity_id' => $primary->entity_id,
                    'entity_name' => $primary->entity?->name ?? '—',
                ];
            })
            ->all();
    }

    #[Computed]
    public function ownerOptions(): array
    {
        return $this->projectsQuery()
            ->select('user_id')
            ->distinct()
            ->with('user:id,name')
            ->get()
            ->pluck('user.name', 'user_id')
            ->filter()
            ->sort()
            ->all();
    }

    #[Computed]
    public function engagementOptions(): array
    {
        return OrganizationEntity::query()
            ->where('team_id', Auth::user()->currentTeam->id)
            ->where('entity_type_id', 37) // Engagement
            ->when($this->entitySearch !== '', fn ($q) => $q->where('name', 'like', '%' . $this->entitySearch . '%'))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, array>  Row-Data fuer die Tabelle
     */
    #[Computed]
    public function rows(): array
    {
        $projects = $this->projectsQuery()->get();
        if (! $this->includeDone) {
            $projects = $projects->reject(fn ($p) => (bool) $p->done);
        }

        $projectIds = $projects->pluck('id')->all();
        $snapshots = $this->latestSnapshotsByProjectId($projectIds);
        $entityLinks = $this->primaryEntityLinkByProjectId($projectIds);

        $rows = $projects->map(function ($p) use ($snapshots, $entityLinks) {
            $snap = $snapshots[$p->id] ?? null;
            $link = $entityLinks[$p->id] ?? null;
            $membersCount = max(0, $p->projectUsers->count());

            return [
                'id' => $p->id,
                'name' => $p->name ?: '—',
                'kind' => $p->kind,
                'status' => $p->status?->value ?? 'aktiv',
                'owner_id' => $p->user_id,
                'owner_name' => $p->user?->name ?? '—',
                'members_count' => $membersCount,
                'entity_id' => $link['entity_id'] ?? null,
                'entity_name' => $link['entity_name'] ?? null,
                'health_score' => $snap?->health_score,
                'health_color' => $snap?->health_color ?: 'gray',
                'tasks_open' => (int) ($snap?->tasks_open ?? 0),
                'tasks_overdue' => (int) ($snap?->tasks_overdue ?? 0),
                'tasks_frog' => (int) ($snap?->tasks_frog ?? 0),
                'last_viewed_at' => $p->last_viewed_at,
            ];
        })->all();

        // ── Filter ──
        $filtered = collect($rows);

        if ($this->colorFilter !== 'all') {
            $filtered = $filtered->filter(fn ($r) => $r['health_color'] === $this->colorFilter);
        }
        if ($this->statusFilter !== 'all') {
            $filtered = $filtered->filter(fn ($r) => $r['status'] === $this->statusFilter);
        }
        if ($this->ownerFilter) {
            $filtered = $filtered->filter(fn ($r) => (int) $r['owner_id'] === (int) $this->ownerFilter);
        }
        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $filtered = $filtered->filter(fn ($r) => str_contains(mb_strtolower($r['name']), $needle));
        }
        if (in_array('no_entity', $this->suspectFlags, true)) {
            $filtered = $filtered->filter(fn ($r) => empty($r['entity_id']));
        }
        if (in_array('no_owner', $this->suspectFlags, true)) {
            $filtered = $filtered->filter(fn ($r) => empty($r['owner_id']));
        }
        if (in_array('no_tasks', $this->suspectFlags, true)) {
            $filtered = $filtered->filter(fn ($r) => $r['tasks_open'] === 0 && $r['tasks_overdue'] === 0);
        }
        if (in_array('stale', $this->suspectFlags, true)) {
            $cutoff = now()->subDays(30);
            $filtered = $filtered->filter(fn ($r) => ! $r['last_viewed_at'] || $r['last_viewed_at']->lt($cutoff));
        }

        // ── Sort ──
        $sorted = match ($this->sort) {
            'score_asc' => $filtered->sortBy(fn ($r) => $r['health_score'] ?? 999)->values(),
            'last_view_desc' => $filtered->sortByDesc(fn ($r) => $r['last_viewed_at']?->timestamp ?? 0)->values(),
            'tasks_desc' => $filtered->sortByDesc(fn ($r) => $r['tasks_overdue'] * 3 + $r['tasks_open'])->values(),
            default => $filtered->sortBy(fn ($r) => mb_strtolower($r['name']))->values(),
        };

        return $sorted->all();
    }

    // ── Bulk-Selection ──────────────────────────────────────────

    public function toggleSelection(int $id): void
    {
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function selectAllVisible(): void
    {
        $this->selectedIds = array_map(fn ($r) => (int) $r['id'], $this->rows);
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    // ── Bulk-Aktionen ───────────────────────────────────────────

    public function askBulkDelete(): void
    {
        if (empty($this->selectedIds)) return;
        $this->confirmingBulkDelete = true;
    }

    public function cancelBulkDelete(): void
    {
        $this->confirmingBulkDelete = false;
    }

    public function confirmBulkDelete(): void
    {
        $team = Auth::user()->currentTeam;
        PlannerProject::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $this->selectedIds)
            ->get()
            ->each(fn ($p) => $p->delete()); // soft delete via Model-Event
        $this->selectedIds = [];
        $this->confirmingBulkDelete = false;
        unset($this->rows);
        session()->flash('cleanup_message', 'Ausgewaehlte Projekte gelöscht.');
    }

    public function bulkSetPassiv(): void
    {
        if (empty($this->selectedIds)) return;
        $team = Auth::user()->currentTeam;
        PlannerProject::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $this->selectedIds)
            ->update(['status' => \Platform\Planner\Enums\ProjectStatus::PASSIV->value]);
        $this->selectedIds = [];
        unset($this->rows);
        session()->flash('cleanup_message', 'Status auf Passiv gesetzt.');
    }

    // ── Entity-Zuweisung ────────────────────────────────────────

    public function openEntityModal(int $projectId): void
    {
        $this->editingProjectId = $projectId;
        $this->newEntityId = null;
        $this->entitySearch = '';
    }

    public function closeEntityModal(): void
    {
        $this->editingProjectId = null;
        $this->newEntityId = null;
        $this->entitySearch = '';
    }

    public function saveEntityChange(): void
    {
        if (! $this->editingProjectId || ! $this->newEntityId) {
            $this->closeEntityModal();
            return;
        }

        // Alle bestehenden EntityLinks des Projekts entfernen
        OrganizationEntityLink::query()
            ->where('linkable_type', PlannerProject::class)
            ->where('linkable_id', $this->editingProjectId)
            ->delete();

        // Neuen Link setzen
        OrganizationEntityLink::create([
            'linkable_type' => PlannerProject::class,
            'linkable_id' => $this->editingProjectId,
            'entity_id' => $this->newEntityId,
            'team_id' => Auth::user()->currentTeam->id,
            'is_primary' => true,
        ]);

        $this->closeEntityModal();
        unset($this->rows);
        session()->flash('cleanup_message', 'Entity-Zuordnung aktualisiert.');
    }

    // ── Render ──────────────────────────────────────────────────

    #[Layout('platform::layouts.app')]
    public function render()
    {
        return view('planner::livewire.projects-cleanup');
    }
}
