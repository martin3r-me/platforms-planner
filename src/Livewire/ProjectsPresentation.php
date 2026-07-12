<?php

namespace Platform\Planner\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\DimensionLinkService;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerTask;

/**
 * Praesentationsmodus — mit dem Kunden die laufenden Projekte durchgehen.
 *
 * Einstieg ueber ein Engagement (der Kunde). Danach eine ruhige, kundensichere
 * Slide-Sicht: ein Projekt gross, durchklickbar. Bewusst OHNE die internen
 * Cockpit-Signale (Ampel, Health-Score, "vergessen", "frog") — hier zaehlt
 * Substanz: Ziel/Umfang/Meilensteine aus dem Canvas, offene Aufgaben samt DoDs,
 * und geplante vs. investierte Zeit.
 */
class ProjectsPresentation extends Component
{
    /** Ausgewaehltes Engagement (Kunde). Null = Auswahl-Screen. */
    #[Url(as: 'e')]
    public ?int $engagementId = null;

    /** Index des aktuellen Projekt-Slides innerhalb des Engagements. */
    public int $index = 0;

    /** Suche im Engagement-Auswahl-Screen. */
    public string $search = '';

    /** Canvas-Bloecke, die als "wesentlich" auf den Slide kommen (in Reihenfolge). */
    protected array $canvasBlocks = [
        'project_goal' => 'Ziel',
        'scope'        => 'Umfang',
        'milestones'   => 'Meilensteine',
    ];

    // ── Navigation ──────────────────────────────────────────────

    public function selectEngagement(int $engagementId): void
    {
        $this->engagementId = $engagementId;
        $this->index = 0;
    }

    public function exitPresentation(): void
    {
        $this->engagementId = null;
        $this->index = 0;
    }

    public function next(): void
    {
        $max = max(0, count($this->slides) - 1);
        $this->index = min($this->index + 1, $max);
    }

    public function prev(): void
    {
        $this->index = max($this->index - 1, 0);
    }

    public function goTo(int $i): void
    {
        $max = max(0, count($this->slides) - 1);
        $this->index = max(0, min($i, $max));
    }

    // ── Scope-Helfer ────────────────────────────────────────────

    /**
     * Alle Team-IDs im Baum unterhalb des Root-Teams — damit ein Praesentator
     * in einem Sub-Team trotzdem alle Engagements/Projekte sieht.
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

    /**
     * Nicht-terminale ("laufende") Projekte im Scope: aktiv + ruhend,
     * ohne abgeschlossen/verworfen.
     */
    protected function runningProjectsQuery()
    {
        return PlannerProject::query()
            ->whereIn('team_id', $this->relevantTeamIds())
            ->visibleTo(Auth::user());
    }

    protected function isRunning(PlannerProject $p): bool
    {
        $state = $p->lifecycle_state;
        return $state === null || ! $state->isTerminal();
    }

    // ── Engagement-Auswahl ──────────────────────────────────────

    /**
     * Engagements, die mindestens ein laufendes Projekt tragen — inkl. Anzahl.
     * So sieht der Praesentator direkt, wo ueberhaupt etwas durchzusprechen ist.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    #[Computed]
    public function engagements(): array
    {
        $running = $this->runningProjectsQuery()
            ->get(['id', 'lifecycle_state', 'name'])
            ->filter(fn ($p) => $this->isRunning($p));

        $projectIds = $running->pluck('id')->all();
        if (empty($projectIds)) {
            return [];
        }

        $linkableType = DimensionLinkService::resolveContextType(PlannerProject::class);
        $links = EntityDimensionBridge::linksForLinkables([$linkableType], $projectIds, true);

        $byEntity = [];
        foreach ($links as $link) {
            $entityId = (int) ($link->entity_id ?? 0);
            if ($entityId === 0) {
                continue;
            }
            if (! isset($byEntity[$entityId])) {
                $byEntity[$entityId] = [
                    'id'    => $entityId,
                    'name'  => $link->entity?->name ?? '—',
                    'count' => 0,
                ];
            }
            $byEntity[$entityId]['count']++;
        }

        $list = array_values($byEntity);

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $list = array_values(array_filter(
                $list,
                fn ($e) => str_contains(mb_strtolower($e['name']), $needle)
            ));
        }

        usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    /** Name des aktiven Engagements (fuer den Kopf der Praesentation). */
    #[Computed]
    public function engagementName(): ?string
    {
        if (! $this->engagementId) {
            return null;
        }
        foreach ($this->engagements as $e) {
            if ($e['id'] === $this->engagementId) {
                return $e['name'];
            }
        }
        // Fallback: Engagement ohne laufende Projekte (direkt via URL angesprungen)
        return \Platform\Organization\Models\OrganizationEntity::query()
            ->whereKey($this->engagementId)
            ->value('name');
    }

    // ── Slides ──────────────────────────────────────────────────

    /**
     * Ein Slide je laufendem Projekt des gewaehlten Engagements.
     *
     * @return array<int, array>
     */
    #[Computed]
    public function slides(): array
    {
        if (! $this->engagementId) {
            return [];
        }

        $linkableType = DimensionLinkService::resolveContextType(PlannerProject::class);
        $projectIds = EntityDimensionBridge::linksForEntity($this->engagementId)
            ->filter(fn ($l) => ($l->linkable_type ?? null) === $linkableType)
            ->pluck('linkable_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($projectIds)) {
            return [];
        }

        $projects = $this->runningProjectsQuery()
            ->whereIn('id', $projectIds)
            ->with(['user:id,name', 'tasks'])
            ->orderBy('name')
            ->get()
            ->filter(fn ($p) => $this->isRunning($p))
            ->values();

        $liveIds = $projects->pluck('id')->all();
        $loggedMap = $this->loggedMinutesByProjectId($liveIds);
        $plannedMap = $this->plannedMinutesByProjectId($liveIds);

        return $projects->map(fn ($p) => $this->buildSlide(
            $p,
            (int) ($plannedMap[$p->id] ?? 0),
            (int) ($loggedMap[$p->id] ?? 0),
        ))->all();
    }

    /**
     * Summiert getrackte Minuten pro Projekt — direkt am Projekt plus an dessen
     * Tasks. Beruecksichtigt beide Morph-Schreibweisen (Alias wie "project" und
     * voller Klassenname), weil Zeit-Eintraege je nach Erzeuger unterschiedlich
     * gespeichert sind. Ohne diese Doppelabfrage bleiben Summen faelschlich 0.
     * Deckungsgleich mit der Cleanup-Sicht.
     *
     * @return array<int, int>  project_id → minutes
     */
    protected function loggedMinutesByProjectId(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $projectAlias = DimensionLinkService::resolveContextType(PlannerProject::class);
        $taskAlias = DimensionLinkService::resolveContextType(PlannerTask::class);

        $taskProjectMap = DB::table('planner_tasks')
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->pluck('project_id', 'id')
            ->all();
        $taskIds = array_keys($taskProjectMap);

        $projectTimes = OrganizationTimeEntry::query()
            ->whereIn('context_type', [$projectAlias, PlannerProject::class])
            ->whereIn('context_id', $projectIds)
            ->selectRaw('context_id, SUM(minutes) as total')
            ->groupBy('context_id')
            ->pluck('total', 'context_id')
            ->all();

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
     * Summiert geplante Minuten pro Projekt — gleiche Dual-Context-Logik wie
     * beim Ist, nur gegen die Planungs-Tabelle und auf aktive Eintraege.
     *
     * @return array<int, int>  project_id → minutes
     */
    protected function plannedMinutesByProjectId(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $projectAlias = DimensionLinkService::resolveContextType(PlannerProject::class);
        $taskAlias = DimensionLinkService::resolveContextType(PlannerTask::class);

        $taskProjectMap = DB::table('planner_tasks')
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->pluck('project_id', 'id')
            ->all();
        $taskIds = array_keys($taskProjectMap);

        $projectTimes = OrganizationTimePlanned::query()
            ->where('is_active', true)
            ->whereIn('context_type', [$projectAlias, PlannerProject::class])
            ->whereIn('context_id', $projectIds)
            ->selectRaw('context_id, SUM(planned_minutes) as total')
            ->groupBy('context_id')
            ->pluck('total', 'context_id')
            ->all();

        $taskTimes = [];
        if (! empty($taskIds)) {
            $taskTimes = OrganizationTimePlanned::query()
                ->where('is_active', true)
                ->whereIn('context_type', [$taskAlias, PlannerTask::class])
                ->whereIn('context_id', $taskIds)
                ->selectRaw('context_id, SUM(planned_minutes) as total')
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

    protected function buildSlide(PlannerProject $project, int $plannedMinutes, int $loggedMinutes): array
    {
        $tasks = $project->tasks;
        $activeTasks = $tasks->filter(
            fn ($t) => $t->lifecycle_state === TaskLifecycleState::ACTIVE
        )->values();

        // ── Offene Aufgaben samt DoD-Kriterien ──
        $dodTotal = 0;
        $dodChecked = 0;
        $taskRows = $activeTasks->map(function ($t) use (&$dodTotal, &$dodChecked) {
            $progress = $t->dod_progress;
            $dodTotal += $progress['total'];
            $dodChecked += $progress['checked'];

            $openItems = array_values(array_filter(
                $t->dod_items,
                fn ($item) => ! $item['checked']
            ));

            return [
                'title'      => $t->title ?: '—',
                'open_items' => array_map(fn ($i) => $i['text'], $openItems),
                'total'      => $progress['total'],
                'checked'    => $progress['checked'],
            ];
        })->all();

        return [
            'id'              => $project->id,
            'name'            => $project->name ?: '—',
            'owner_name'      => $project->user?->name,
            'canvas'          => $this->canvasHighlights($project->id),
            'tasks'           => $taskRows,
            'open_task_count' => $activeTasks->count(),
            'dod_total'       => $dodTotal,
            'dod_checked'     => $dodChecked,
            'planned_minutes' => (int) $plannedMinutes,
            'logged_minutes'  => (int) $loggedMinutes,
            'url'             => route('planner.projects.show', $project->id),
        ];
    }

    /**
     * Zieht die "wesentlichen" Canvas-Inhalte fuer den Slide: die ersten Eintraege
     * der Kern-Bloecke (Ziel, Umfang, Meilensteine) aus dem aktuellen Canvas.
     *
     * @return array<int, array{label:string, type:string, entries:array}>
     */
    protected function canvasHighlights(int $projectId): array
    {
        $canvas = PlannerProjectCanvas::query()
            ->where('project_id', $projectId)
            ->where('status', '!=', PlannerProjectCanvas::STATUS_DISCARDED)
            ->visibleTo(Auth::user())
            ->with(['blocks' => fn ($q) => $q->orderBy('position'), 'blocks.entries'])
            ->latest()
            ->first();

        if (! $canvas) {
            return [];
        }

        $out = [];
        foreach ($this->canvasBlocks as $type => $label) {
            $block = $canvas->blocks->firstWhere('block_type', $type);
            if (! $block) {
                continue;
            }
            $entries = $block->entries
                ->take(2)
                ->map(fn ($e) => [
                    'title'   => $e->title,
                    'content' => $e->content ? Str::limit(strip_tags($e->content), 180) : null,
                ])
                ->filter(fn ($e) => $e['title'] || $e['content'])
                ->values()
                ->all();

            if (empty($entries)) {
                continue;
            }
            $out[] = ['label' => $label, 'type' => $type, 'entries' => $entries];
        }

        return $out;
    }

    // ── Render ──────────────────────────────────────────────────

    #[Layout('platform::layouts.app')]
    public function render()
    {
        return view('planner::livewire.projects-presentation');
    }
}
