<?php

namespace Platform\Planner\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\DimensionLinkService;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Exceptions\InvalidLifecycleTransitionException;
use Platform\Planner\Services\ActivityClock;
use Platform\Planner\Services\LifecycleService;

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
    /** all|aktiv|ruhend|abgeschlossen|verworfen */
    public string $lifecycleFilter = 'aktiv';
    public ?int $ownerFilter = null;
    /** ['no_owner','no_entity','no_tasks','forgotten'] */
    public array $suspectFlags = [];
    public string $search = '';
    /** name|score_asc|last_view_desc|tasks_desc|forgotten_desc */
    public string $sort = 'name';

    // ── Bulk-Selection ──────────────────────────────────────────
    /** @var int[] */
    public array $selectedIds = [];

    // ── Modal: Entity-Zuweisung ─────────────────────────────────
    public ?int $editingProjectId = null;
    public ?int $newEntityId = null;
    public string $entitySearch = '';

    // ── Modal: Bulk-Delete-Bestaetigung ─────────────────────────
    public bool $confirmingBulkDelete = false;

    // ── Modal: Single-Delete-Bestaetigung ───────────────────────
    public ?int $deletingProjectId = null;
    public ?string $deletingProjectName = null;

    protected function projectsQuery()
    {
        $user = Auth::user();

        // Team-Scope wie bei den Engagement-Optionen: Root + alle Child-Teams.
        // Aufräumer sitzen ggf. in einem Sub-Team, die Projekte hängen aber am
        // Root-Team — sonst waeren sie hier unsichtbar.
        return PlannerProject::query()
            ->whereIn('team_id', $this->relevantTeamIds())
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
     * Summiert getrackte Minuten pro Projekt — direkte Buchungen am Projekt
     * plus Buchungen an dessen Tasks. Beruecksichtigt Morph-Aliase
     * (context_type kann sowohl "project"/"task" als auch der volle
     * Klassenname sein, je nachdem wer die Eintraege erstellt hat).
     *
     * @return array<int, int>  keyed by project_id → total minutes
     */
    protected function trackedMinutesByProjectId(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $projectAlias = DimensionLinkService::resolveContextType(PlannerProject::class);
        $taskAlias = DimensionLinkService::resolveContextType(PlannerTask::class);

        // Task-ID → project_id Mapping fuer alle relevanten Projekte
        $taskProjectMap = DB::table('planner_tasks')
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->pluck('project_id', 'id')
            ->all();
        $taskIds = array_keys($taskProjectMap);

        // Direkt am Projekt
        $projectTimes = OrganizationTimeEntry::query()
            ->whereIn('context_type', [$projectAlias, PlannerProject::class])
            ->whereIn('context_id', $projectIds)
            ->selectRaw('context_id, SUM(minutes) as total')
            ->groupBy('context_id')
            ->pluck('total', 'context_id')
            ->all();

        // An Tasks
        $taskTimes = [];
        if (! empty($taskIds)) {
            $taskTimes = OrganizationTimeEntry::query()
                ->whereIn('context_type', [$taskAlias, PlannerTask::class])
                ->whereIn('context_id', $taskIds)
                ->selectRaw('context_id, SUM(minutes) as total')
                ->groupBy('context_id')
                ->pluck('total', 'context_id')
                ->all();
        }

        $byProject = [];
        foreach ($projectIds as $pid) {
            $byProject[$pid] = (int) ($projectTimes[$pid] ?? 0);
        }
        foreach ($taskTimes as $taskId => $mins) {
            $pid = $taskProjectMap[$taskId] ?? null;
            if ($pid) {
                $byProject[$pid] = ($byProject[$pid] ?? 0) + (int) $mins;
            }
        }

        return $byProject;
    }

    /**
     * Fuer jedes Projekt: erster (primary or first) EntityLink samt Entity-Name.
     * Nutzt EntityDimensionBridge — die aktuelle Quelle der Wahrheit fuer
     * Entity-Verknuepfungen (organization_dimension_links). Die alte
     * PlannerProject::entityLinks() Relation ist deprecated.
     *
     * @return array<int, array{entity_id: int, entity_name: string}>  keyed by project_id
     */
    protected function primaryEntityLinkByProjectId(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        // Bridge speichert linkable_type als morph alias (z.B. "project"),
        // nicht als voller Klassenname → vorher aufloesen.
        $linkableType = DimensionLinkService::resolveContextType(PlannerProject::class);

        return EntityDimensionBridge::linksForLinkables(
                [$linkableType],
                $projectIds,
                true // with entity
            )
            ->groupBy('linkable_id')
            ->map(function ($linkGroup) {
                $primary = $linkGroup->first(fn ($l) => (bool) ($l->is_primary ?? false))
                    ?? $linkGroup->first();
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

    /**
     * Liefert alle Team-IDs im Baum unterhalb des Root-Teams des aktuellen
     * Nutzers — damit die Engagement-Auswahl nicht am Sub-Team hängen bleibt.
     */
    protected function relevantTeamIds(): array
    {
        $current = Auth::user()->currentTeamRelation;
        if (! $current) {
            return [];
        }
        $root = $current->getRootTeam();
        $ids = [$root->id];
        $walker = function (Team $team) use (&$walker, &$ids) {
            foreach ($team->childTeams()->get() as $child) {
                $ids[] = $child->id;
                $walker($child);
            }
        };
        $walker($root);
        return $ids;
    }

    #[Computed]
    public function engagementOptions(): array
    {
        return OrganizationEntity::query()
            ->whereIn('team_id', $this->relevantTeamIds())
            ->where('entity_type_id', 37) // Engagement
            ->where('is_active', true)
            ->when($this->entitySearch !== '', fn ($q) => $q->where('name', 'like', '%' . $this->entitySearch . '%'))
            ->orderBy('name')
            ->limit(200)
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

        // Default: only ACTIVE projects. Use lifecycleFilter to opt into
        // dormant/completed/discarded views.
        if ($this->lifecycleFilter !== 'all') {
            $projects = $projects->filter(
                fn ($p) => ($p->lifecycle_state?->value ?? 'aktiv') === $this->lifecycleFilter
            );
        }

        $projectIds = $projects->pluck('id')->all();
        $snapshots = $this->latestSnapshotsByProjectId($projectIds);
        $entityLinks = $this->primaryEntityLinkByProjectId($projectIds);
        $trackedMinutes = $this->trackedMinutesByProjectId($projectIds);
        $activityByProject = app(ActivityClock::class)->lastActivityForProjects($projectIds);
        $now = now();

        $rows = $projects->map(function ($p) use ($snapshots, $entityLinks, $trackedMinutes, $activityByProject, $now) {
            $snap = $snapshots[$p->id] ?? null;
            $link = $entityLinks[$p->id] ?? null;
            $membersCount = max(0, $p->projectUsers->count());

            // Layer-Status aus confidence_reason (Form "missing:canvas,planned_period,...")
            $missing = [];
            if ($snap && $snap->confidence_reason && str_starts_with($snap->confidence_reason, 'missing:')) {
                foreach (explode(',', substr($snap->confidence_reason, 8)) as $m) {
                    $missing[] = trim($m);
                }
            }
            $layerStatus = [
                'canvas' => $snap ? ! in_array('canvas', $missing, true) : false,
                'period' => $snap ? ! in_array('planned_period', $missing, true) : false,
                'minutes' => $snap ? ! in_array('planned_minutes', $missing, true) : false,
                'tasks' => $snap ? ! in_array('tasks', $missing, true) : false,
            ];

            // ActivityClock liefert die MAX-Aktivitaet aus allen Signalen.
            // Fallback: created_at — ein frisch angelegtes Projekt ohne
            // Interaktion soll nicht sofort als "vergessen" erscheinen.
            $lastActivity = $activityByProject[$p->id] ?? $p->created_at;
            $forgottenDays = $lastActivity ? (int) $now->diffInDays($lastActivity, absolute: true) : null;
            $forgottenBucket = match (true) {
                $forgottenDays === null => 'unknown',
                $forgottenDays < 14 => 'fresh',
                $forgottenDays < 30 => 'warm',
                $forgottenDays < 90 => 'cold',
                default => 'buried',
            };

            return [
                'id' => $p->id,
                'name' => $p->name ?: '—',
                'kind' => $p->kind,
                'lifecycle_state' => $p->lifecycle_state?->value ?? 'aktiv',
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
                'last_activity_at' => $lastActivity,
                'forgotten_days' => $forgottenDays,
                'forgotten_bucket' => $forgottenBucket,
                'layers' => $layerStatus,
                'tracked_minutes' => (int) ($trackedMinutes[$p->id] ?? 0),
            ];
        })->all();

        // ── Filter ──
        $filtered = collect($rows);

        if ($this->colorFilter !== 'all') {
            $filtered = $filtered->filter(fn ($r) => $r['health_color'] === $this->colorFilter);
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
        if (in_array('forgotten', $this->suspectFlags, true)) {
            $filtered = $filtered->filter(fn ($r) => in_array($r['forgotten_bucket'], ['cold', 'buried'], true));
        }

        // ── Sort ──
        $sorted = match ($this->sort) {
            'score_asc' => $filtered->sortBy(fn ($r) => $r['health_score'] ?? 999)->values(),
            'last_view_desc' => $filtered->sortByDesc(fn ($r) => $r['last_viewed_at']?->timestamp ?? 0)->values(),
            'tasks_desc' => $filtered->sortByDesc(fn ($r) => $r['tasks_overdue'] * 3 + $r['tasks_open'])->values(),
            'forgotten_desc' => $filtered->sortByDesc(fn ($r) => $r['forgotten_days'] ?? -1)->values(),
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
        $count = 0;
        foreach ($this->selectedIds as $id) {
            if ($this->performDelete((int) $id)) {
                $count++;
            }
        }
        $this->selectedIds = [];
        $this->confirmingBulkDelete = false;
        unset($this->rows);
        session()->flash('cleanup_message', "{$count} Projekt(e) inkl. Aufgaben, Slots, Canvases, Entity-Links und Zeiteintraegen gelöscht.");
    }

    // ── Einzel-Loeschen ─────────────────────────────────────────

    public function askDeleteSingle(int $projectId): void
    {
        $project = PlannerProject::query()
            ->where('team_id', Auth::user()->currentTeam->id)
            ->find($projectId);
        if (! $project) return;
        $this->deletingProjectId = $projectId;
        $this->deletingProjectName = $project->name;
    }

    public function cancelDeleteSingle(): void
    {
        $this->deletingProjectId = null;
        $this->deletingProjectName = null;
    }

    public function confirmDeleteSingle(): void
    {
        if (! $this->deletingProjectId) return;
        $ok = $this->performDelete((int) $this->deletingProjectId);
        $name = $this->deletingProjectName;
        $this->deletingProjectId = null;
        $this->deletingProjectName = null;
        unset($this->rows);
        session()->flash('cleanup_message', $ok
            ? "Projekt '{$name}' inkl. Aufgaben, Slots, Canvases, Entity-Links und Zeiteintraegen gelöscht."
            : "Projekt konnte nicht gelöscht werden.");
    }

    /**
     * Raeumt ein Projekt komplett auf: Dimension-Links, Canvas + Blocks +
     * Entries, Time-Entries (an Projekt und an dessen Tasks), Slots + Tasks
     * (per Cascade des Projekt-Deletes). Rueckgabe: true bei Erfolg.
     */
    protected function performDelete(int $projectId): bool
    {
        $project = PlannerProject::query()
            ->whereIn('team_id', $this->relevantTeamIds())
            ->find($projectId);
        if (! $project) {
            return false;
        }

        $linkableType = DimensionLinkService::resolveContextType(PlannerProject::class);
        $taskAlias = DimensionLinkService::resolveContextType(PlannerTask::class);

        // 1) Dimension-Links entfernen (per Bridge, damit Cache konsistent bleibt)
        $links = EntityDimensionBridge::linksForLinkables([$linkableType], [$projectId], false);
        foreach ($links as $link) {
            if ($link->entity_id) {
                EntityDimensionBridge::deleteLink((int) $link->entity_id, $linkableType, $projectId);
            }
        }
        EntityDimensionBridge::flush();

        // 2) Task-IDs zur weiteren Aufraeumarbeit einsammeln
        $taskIds = DB::table('planner_tasks')
            ->where('project_id', $projectId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        // 3) Time-Entries loeschen — sowohl direkt am Projekt als auch an den Tasks
        OrganizationTimeEntry::query()
            ->whereIn('context_type', [$linkableType, PlannerProject::class])
            ->where('context_id', $projectId)
            ->delete();
        if (! empty($taskIds)) {
            OrganizationTimeEntry::query()
                ->whereIn('context_type', [$taskAlias, PlannerTask::class])
                ->whereIn('context_id', $taskIds)
                ->delete();
        }

        // 4) Planner-Canvas loeschen (Cascade auf Blocks/Entries erwartet)
        PlannerProjectCanvas::where('project_id', $projectId)->delete();

        // 5) Projekt loeschen — Slots + Tasks folgen per Cascade
        $project->delete();

        return true;
    }

    public function bulkComplete(): void
    {
        if (empty($this->selectedIds)) return;
        [$ok, $skipped] = $this->applyLifecycle('complete');
        $this->selectedIds = [];
        unset($this->rows);
        session()->flash('cleanup_message', "{$ok} Projekt(e) abgeschlossen"
            . ($skipped > 0 ? " ({$skipped} bereits Endzustand)" : '')
            . '.');
    }

    public function bulkDiscard(): void
    {
        if (empty($this->selectedIds)) return;
        [$ok, $skipped] = $this->applyLifecycle('discard');
        $this->selectedIds = [];
        unset($this->rows);
        session()->flash('cleanup_message', "{$ok} Projekt(e) verworfen (offene Tasks kaskadiert)"
            . ($skipped > 0 ? " — {$skipped} übersprungen" : '')
            . '.');
    }

    // ── Single lifecycle actions ────────────────────────────────

    public function complete(int $projectId): void
    {
        $project = $this->loadOwnProject($projectId);
        if (! $project) return;
        try {
            app(LifecycleService::class)->complete($project);
            session()->flash('cleanup_message', "'{$project->name}' abgeschlossen.");
        } catch (InvalidLifecycleTransitionException $e) {
            session()->flash('cleanup_message', "Nicht möglich: {$e->getMessage()}");
        }
        unset($this->rows);
    }

    public function discard(int $projectId): void
    {
        $project = $this->loadOwnProject($projectId);
        if (! $project) return;
        try {
            app(LifecycleService::class)->discard($project);
            session()->flash('cleanup_message', "'{$project->name}' verworfen (offene Tasks kaskadiert).");
        } catch (InvalidLifecycleTransitionException $e) {
            session()->flash('cleanup_message', "Nicht möglich: {$e->getMessage()}");
        }
        unset($this->rows);
    }

    public function reopen(int $projectId): void
    {
        $project = $this->loadOwnProject($projectId);
        if (! $project) return;
        try {
            app(LifecycleService::class)->reopen($project);
            session()->flash('cleanup_message', "'{$project->name}' wieder aktiviert.");
        } catch (InvalidLifecycleTransitionException $e) {
            session()->flash('cleanup_message', "Nicht möglich: {$e->getMessage()}");
        }
        unset($this->rows);
    }

    public function revive(int $projectId): void
    {
        $project = $this->loadOwnProject($projectId);
        if (! $project) return;
        try {
            app(LifecycleService::class)->revive($project);
            session()->flash('cleanup_message', "'{$project->name}' zurückgeholt.");
        } catch (InvalidLifecycleTransitionException $e) {
            session()->flash('cleanup_message', "Nicht möglich: {$e->getMessage()}");
        }
        unset($this->rows);
    }

    /**
     * Applies a lifecycle verb to the current selection, swallowing
     * per-item exceptions and counting outcomes.
     *
     * @return array{0:int,1:int}  [ok, skipped]
     */
    protected function applyLifecycle(string $verb): array
    {
        $lifecycle = app(LifecycleService::class);
        $projects = PlannerProject::query()
            ->whereIn('team_id', $this->relevantTeamIds())
            ->whereIn('id', $this->selectedIds)
            ->get();
        $ok = 0;
        $skipped = 0;
        foreach ($projects as $project) {
            try {
                $lifecycle->{$verb}($project);
                $ok++;
            } catch (InvalidLifecycleTransitionException) {
                $skipped++;
            }
        }
        return [$ok, $skipped];
    }

    protected function loadOwnProject(int $projectId): ?PlannerProject
    {
        return PlannerProject::query()
            ->whereIn('team_id', $this->relevantTeamIds())
            ->find($projectId);
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

        // Bridge kuemmert sich um das richtige Loeschen der alten dimension_links
        // und um das Anlegen des neuen.
        EntityDimensionBridge::replaceLinks(
            PlannerProject::class,
            (int) $this->editingProjectId,
            (int) $this->newEntityId,
        );
        EntityDimensionBridge::flush();

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
