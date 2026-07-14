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
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimePeriod;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\DimensionLinkService;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Exceptions\InvalidLifecycleTransitionException;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\LifecycleService;

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

    /**
     * Positions-Modell: Index 0 = Engagement-Ueberblick, Index 1..n = Projekte.
     * Deshalb ist die Obergrenze count(slides), nicht count-1.
     */
    public function next(): void
    {
        $this->index = min($this->index + 1, count($this->slides));
    }

    public function prev(): void
    {
        $this->index = max($this->index - 1, 0);
    }

    public function goTo(int $i): void
    {
        $this->index = max(0, min($i, count($this->slides)));
    }

    // ── Live-Aktionen: Aufgabe/DoD abhaken ──────────────────────

    /**
     * Aufgabe im Scope laden (Autorisierung ueber den Team-Baum). Null, wenn
     * die Aufgabe nicht existiert oder nicht zum sichtbaren Projekt-Set gehoert.
     */
    protected function loadScopedTask(int $taskId): ?PlannerTask
    {
        $task = PlannerTask::query()->whereKey($taskId)->first();
        if (! $task) {
            return null;
        }
        $inScope = PlannerProject::query()
            ->whereKey($task->project_id)
            ->whereIn('team_id', $this->relevantTeamIds())
            ->visibleTo(Auth::user())
            ->exists();
        return $inScope ? $task : null;
    }

    /**
     * Hakt eine Aufgabe live ab bzw. oeffnet sie wieder — je nach Zustand.
     * Laeuft ueber den LifecycleService (Zustand + Zeitstempel, Wiederkehrer).
     */
    public function toggleTaskDone(int $taskId): void
    {
        $task = $this->loadScopedTask($taskId);
        if (! $task) {
            return;
        }

        $lifecycle = app(LifecycleService::class);
        try {
            if ($task->lifecycle_state === TaskLifecycleState::COMPLETED) {
                $lifecycle->reopenTask($task);
            } elseif ($task->lifecycle_state === TaskLifecycleState::ACTIVE) {
                $lifecycle->completeTask($task);
            }
        } catch (InvalidLifecycleTransitionException) {
            // z.B. verworfen — stiller No-op.
        }

        unset($this->slides, $this->overview);
    }

    /**
     * Schaltet ein einzelnes DoD-Kriterium einer (aktiven) Aufgabe um und
     * persistiert es im verschluesselten JSON-Feld. Erledigte Aufgaben werden
     * nicht angetastet (dort gelten alle Kriterien als erfuellt).
     */
    public function toggleDodItem(int $taskId, int $index): void
    {
        $task = $this->loadScopedTask($taskId);
        if (! $task || $task->lifecycle_state !== TaskLifecycleState::ACTIVE) {
            return;
        }

        $items = array_values($task->dod_items);
        if (! isset($items[$index])) {
            return;
        }
        $items[$index]['checked'] = ! ($items[$index]['checked'] ?? false);

        // Gleiches Format wie der Task-Editor: JSON [{text, checked}], leere raus.
        $clean = array_values(array_filter(
            array_map(fn ($i) => ['text' => trim($i['text'] ?? ''), 'checked' => (bool) ($i['checked'] ?? false)], $items),
            fn ($i) => $i['text'] !== ''
        ));
        $task->dod = empty($clean) ? '' : json_encode($clean, JSON_UNESCAPED_UNICODE);
        $task->save();

        unset($this->slides, $this->overview);
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

        // Jede verlinkte Entity auf ihr Engagement aufloesen: haengt ein Projekt an
        // einer Initiative, zaehlt es zum Engagement DARUEBER — nicht die Initiative
        // selbst als "Engagement" listen. Fallback: die Entity selbst.
        $entityMap = $this->loadEntityChain(
            $links->pluck('entity_id')->filter()->map(fn ($i) => (int) $i)->unique()->all()
        );

        $byEntity = [];
        foreach ($links as $link) {
            $entityId = (int) ($link->entity_id ?? 0);
            if ($entityId === 0) {
                continue;
            }
            [$engId, $engName] = $this->resolveEngagementId($entityId, $entityMap);
            if (! isset($byEntity[$engId])) {
                $byEntity[$engId] = [
                    'id'       => $engId,
                    'name'     => $engName,
                    'projects' => [],
                ];
            }
            // Projekt-ID als Key → distinkt zaehlen (direkt + via Initiative).
            $byEntity[$engId]['projects'][(int) $link->linkable_id] = true;
        }

        foreach ($byEntity as &$row) {
            $row['count'] = count($row['projects']);
            unset($row['projects']);
        }
        unset($row);

        // Bezuege (Venture/Kunde) aller Engagements batchweise nachladen
        $relationsByEngagement = $this->entityLinksForEngagements(array_keys($byEntity));
        foreach ($byEntity as $id => &$row) {
            $row['links'] = $relationsByEngagement[$id] ?? [];
        }
        unset($row);

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

    /**
     * Alle Nachfahren-Entities eines Wurzelknotens (beliebige Tiefe, BFS). Damit
     * unter einem Engagement ALLES gefunden wird, was irgendwo darunter haengt —
     * nicht nur direkte Kinder (Engagement → Programm → Initiative → Projekt).
     * Bounded gegen tiefe/zyklische Baeume.
     *
     * @return \Illuminate\Support\Collection<int, OrganizationEntity>
     */
    protected function descendantEntities(int $rootId): Collection
    {
        $all = collect();
        $frontier = [$rootId];
        $guard = 0;
        while (! empty($frontier) && $guard++ < 15) {
            $children = OrganizationEntity::query()
                ->whereIn('parent_entity_id', $frontier)
                ->with('type:id,code,name')
                ->get(['id', 'name', 'entity_type_id', 'parent_entity_id']);
            if ($children->isEmpty()) {
                break;
            }
            $all = $all->concat($children);
            $frontier = $children->pluck('id')
                ->map(fn ($i) => (int) $i)
                ->reject(fn ($i) => $i === $rootId)
                ->all();
        }

        return $all->unique('id')->values();
    }

    /**
     * Laedt eine Entity-Menge samt Vorfahrenkette (id → code/parent/name), damit
     * sich ein Linkable-Anker (z.B. eine Initiative) auf sein Engagement aufloesen
     * laesst. Bounded gegen tiefe/zyklische Baeume.
     *
     * @return array<int, array{id:int, name:?string, code:?string, parent:?int}>
     */
    protected function loadEntityChain(array $ids): array
    {
        $map = [];
        $toLoad = array_values(array_unique(array_map('intval', $ids)));
        $guard = 0;
        while (! empty($toLoad) && $guard++ < 12) {
            $rows = OrganizationEntity::query()
                ->whereIn('id', $toLoad)
                ->with('type:id,code')
                ->get(['id', 'name', 'entity_type_id', 'parent_entity_id']);
            $toLoad = [];
            foreach ($rows as $e) {
                if (isset($map[(int) $e->id])) {
                    continue;
                }
                $parent = $e->parent_entity_id ? (int) $e->parent_entity_id : null;
                $map[(int) $e->id] = [
                    'id'     => (int) $e->id,
                    'name'   => $e->name,
                    'code'   => $e->type?->code,
                    'parent' => $parent,
                ];
                if ($parent && ! isset($map[$parent])) {
                    $toLoad[] = $parent;
                }
            }
        }

        return $map;
    }

    /**
     * Loest eine Entity auf ihr Engagement auf (Typ-Code 'engagement'): erst die
     * Entity selbst, dann die Elternkette hoch. Fallback: die Entity selbst, damit
     * nie ein Engagement aus der Auswahl verschwindet.
     *
     * @param  array<int, array{id:int, name:?string, code:?string, parent:?int}>  $map
     * @return array{0:int, 1:string}  [engagementId, engagementName]
     */
    protected function resolveEngagementId(int $entityId, array $map): array
    {
        $cur = $entityId;
        $guard = 0;
        while ($cur && $guard++ < 12) {
            $node = $map[$cur] ?? null;
            if (! $node) {
                break;
            }
            if (($node['code'] ?? null) === 'engagement') {
                return [$node['id'], $node['name'] ?? '—'];
            }
            $cur = $node['parent'];
        }

        return [$entityId, $map[$entityId]['name'] ?? '—'];
    }

    /**
     * Loest die Entity-Bezuege eines Engagements ueber Dimension-Links auf:
     * das Engagement ist selbst Linkable, seine entity-basierten Dimension-Links
     * zeigen auf Venture- und Kunden-Entities. Rueckgabe je Engagement eine Liste
     * aus [label (Dimensionsname, z.B. "Venture"/"Kunde"), name (Entity-Name), key].
     * Batch — ein Query fuer alle Engagements, kein N+1.
     *
     * @return array<int, array<int, array{label:string, key:string, name:string}>>
     */
    protected function entityLinksForEngagements(array $engagementIds): array
    {
        if (empty($engagementIds)) {
            return [];
        }

        // Engagement kann als Alias ODER voller Klassenname als linkable liegen —
        // beide Schreibweisen abdecken (analog zur Zeit-Aggregation).
        $entityTypes = array_values(array_unique([
            DimensionLinkService::resolveContextType(OrganizationEntity::class),
            OrganizationEntity::class,
        ]));

        $links = EntityDimensionBridge::linksForLinkables($entityTypes, $engagementIds, true);
        if ($links->isEmpty()) {
            return [];
        }

        $definitions = OrganizationDimensionDefinition::query()
            ->whereIn('id', $links->pluck('dimension_definition_id')->unique()->all())
            ->get(['id', 'key', 'name'])
            ->keyBy('id');

        $byEngagement = [];
        foreach ($links as $link) {
            if (! $link->entity_id) {
                continue;
            }
            $def = $definitions->get($link->dimension_definition_id);
            $byEngagement[(int) $link->linkable_id][] = [
                'label' => $def?->name ?? 'Bezug',
                'key'   => $def?->key ?? '',
                'name'  => $link->entity?->name ?? '—',
            ];
        }

        return $byEngagement;
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
        return OrganizationEntity::query()
            ->whereKey($this->engagementId)
            ->value('name');
    }

    /**
     * Entity-Bezuege (Venture/Kunde) des aktiven Engagements — fuer den
     * Ueberblick-Kopf.
     *
     * @return array<int, array{label:string, key:string, name:string}>
     */
    #[Computed]
    public function engagementLinks(): array
    {
        if (! $this->engagementId) {
            return [];
        }
        return $this->entityLinksForEngagements([$this->engagementId])[$this->engagementId] ?? [];
    }

    /**
     * Aggregat ueber alle laufenden Projekte des Engagements — speist den
     * Ueberblick-Slide (Position 0).
     */
    #[Computed]
    public function overview(): array
    {
        $slides = $this->slides;

        $planned = $logged = $openTasks = $overdue = $dodTotal = $dodChecked = 0;
        $spTotal = $spDone = 0;
        $healthMix = ['green' => 0, 'yellow' => 0, 'red' => 0, 'gray' => 0];
        $nearest = null;
        $groups = [];

        foreach ($slides as $s) {
            if (! empty($s['bracket'])) {
                $groups[$s['bracket']['id']] = $s['bracket']['name'];
            }
            $planned += $s['planned_minutes'];
            $logged += $s['logged_minutes'];
            $openTasks += $s['open_task_count'];
            $overdue += $s['overdue_count'];
            $dodTotal += $s['dod_total'];
            $dodChecked += $s['dod_checked'];
            $spTotal += $s['sp_total'];
            $spDone += $s['sp_done'];
            $hc = $s['health_color'] ?: 'gray';
            $healthMix[$hc] = ($healthMix[$hc] ?? 0) + 1;

            if ($s['days_to_end'] !== null && ($nearest === null || $s['days_to_end'] < $nearest['days'])) {
                $nearest = ['days' => $s['days_to_end'], 'date' => $s['planned_end'], 'name' => $s['name']];
            }
        }

        return [
            'project_count'    => count($slides),
            'group_count'      => count($groups),
            'planned_minutes'  => $planned,
            'logged_minutes'   => $logged,
            'open_tasks'       => $openTasks,
            'overdue'          => $overdue,
            'dod_total'        => $dodTotal,
            'dod_checked'      => $dodChecked,
            'sp_total'         => $spTotal,
            'sp_done'          => $spDone,
            'health_mix'       => $healthMix,
            'nearest_deadline' => $nearest,
            'links'            => $this->engagementLinks,
        ];
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

        // Alle Nachfahren-Entities des Engagements (Initiativen/Programme/Container,
        // beliebige Tiefe), die Projekte buendeln. Deren Projekte werden mitgezeigt
        // und unter ihrem Anker gruppiert — nicht nur, was DIREKT am Engagement haengt.
        $anchors = $this->descendantEntities($this->engagementId);

        // Ein Batch-Query: Projekt-Links am Engagement UND an allen Nachfahren.
        $entityIds = array_merge(
            [$this->engagementId],
            $anchors->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $projectsByEntity = [];
        foreach (EntityDimensionBridge::linksForEntities($entityIds) as $link) {
            if (($link->linkable_type ?? null) !== $linkableType) {
                continue;
            }
            $eid = (int) ($link->entity_id ?? 0);
            $pid = (int) $link->linkable_id;
            if ($eid && $pid) {
                $projectsByEntity[$eid][] = $pid;
            }
        }

        // Jede Entity im Teilbaum schnell greifbar machen (fuer die Bracket-Aufloesung).
        $entityById = [];
        foreach ($anchors as $e) {
            $entityById[(int) $e->id] = [
                'id'     => (int) $e->id,
                'name'   => $e->name ?: '—',
                'type'   => $e->type?->name ?? 'Initiative',
                'parent' => $e->parent_entity_id ? (int) $e->parent_entity_id : null,
            ];
        }

        // Bracket = oberste Entity unter dem Engagement im Anker-Pfad: der Container
        // (Thema), oder — wenn keiner dazwischenliegt — die Initiative selbst. Das ist
        // die Gruppen-Klammer fuer Rail/Ueberblick. Der Anker bleibt die Initiative,
        // an der das Projekt direkt haengt (Breadcrumb-Feinheit).
        $bracketOf = function (int $anchorId) use ($entityById): ?array {
            $cur = $anchorId;
            $guard = 0;
            while ($cur && $guard++ < 15) {
                $node = $entityById[$cur] ?? null;
                if (! $node) {
                    return null;
                }
                // Elternteil ausserhalb des Teilbaums → cur haengt direkt am Engagement.
                if ($node['parent'] === null || ! isset($entityById[$node['parent']])) {
                    return ['id' => $node['id'], 'name' => $node['name'], 'type' => $node['type']];
                }
                $cur = $node['parent'];
            }
            return null;
        };

        // Reihenfolge: Projekte je Anker-Initiative unter ihrem Bracket; lose Projekte
        // (direkt am Engagement) zuletzt. Ein Projekt nur EINMAL (Anker vor "lose").
        $ordered = [];
        $seen = [];
        foreach ($anchors->sortBy('name')->values() as $ce) {
            $aid = (int) $ce->id;
            $anchor = [
                'id'   => $aid,
                'name' => $ce->name ?: '—',
                'type' => $ce->type?->name ?? 'Initiative',
            ];
            $bracket = $bracketOf($aid);
            foreach (array_unique($projectsByEntity[$aid] ?? []) as $pid) {
                if (isset($seen[$pid])) {
                    continue;
                }
                $seen[$pid] = true;
                $ordered[] = ['pid' => $pid, 'anchor' => $anchor, 'bracket' => $bracket];
            }
        }
        foreach (array_unique($projectsByEntity[$this->engagementId] ?? []) as $pid) {
            if (isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $ordered[] = ['pid' => $pid, 'anchor' => null, 'bracket' => null];
        }

        $projectIds = array_column($ordered, 'pid');
        if (empty($projectIds)) {
            return [];
        }

        $projects = $this->runningProjectsQuery()
            ->whereIn('id', $projectIds)
            ->with(['user:id,name', 'tasks'])
            ->get()
            ->filter(fn ($p) => $this->isRunning($p))
            ->keyBy('id');

        // Sortierung: nach Bracket (Thema) alphabetisch, lose Projekte ganz ans Ende;
        // innerhalb eines Brackets nach Projektname.
        usort($ordered, function ($a, $b) use ($projects) {
            // 1. nach Bracket (Thema), lose zuletzt
            $ba = $a['bracket']['name'] ?? "\u{FFFF}";
            $bb = $b['bracket']['name'] ?? "\u{FFFF}";
            if (($cmp = strcasecmp($ba, $bb)) !== 0) {
                return $cmp;
            }
            // 2. innerhalb eines Brackets Projekte gleicher Initiative zusammenhalten
            $aa = $a['anchor']['name'] ?? "\u{FFFF}";
            $ab = $b['anchor']['name'] ?? "\u{FFFF}";
            if (($cmp = strcasecmp($aa, $ab)) !== 0) {
                return $cmp;
            }
            // 3. dann nach Projektname
            return strcasecmp(
                $projects->get($a['pid'])?->name ?? '',
                $projects->get($b['pid'])?->name ?? ''
            );
        });

        $liveIds = $projects->keys()->all();
        $loggedMap = $this->loggedMinutesByProjectId($liveIds);
        $plannedMap = $this->plannedMinutesByProjectId($liveIds);
        $periodMap = $this->plannedEndByProjectId($liveIds);
        $snapshots = $this->latestSnapshotsByProjectId($liveIds);

        // In der berechneten Reihenfolge Slides bauen und je Slide Anker (Initiative)
        // + Bracket (Thema/Container) anheften — fuer Breadcrumb bzw. Rail-Gruppierung.
        $slides = [];
        foreach ($ordered as $row) {
            $p = $projects->get($row['pid']);
            if (! $p) {
                continue; // nicht laufend / nicht sichtbar
            }
            $slide = $this->buildSlide(
                $p,
                (int) ($plannedMap[$p->id] ?? 0),
                (int) ($loggedMap[$p->id] ?? 0),
                $snapshots[$p->id] ?? null,
                $periodMap[$p->id] ?? null,
            );
            $slide['initiative'] = $row['anchor'];
            $slide['bracket'] = $row['bracket'];
            $slides[] = $slide;
        }

        return $slides;
    }

    /**
     * Neuester Snapshot je Projekt — Quelle fuer die Health-Trend-Daten
     * (Ampel-Farbe + Score-Delta). Ein Query, kein N+1.
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
     * Geplantes Enddatum (Go-Live/Deadline) je Projekt — als Dual-Context-Query,
     * NICHT ueber $project->plannedEnd(). Die Trait-Methode nutzt morphMany auf den
     * Morph-Alias ("project"); Zeitraum-Datensaetze liegen aber teils unter dem
     * vollen Klassennamen → sonst kommt null zurueck und das Datum verschwindet.
     * Gleiche Falle wie bei geplanten/getrackten Minuten.
     *
     * @return array<int, \Carbon\Carbon>  project_id → planned_end
     */
    protected function plannedEndByProjectId(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $projectAlias = DimensionLinkService::resolveContextType(PlannerProject::class);

        return OrganizationTimePeriod::query()
            ->where('is_active', true)
            ->whereIn('context_type', [$projectAlias, PlannerProject::class])
            ->whereIn('context_id', $projectIds)
            ->whereNotNull('planned_end')
            ->get(['context_id', 'planned_end'])
            ->groupBy('context_id')
            ->map(fn ($rows) => $rows->pluck('planned_end')->max())
            ->all();
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

    protected function buildSlide(PlannerProject $project, int $plannedMinutes, int $loggedMinutes, ?PlannerProjectSnapshot $snapshot, ?\Carbon\Carbon $plannedEnd = null): array
    {
        $tasks = $project->tasks;
        $activeTasks = $tasks->filter(
            fn ($t) => $t->lifecycle_state === TaskLifecycleState::ACTIVE
        )->values();
        $doneTasks = $tasks->filter(
            fn ($t) => $t->lifecycle_state === TaskLifecycleState::COMPLETED
        );

        // ── Deadline / Go-Live (live aus der Projekt-Planung) ──
        // $plannedEnd kommt via Dual-Context-Query rein (siehe plannedEndByProjectId);
        // NICHT $project->plannedEnd() nutzen — morphMany trifft nur den Alias.
        $daysToEnd = $plannedEnd
            ? (int) now()->startOfDay()->diffInDays($plannedEnd->copy()->startOfDay(), false)
            : null;

        // Verstrichener Anteil der Laufzeit (Start → Ziel) fuer die Eckdaten-Leiste.
        $startDate = $project->created_at;
        $elapsedPct = null;
        if ($plannedEnd && $startDate) {
            $span = (int) $startDate->copy()->startOfDay()->diffInDays($plannedEnd->copy()->startOfDay(), false);
            if ($span > 0) {
                $elapsed = (int) $startDate->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);
                $elapsedPct = max(0, min(100, (int) round($elapsed / $span * 100)));
            } else {
                $elapsedPct = 100;
            }
        }

        // ── Ueberfaellige Aufgaben (live) ──
        $overdueCount = $activeTasks->filter(
            fn ($t) => $t->due_date && $t->due_date->isPast()
        )->count();

        // ── Story-Points (live): erledigt vs. gesamt (ohne verworfene) ──
        $spTotal = (int) ($activeTasks->sum(fn ($t) => $t->story_points?->points() ?? 0)
            + $doneTasks->sum(fn ($t) => $t->story_points?->points() ?? 0));
        $spDone = (int) $doneTasks->sum(fn ($t) => $t->story_points?->points() ?? 0);

        // ── Health-Trend aus dem neuesten Snapshot (Ampel + Score-Delta) ──
        $delta = $snapshot?->delta_health_score;
        $healthTrend = $delta === null ? null : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'));

        // ── Aufgaben (offen + erledigt sichtbar) samt DoD-Kriterien ──
        // Fortschritt zaehlt erledigte Aufgaben als voll erfuellt, damit sowohl
        // Abhaken als auch einzelne DoD-Haken den Ring nach oben bewegen.
        $dodTotal = 0;
        $dodChecked = 0;
        $relevantTasks = $activeTasks->concat($doneTasks); // offen zuerst, dann erledigt
        $taskRows = $relevantTasks->map(function ($t) use (&$dodTotal, &$dodChecked) {
            $isDone = $t->lifecycle_state === TaskLifecycleState::COMPLETED;
            $items = array_values($t->dod_items);
            $total = count($items);
            $checked = $isDone ? $total : count(array_filter($items, fn ($i) => $i['checked']));
            $dodTotal += $total;
            $dodChecked += $checked;

            return [
                'id'           => $t->id,
                'title'        => $t->title ?: '—',
                'is_done'      => $isDone,
                'story_points' => $t->story_points?->points(),
                'dod_items'    => array_map(fn ($i, $idx) => [
                    'index'   => $idx,
                    'text'    => $i['text'],
                    'checked' => $isDone ? true : (bool) $i['checked'],
                ], $items, array_keys($items)),
                'dod_total'    => $total,
                'dod_checked'  => $checked,
            ];
        })->all();

        return [
            'id'              => $project->id,
            'name'            => $project->name ?: '—',
            'owner_name'      => $project->user?->name,
            'created_at'      => $project->created_at?->format('d.m.Y'),
            'canvas'          => $this->canvasHighlights($project->id),
            'tasks'           => $taskRows,
            'open_task_count' => $activeTasks->count(),
            'dod_total'       => $dodTotal,
            'dod_checked'     => $dodChecked,
            'planned_minutes' => (int) $plannedMinutes,
            'logged_minutes'  => (int) $loggedMinutes,
            'planned_end'     => $plannedEnd?->format('d.m.Y'),
            'days_to_end'     => $daysToEnd,
            'elapsed_pct'     => $elapsedPct,
            'overdue_count'   => $overdueCount,
            'sp_total'        => $spTotal,
            'sp_done'         => $spDone,
            'health_color'    => $snapshot?->health_color,
            'health_trend'    => $healthTrend,
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
