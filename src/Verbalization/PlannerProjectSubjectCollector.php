<?php

namespace Platform\Planner\Verbalization;

use DateTimeImmutable;
use Platform\Core\Verbalization\Claim;
use Platform\Core\Verbalization\Edge;
use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactNature;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Enums\SubjectKind;
use Platform\Core\Verbalization\Fact;
use Platform\Core\Verbalization\Freshness;
use Platform\Core\Verbalization\Identity;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorInterface;
use Platform\Planner\Enums\ProjectKind;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;

/**
 * Sammler fuer PlannerProject — Knoten + Snapshot + Live-Topup + Canvas + Org-Anker -> Subject.
 *
 * Strategie:
 *  - Stammdaten: live aus dem Project-Model (billig)
 *  - Aggregate (Tasks/SP/Health): juengster Snapshot (heute Nacht), Live-Topup
 *    fuer Bewegungen seit Snapshot
 *  - Top-N-Detail-Listen (Slots/Froesche/Personen): aus Snapshot-Sub-Tabellen
 *  - Canvas: live aus PlannerProjectCanvas + Blocks + Entries
 *  - Organisation: via entityLinks → Verknuepfung zu Organization-Entities
 */
class PlannerProjectSubjectCollector implements SubjectCollectorInterface
{
    public function handles(): string
    {
        return 'planner_project';
    }

    /**
     * Default-Recipe: alles an, max-Limits — Verhalten wie vor der Recipe-Aera.
     * Kommt zum Einsatz wenn keine Recipe uebergeben wird.
     */
    public const DEFAULT_SOURCES = [
        // State-Facts
        'description' => true,
        'lifetime' => true,
        'core_health' => true,
        'slots' => ['enabled' => true, 'top_n' => 3],
        'frogs' => ['enabled' => true, 'top_n' => 3],
        'people' => ['enabled' => true, 'top_n' => 3],
        'canvas' => ['enabled' => true, 'max_highlights' => 6, 'max_entry_chars' => 140],
        'budget' => true,
        'termine' => true,
        'confidence' => true,
        // Movement/Ableitungs-Facts (Weg A: Bewegung im Kontext)
        'movement_summary' => true,
        'scope_fulfillment' => true,
        'ball_position' => true,
        'open_by_owner' => true,
        // Edges
        'edges_owner' => true,
        'edges_team' => true,
        'edges_org_anchors' => true,
        'edges_cost_center' => true,
    ];

    public function collectState(
        mixed $project,
        ?CollectionRecipe $recipe = null,
        ?\DateTimeInterface $since = null,
    ): Subject {
        if (! $project instanceof PlannerProject) {
            $project = PlannerProject::with(['user:id,name', 'projectUsers.user:id,name', 'entityLinks.entity:id,name'])
                ->findOrFail($project);
        }

        $snapshot = PlannerProjectSnapshot::with(['slots', 'frogs', 'people'])
            ->where('project_id', $project->id)
            ->orderByDesc('taken_on')
            ->first();

        [$source, $asOf] = $this->resolveFreshness($snapshot);

        // Recipe-Helper — fallback auf Default-Sources wenn keine Recipe gesetzt ist.
        $isOn = $recipe
            ? fn (string $key) => $recipe->hasSource($key)
            : fn (string $key) => $this->defaultSourceOn($key);
        $limit = function (string $key, string $limitKey, ?int $default) use ($recipe) {
            if ($recipe) {
                return $recipe->sourceLimit($key, $limitKey, $default);
            }
            $cfg = self::DEFAULT_SOURCES[$key] ?? null;
            return is_array($cfg) && isset($cfg[$limitKey]) ? (int) $cfg[$limitKey] : $default;
        };

        $facts = array_merge(
            // Bewegungs- + Ableitungs-Facts kommen als ERSTES (bei CORE-Priority), damit die
            // Prosa mit "was hat sich getan / wo stehen wir" beginnt statt mit "Zweck".
            $isOn('movement_summary') && $since ? $this->factsMovementSummary($project, $since) : [],
            $isOn('scope_fulfillment') ? $this->factsScopeFulfillment($project, $snapshot) : [],
            $isOn('ball_position') ? $this->factsBallPosition($project, $snapshot) : [],
            // State-Facts
            $isOn('description') ? $this->factsDescription($project) : [],
            $isOn('lifetime') ? $this->factsLifetime($project) : [],
            $isOn('core_health') ? $this->factsCore($project, $snapshot) : [],
            $isOn('open_by_owner') ? $this->factsOpenByOwner($project) : [],
            $isOn('slots') ? $this->factsSlots($snapshot, $limit('slots', 'top_n', 3)) : [],
            $isOn('frogs') ? $this->factsFrogs($snapshot, $limit('frogs', 'top_n', 3)) : [],
            $isOn('people') ? $this->factsPeople($snapshot, $limit('people', 'top_n', 3)) : [],
            $isOn('canvas') ? $this->factsCanvas(
                $project,
                $limit('canvas', 'max_highlights', 6),
                $limit('canvas', 'max_entry_chars', 140),
            ) : [],
            $isOn('budget') ? $this->factsBudget($project, $snapshot) : [],
            $isOn('termine') ? $this->factsTermine($snapshot) : [],
            $isOn('confidence') ? $this->factsConfidence($snapshot) : [],
        );

        $edges = array_merge(
            $isOn('edges_owner') ? $this->edgesOwner($project) : [],
            $isOn('edges_org_anchors') ? $this->edgesOrgAnchors($project) : [],
            $isOn('edges_team') ? $this->edgesTeam($project) : [],
            $isOn('edges_cost_center') ? $this->edgesCostCenter($project) : [],
        );

        return new Subject(
            kind: SubjectKind::STATE,
            type: 'planner_project',
            id: (string) $project->id,
            identity: new Identity(
                primaryName: $project->name,
                shortLabel: $project->name,
                slug: $project->uuid,
            ),
            facts: $facts,
            edges: $edges,
            freshness: new Freshness(
                asOf: DateTimeImmutable::createFromFormat('U', (string) $asOf->getTimestamp()) ?: new DateTimeImmutable(),
                source: $source,
                stalenessSeconds: time() - $asOf->getTimestamp(),
            ),
            meta: [
                'snapshot_id' => $snapshot?->id,
                'has_snapshot' => $snapshot !== null,
                'recipe_key' => $recipe?->key,
            ],
        );
    }

    protected function defaultSourceOn(string $key): bool
    {
        $cfg = self::DEFAULT_SOURCES[$key] ?? false;
        if (is_bool($cfg)) {
            return $cfg;
        }
        return (bool) ($cfg['enabled'] ?? false);
    }

    /** @return array{0: DataSource, 1: \DateTimeInterface} */
    protected function resolveFreshness(?PlannerProjectSnapshot $snapshot): array
    {
        if (! $snapshot) {
            return [DataSource::LIVE, now()];
        }
        $taken = $snapshot->taken_at ?? $snapshot->taken_on?->setTime(0, 0) ?? now();
        return [DataSource::SNAPSHOT_WITH_LIVE_TOPUP, $taken];
    }

    // ───────────────────────── FACTS ─────────────────────────

    /** @return Fact[] */
    protected function factsDescription(PlannerProject $project): array
    {
        $desc = trim((string) ($project->description ?? ''));
        if ($desc === '') {
            return [];
        }
        return [new Fact(FactPriority::CORE, 'Zweck: ' . $desc, 'project.description')];
    }

    /**
     * Wie lange laeuft das Projekt schon? Verschiebt Lesart komplett:
     * "heute angelegt" vs. "seit 6 Monaten laeuft" sind verschiedene Stories,
     * auch bei gleichem Health-Score.
     *
     * Priorisierung: ganz frische (< 7 Tage) oder ueberfaellige (> 12 Monate)
     * Projekte → QUALIFYING. Dazwischen → CONTEXT.
     *
     * @return Fact[]
     */
    protected function factsLifetime(PlannerProject $project): array
    {
        if (! $project->created_at) {
            return [];
        }
        $created = $project->created_at;
        $diffDays = (int) $created->diffInDays(now());

        $human = $this->humanizeProjectAge($diffDays, $created);
        $priority = ($diffDays < 7 || $diffDays > 365)
            ? FactPriority::QUALIFYING
            : FactPriority::CONTEXT;

        $datePart = $created->format('d.m.Y');
        return [new Fact(
            $priority,
            "Angelegt am {$datePart} — {$human}.",
            'project.created_at',
            hashKey: 'lifetime:' . $created->format('Y-m-d'),
        )];
    }

    protected function humanizeProjectAge(int $diffDays, \Illuminate\Support\Carbon $created): string
    {
        if ($diffDays === 0) return 'heute angelegt';
        if ($diffDays === 1) return 'gestern angelegt';
        if ($diffDays < 7) return "vor {$diffDays} Tagen angelegt";
        if ($diffDays < 14) return 'vor etwas mehr als einer Woche angelegt';
        if ($diffDays < 30) {
            $weeks = (int) round($diffDays / 7);
            return "vor {$weeks} Wochen angelegt";
        }
        if ($diffDays < 365) {
            $months = (int) round($diffDays / 30);
            return "laeuft seit {$months} Monaten";
        }
        $years = (int) floor($diffDays / 365);
        if ($years === 1) return 'laeuft seit ueber einem Jahr';
        return "laeuft seit ueber {$years} Jahren";
    }

    /** @return Fact[] */
    protected function factsCore(PlannerProject $project, ?PlannerProjectSnapshot $snapshot): array
    {
        $facts = [];

        $kindLabel = match ($project->kind) {
            ProjectKind::PROJECT => 'Projekt',
            ProjectKind::RUN => 'Run',
            default => 'Vorhaben',
        };
        $statusLabel = $project->lifecycle_state?->value ?? 'aktiv';
        $facts[] = new Fact(FactPriority::CORE, "{$kindLabel} im Status: {$statusLabel}.", 'project.kind+status');

        if (! $snapshot) {
            $facts[] = new Fact(FactPriority::CONTEXT, 'Noch keine Snapshot-Daten zu diesem Knoten.', 'snapshot.absent');
            return $facts;
        }

        if ($snapshot->health_score !== null) {
            $delta = $snapshot->delta_health_score;
            $deltaTxt = $delta !== null && $delta !== 0
                ? ' (' . ($delta > 0 ? '+' : '') . $delta . ' ggü. Vortag)'
                : '';
            $colorTxt = match ($snapshot->health_color) {
                'red' => ' — Ampel rot',
                'yellow' => ' — Ampel gelb',
                'green' => ' — Ampel gruen',
                default => '',
            };
            $facts[] = new Fact(FactPriority::CORE, "Health-Score: {$snapshot->health_score}{$colorTxt}{$deltaTxt}.", 'snapshot.health');
        }

        // Tasks-Lage mit Live-Topup
        $todayStart = now()->startOfDay();
        $sinceSnapshot = $snapshot->taken_at ?? $todayStart;
        $doneSinceSnapshot = PlannerTask::where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)
            ->where('lifecycle_state_changed_at', '>=', $sinceSnapshot)
            ->count();
        $tasksOpenNow = max(0, (int) $snapshot->tasks_open - $doneSinceSnapshot);

        $taskTxt = "{$tasksOpenNow} Aufgaben offen";
        if ($doneSinceSnapshot > 0) {
            $taskTxt .= ", davon {$doneSinceSnapshot} seit dem letzten Snapshot erledigt";
        }
        if ($snapshot->tasks_overdue > 0) {
            $taskTxt .= ", {$snapshot->tasks_overdue} überfällig";
        }
        if ($snapshot->tasks_frog > 0) {
            $taskTxt .= ", {$snapshot->tasks_frog} davon Frösche";
        }
        $facts[] = new Fact(FactPriority::CORE, $taskTxt . '.', 'snapshot.tasks+live.done');

        if ($snapshot->story_points_total !== null && $snapshot->story_points_total > 0) {
            // Live-Topup: SP der seit Snapshot erledigten Tasks aufaddieren (analog Tasks-Count).
            // Sonst Widerspruch: "3 seit Snapshot done" vs "0 SP erledigt".
            $spSinceSnapshot = PlannerTask::where('project_id', $project->id)
                ->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)
                ->where('lifecycle_state_changed_at', '>=', $sinceSnapshot)
                ->get()
                ->sum(fn ($t) => $t->story_points?->points() ?? 0);
            $spDone = (int) $snapshot->story_points_done + (int) $spSinceSnapshot;
            $spTotal = (int) $snapshot->story_points_total;
            $spDone = min($spDone, $spTotal); // safety
            $pct = $spTotal > 0 ? round($spDone / $spTotal * 100) : 0;
            $facts[] = new Fact(FactPriority::QUALIFYING, "{$spDone} von {$spTotal} Story Points erledigt ({$pct}%).", 'snapshot.story_points+live.done_sp');
        }

        if ((int) $snapshot->minutes_logged > 0) {
            $hours = round($snapshot->minutes_logged / 60, 1);
            $facts[] = new Fact(FactPriority::QUALIFYING, "Bisher erfasste Arbeitszeit: {$hours} Stunden.", 'snapshot.minutes_logged');
        }

        return $facts;
    }

    /**
     * Bewegungs-Zusammenfassung seit $since (typisch: created_at des letzten Feed-Outputs).
     * Diese Fact-Klasse macht den Report von einem Zustand-Report zu einer
     * "was hat sich getan" — Grundlage der Kunden-Kommunikation.
     *
     * Leer wenn nichts passiert ist — dann triggert der Feed-Refresh No-Delta-Skip
     * und es entsteht kein neuer Output.
     *
     * @return Fact[]
     */
    protected function factsMovementSummary(PlannerProject $project, \DateTimeInterface $since): array
    {
        $doneSince = PlannerTask::where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)
            ->where('lifecycle_state_changed_at', '>=', $since)
            ->orderBy('lifecycle_state_changed_at')
            ->get(['id', 'title', 'story_points', 'project_slot_id', 'lifecycle_state_changed_at']);

        if ($doneSince->isEmpty()) {
            return [];
        }

        $count = $doneSince->count();
        $spTotal = $doneSince->sum(fn ($t) => $t->story_points?->points() ?? 0);
        $sinceLabel = $this->humanizeSince($since);

        $parts = ["{$sinceLabel} erledigt: **{$count} " . ($count === 1 ? 'Aufgabe' : 'Aufgaben') . "**"];
        if ($spTotal > 0) {
            $parts[] = "gesamt {$spTotal} Story Points";
        }

        // Welche Slots sind KOMPLETT durch diese Erledigungen jetzt geschlossen?
        $slotIdsTouched = $doneSince->pluck('project_slot_id')->filter()->unique();
        $closedSlots = [];
        foreach ($slotIdsTouched as $slotId) {
            $openInSlot = PlannerTask::where('project_id', $project->id)
                ->where('project_slot_id', $slotId)
                ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
                ->count();
            if ($openInSlot === 0) {
                $slotName = \Platform\Planner\Models\PlannerProjectSlot::where('id', $slotId)->value('name');
                if ($slotName) {
                    $closedSlots[] = $slotName;
                }
            }
        }

        $summary = implode(', ', $parts) . '.';
        if (! empty($closedSlots)) {
            $slotList = '"' . implode('", "', $closedSlots) . '"';
            $summary .= ' Damit ' . (count($closedSlots) === 1 ? 'ist Slot ' : 'sind die Slots ') . $slotList . ' vollstaendig abgeschlossen.';
        }

        // Stabile Signatur — Zeitfenster-Text ("Seit gestern"/"In dieser Woche")
        // gehoert NICHT in den Hash, sonst driftet der Dedup mit der Uhr.
        // Content-Signatur: erledigte-count + SP + geschlossene Slot-Namen.
        $slotSig = ! empty($closedSlots) ? implode(',', $closedSlots) : '_';
        $hashKey = "movement:done={$count}:sp={$spTotal}:slots={$slotSig}";

        return [new Fact(
            FactPriority::CORE,
            $summary,
            'movement.since=' . $since->format('c'),
            FactNature::MOVEMENT,
            hashKey: $hashKey,
        )];
    }

    protected function humanizeSince(\DateTimeInterface $since): string
    {
        $sinceCarbon = \Illuminate\Support\Carbon::parse($since->format('c'));
        $days = (int) $sinceCarbon->diffInDays(now());
        if ($days <= 1) return 'Seit gestern';
        if ($days <= 3) return 'In den letzten Tagen';
        if ($days <= 7) return 'In dieser Woche';
        if ($days <= 14) return 'In den letzten zwei Wochen';
        return 'Seit dem ' . $sinceCarbon->format('d.m.');
    }

    /**
     * Scope-Erfuellung: welche Slots sind komplett abgearbeitet? Das ist eine
     * konservative Ableitung (Slot-Vollstaendigkeit statt Canvas-Text-Match) —
     * ehrlich, aber ohne komplizierte NLP-Ableitung.
     *
     * @return Fact[]
     */
    protected function factsScopeFulfillment(PlannerProject $project, ?PlannerProjectSnapshot $snapshot): array
    {
        $slots = \Platform\Planner\Models\PlannerProjectSlot::where('project_id', $project->id)
            ->withCount([
                'tasks as tasks_total' => fn ($q) => $q,
                'tasks as tasks_open' => fn ($q) => $q->where('lifecycle_state', TaskLifecycleState::ACTIVE->value),
            ])
            ->get();

        if ($slots->isEmpty()) {
            return [];
        }

        $fullyDone = $slots->filter(fn ($s) => (int) $s->tasks_total > 0 && (int) $s->tasks_open === 0);
        $partiallyOpen = $slots->filter(fn ($s) => (int) $s->tasks_open > 0);

        if ($fullyDone->isEmpty() && $partiallyOpen->isEmpty()) {
            return [];
        }

        $facts = [];
        if ($fullyDone->isNotEmpty()) {
            $names = $fullyDone->pluck('name')->map(fn ($n) => "\"{$n}\"")->implode(', ');
            $verb = $fullyDone->count() === 1 ? 'ist komplett abgeschlossen' : 'sind komplett abgeschlossen';
            $facts[] = new Fact(FactPriority::CORE, "Slot {$names} {$verb}.", 'scope.fulfillment.done_slots', FactNature::DERIVATION);
        }
        if ($partiallyOpen->isNotEmpty()) {
            $summary = $partiallyOpen->map(fn ($s) => "\"{$s->name}\" ({$s->tasks_open} offen)")->implode(', ');
            $facts[] = new Fact(FactPriority::QUALIFYING, "In Arbeit: {$summary}.", 'scope.fulfillment.open_slots', FactNature::DERIVATION);
        }
        return $facts;
    }

    /**
     * Ball-Position: wer ist gerade am Zug?
     *
     * Heuristik (bewusst konservativ):
     *  - Keine offenen Tasks in BHG-Slots + Projekt hat weitere Rollen im Canvas
     *    (Stakeholder mit 'Test' / 'Kunde' / 'Feedback' im Namen) → Ball beim Kunden
     *  - Sonst → Ball bei Owner
     *
     * @return Fact[]
     */
    protected function factsBallPosition(PlannerProject $project, ?PlannerProjectSnapshot $snapshot): array
    {
        $openTotal = (int) ($snapshot?->tasks_open ?? PlannerTask::where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)->count());
        // Live-Topup: alles was seit Snapshot done ging, war offen — jetzt nicht mehr
        if ($snapshot) {
            $doneSinceSnapshot = PlannerTask::where('project_id', $project->id)
                ->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)
                ->where('lifecycle_state_changed_at', '>=', $snapshot->taken_at ?? now()->startOfDay())
                ->count();
            $openTotal = max(0, $openTotal - $doneSinceSnapshot);
        }

        if ($project->lifecycle_state === ProjectLifecycleState::COMPLETED) {
            return [new Fact(FactPriority::CORE, 'Projekt ist abgeschlossen.', 'ball.done', FactNature::DERIVATION)];
        }

        if ($openTotal === 0) {
            // Alle Tasks BHG-seitig fertig — Ball vermutlich beim Externen.
            // Prueft ob Stakeholder-Rollen im Canvas Kunden/Test-Rolle nahelegen.
            $externalRole = $this->findExternalWaitingRole($project);
            if ($externalRole) {
                return [new Fact(
                    FactPriority::CORE,
                    "Alle geplanten BHG-Aufgaben sind erledigt. Ball liegt jetzt bei: {$externalRole}.",
                    'ball.at_external',
                    FactNature::DERIVATION,
                )];
            }
            return [new Fact(
                FactPriority::CORE,
                'Alle geplanten Aufgaben sind erledigt. Naechster Schritt: Klaerung was folgt.',
                'ball.no_open_tasks',
                FactNature::DERIVATION,
            )];
        }

        $ownerName = $project->user?->name ?? 'Owner';
        return [new Fact(
            FactPriority::QUALIFYING,
            "In Umsetzung durch {$ownerName} ({$openTotal} offen).",
            'ball.at_owner',
            FactNature::DERIVATION,
        )];
    }

    /**
     * Sucht im Canvas-Stakeholder-Block nach Personen mit externen Rollen
     * (Kunde/Test/Auftraggeber) — die Rolle wird als Ball-Empfaenger genannt.
     */
    protected function findExternalWaitingRole(PlannerProject $project): ?string
    {
        $canvas = PlannerProjectCanvas::with(['blocks.entries'])
            ->where('project_id', $project->id)
            ->where('status', '!=', 'closed')
            ->latest('id')
            ->first();
        if (! $canvas) {
            return null;
        }
        $stakeholderBlock = $canvas->blocks->first(fn ($b) => $b->block_type === 'stakeholders');
        if (! $stakeholderBlock) {
            return null;
        }
        // Suche einen Entry mit externem Rollen-Hinweis
        $keywords = ['test', 'kunde', 'auftrag', 'feedback', 'endnutz', 'anwender', 'extern'];
        foreach ($stakeholderBlock->entries as $entry) {
            $haystack = mb_strtolower(($entry->title ?? '') . ' ' . ($entry->content ?? ''));
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return trim($entry->title ?? 'Externem Stakeholder');
                }
            }
        }
        return null;
    }

    /**
     * Offene Aufgaben pro AP-Verantwortlichem — als CORE-Fact.
     *
     * Zaehlt live und teilt nach user_in_charge_id auf. Fuer jede Person werden
     * die Slot-Namen mitgegeben, in denen sie zustaendig ist. Damit ist die
     * Task-Zahl im Prosa-Prompt eindeutig owner-attribuiert und das LLM kann
     * nicht mehr naiv "n offene Tasks" dem Projekt-Owner zuschlagen.
     *
     * @return Fact[]
     */
    protected function factsOpenByOwner(PlannerProject $project): array
    {
        $tasks = PlannerTask::where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('user_in_charge_id')
            ->with(['userInCharge:id,name', 'projectSlot:id,name'])
            ->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $grouped = $tasks->groupBy('user_in_charge_id');
        $parts = $grouped->map(function ($tasksOfUser, $userId) {
            /** @var \Illuminate\Support\Collection<int, PlannerTask> $tasksOfUser */
            $first = $tasksOfUser->first();
            $name = $first->userInCharge?->name ?? ('User #' . $userId);
            $count = $tasksOfUser->count();
            $slotNames = $tasksOfUser->pluck('projectSlot.name')->filter()->unique()->values();
            $slots = $slotNames->isNotEmpty() ? ' (' . $slotNames->implode(', ') . ')' : '';
            return "{$count} bei {$name}{$slots}";
        })->values()->implode('; ');

        return [new Fact(
            FactPriority::CORE,
            "Offene Aufgaben nach Verantwortung: {$parts}.",
            'live.open_by_owner',
        )];
    }

    /** @return Fact[] */
    protected function factsSlots(?PlannerProjectSnapshot $snapshot, ?int $topN = 3): array
    {
        if (! $snapshot || $snapshot->slots->isEmpty() || ($topN ?? 0) <= 0) {
            return [];
        }
        $topSlots = $snapshot->slots
            ->filter(fn ($s) => (int) $s->open_tasks > 0)
            ->sortByDesc('open_tasks')
            ->take($topN);

        if ($topSlots->isEmpty()) {
            return [];
        }

        $parts = $topSlots->map(function ($s) {
            return "{$s->slot_name} ({$s->open_tasks} offen / {$s->done_tasks} erledigt)";
        })->implode('; ');

        return [new Fact(FactPriority::QUALIFYING, "Aktive Slots: {$parts}.", 'snapshot.slots.top' . $topN)];
    }

    /** @return Fact[] */
    protected function factsFrogs(?PlannerProjectSnapshot $snapshot, ?int $topN = 3): array
    {
        if (! $snapshot || $snapshot->frogs->isEmpty() || ($topN ?? 0) <= 0) {
            return [];
        }
        $topFrogs = $snapshot->frogs
            ->sortByDesc(fn ($f) => ($f->is_overdue ? 100 : 0) + (int) $f->postpone_count)
            ->take($topN);

        if ($topFrogs->isEmpty()) {
            return [];
        }

        $parts = $topFrogs->map(function ($f) {
            $tail = [];
            if ($f->is_overdue) {
                $tail[] = 'überfällig';
            }
            if ((int) $f->postpone_count > 0) {
                $tail[] = $f->postpone_count . 'x verschoben';
            }
            $tailTxt = $tail ? ' (' . implode(', ', $tail) . ')' : '';
            return $f->task_title . $tailTxt;
        })->implode('; ');

        return [new Fact(FactPriority::CORE, "Frösche, die liegen bleiben: {$parts}.", 'snapshot.frogs.top' . $topN)];
    }

    /** @return Fact[] */
    protected function factsPeople(?PlannerProjectSnapshot $snapshot, ?int $topN = 3): array
    {
        if (! $snapshot || $snapshot->people->isEmpty() || ($topN ?? 0) <= 0) {
            return [];
        }
        $topPeople = $snapshot->people
            ->filter(fn ($p) => (int) $p->open_tasks > 0)
            ->sortByDesc('open_tasks')
            ->take($topN);

        if ($topPeople->isEmpty()) {
            return [];
        }

        $parts = $topPeople->map(function ($p) {
            $sp = (int) $p->sp_open > 0 ? ", {$p->sp_open} SP" : '';
            $od = (int) $p->overdue_tasks > 0 ? ", davon {$p->overdue_tasks} überfällig" : '';
            return "{$p->user_name}: {$p->open_tasks} offen{$sp}{$od}";
        })->implode('; ');

        return [new Fact(FactPriority::QUALIFYING, "Aktuelle Workload-Verteilung: {$parts}.", 'snapshot.people.top' . $topN)];
    }

    /** @return Fact[] */
    protected function factsCanvas(PlannerProject $project, ?int $maxHighlights = 6, ?int $maxEntryChars = 140): array
    {
        $canvas = PlannerProjectCanvas::with(['blocks.entries'])
            ->where('project_id', $project->id)
            ->where('status', '!=', 'closed')
            ->latest('id')
            ->first();

        if (! $canvas) {
            return [];
        }

        $facts = [];
        $blocks = $canvas->blocks;
        $totalBlocks = $blocks->count();
        $filledBlocks = $blocks->filter(fn ($b) => $b->entries->isNotEmpty())->count();

        if ($totalBlocks > 0) {
            $facts[] = new Fact(
                FactPriority::QUALIFYING,
                "Canvas '{$canvas->title}': {$filledBlocks} von {$totalBlocks} Bloecken befuellt.",
                'canvas.completeness',
            );
        }

        // Highlights: pro befuelltem Block die ersten 1-2 Entries, max insgesamt durch Recipe begrenzt
        $highlights = [];
        $maxHi = max(0, (int) ($maxHighlights ?? 6));
        $maxChars = max(20, (int) ($maxEntryChars ?? 140));
        if ($maxHi === 0) {
            return $facts;
        }
        foreach ($blocks->filter(fn ($b) => $b->entries->isNotEmpty())->sortBy('position') as $block) {
            $topEntries = $block->entries->sortBy('position')->take(2);
            foreach ($topEntries as $entry) {
                $title = trim((string) ($entry->title ?? ''));
                $content = trim((string) ($entry->content ?? ''));
                $combined = $title;
                if ($content !== '' && $content !== $title) {
                    $combined .= ($title !== '' ? ': ' : '') . $content;
                }
                if ($combined === '') {
                    continue;
                }
                if (mb_strlen($combined) > $maxChars) {
                    $combined = mb_substr($combined, 0, $maxChars - 3) . '...';
                }
                $highlights[] = "[{$block->label}] {$combined}";
                if (count($highlights) >= $maxHi) {
                    break 2;
                }
            }
        }

        if (! empty($highlights)) {
            $facts[] = new Fact(
                FactPriority::CORE,
                "Canvas-Highlights: " . implode(' | ', $highlights),
                'canvas.entries.top',
            );
        }

        return $facts;
    }

    /** @return Fact[] */
    protected function factsBudget(PlannerProject $project, ?PlannerProjectSnapshot $snapshot): array
    {
        $facts = [];
        $budget = (float) ($project->budget_amount ?? 0);
        $currency = $project->currency ?? 'EUR';

        if ($budget > 0) {
            $used = (float) ($snapshot?->budget_used_euro ?? 0);
            $pct = $budget > 0 ? round($used / $budget * 100) : 0;
            $facts[] = new Fact(
                FactPriority::QUALIFYING,
                "Budget: " . number_format($used, 0, ',', '.') . " {$currency} von " . number_format($budget, 0, ',', '.') . " {$currency} verbraucht ({$pct}%).",
                'project.budget+snapshot.budget_used',
            );
        }

        return $facts;
    }

    /** @return Fact[] */
    protected function factsTermine(?PlannerProjectSnapshot $snapshot): array
    {
        if (! $snapshot || $snapshot->days_to_planned_end === null) {
            return [];
        }
        $days = (int) $snapshot->days_to_planned_end;
        // Stabile Signatur: nicht die konkreten Tage (driftet taeglich), sondern
        // ein Bucket. Wechselt nur bei semantisch bedeutsamem Uebergang (Termin
        // rueckt in kritischen Bereich / geht in Overdue ueber).
        $bucket = $days < 0 ? 'overdue' : ($days <= 14 ? 'near' : 'far');
        $hashKey = "days_to_end:bucket={$bucket}";
        if ($days < 0) {
            return [new Fact(FactPriority::CORE, "Geplanter Endtermin liegt " . abs($days) . " Tage zurück.", 'snapshot.days_to_end', hashKey: $hashKey)];
        }
        if ($days <= 14) {
            return [new Fact(FactPriority::QUALIFYING, "Geplanter Endtermin in {$days} Tagen.", 'snapshot.days_to_end', hashKey: $hashKey)];
        }
        return [new Fact(FactPriority::CONTEXT, "Geplanter Endtermin in {$days} Tagen.", 'snapshot.days_to_end', hashKey: $hashKey)];
    }

    /** @return Fact[] */
    protected function factsConfidence(?PlannerProjectSnapshot $snapshot): array
    {
        if (! $snapshot || $snapshot->confidence_score === null) {
            return [];
        }
        $cs = (int) $snapshot->confidence_score;
        if ($cs >= 50) {
            return [];
        }
        $reason = $snapshot->confidence_reason;
        return [new Fact(
            FactPriority::CONTEXT,
            "Datenbasis fuer Bewertung unvollstaendig (Konfidenz {$cs}/100" . ($reason ? ', Grund: ' . $reason : '') . ').',
            'snapshot.confidence',
        )];
    }

    // ───────────────────────── EDGES ─────────────────────────

    /** @return Edge[] */
    protected function edgesOwner(PlannerProject $project): array
    {
        $edges = [];

        if ($project->user_id) {
            $edges[] = new Edge(
                relation: 'verantwortet_von',
                targetType: 'person',
                targetId: (string) $project->user_id,
                targetLabel: $project->user?->name ?? ('User #' . $project->user_id),
                claim: Claim::systemVerified(),
                weight: FactPriority::CORE,
            );
        }

        // Sekundaere Owner: distinct user_in_charge_id aus offenen Tasks, gruppiert
        // pro Person mit den Slot-Namen die sie verantwortet. Damit sind AP-Owner
        // sichtbar, wenn ein Projekt dezentral gefahren wird (typischer Fall:
        // Projekt-Owner koordiniert, aber einzelne Arbeitspakete haben eigene
        // Verantwortliche). Der Projekt-Owner wird uebersprungen, da er schon oben steht.
        $projectOwnerId = (int) ($project->user_id ?? 0);
        $tasks = PlannerTask::where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('user_in_charge_id')
            ->with(['userInCharge:id,name', 'projectSlot:id,name'])
            ->get()
            ->filter(fn ($t) => (int) $t->user_in_charge_id !== $projectOwnerId)
            ->groupBy('user_in_charge_id');

        foreach ($tasks as $userId => $tasksOfUser) {
            /** @var \Illuminate\Support\Collection<int, PlannerTask> $tasksOfUser */
            $first = $tasksOfUser->first();
            $name = $first->userInCharge?->name ?? ('User #' . $userId);
            $slotNames = $tasksOfUser->pluck('projectSlot.name')->filter()->unique()->values();
            $label = $slotNames->isNotEmpty()
                ? $name . ' — verantwortet AP: ' . $slotNames->implode(', ')
                : $name;
            $edges[] = new Edge(
                relation: 'verantwortet_arbeitspaket',
                targetType: 'person',
                targetId: (string) $userId,
                targetLabel: $label,
                claim: Claim::systemVerified(),
                weight: FactPriority::QUALIFYING,
            );
        }

        return $edges;
    }

    /** @return Edge[] */
    protected function edgesTeam(PlannerProject $project): array
    {
        $edges = [];
        foreach ($project->projectUsers ?? [] as $pu) {
            if (! $pu->user || (int) $pu->user_id === (int) $project->user_id) {
                continue;
            }
            $edges[] = new Edge(
                relation: 'arbeitet_mit',
                targetType: 'person',
                targetId: (string) $pu->user_id,
                targetLabel: $pu->user->name ?? ('User #' . $pu->user_id),
                claim: Claim::systemVerified(),
                weight: FactPriority::QUALIFYING,
            );
        }
        return $edges;
    }

    /** @return Edge[] */
    protected function edgesOrgAnchors(PlannerProject $project): array
    {
        $edges = [];
        foreach ($project->entityLinks ?? [] as $link) {
            if (! $link->entity) {
                continue;
            }
            $edges[] = new Edge(
                relation: 'gehört_zu',
                targetType: 'organization_entity',
                targetId: (string) $link->entity->id,
                targetLabel: $link->entity->name,
                claim: Claim::systemVerified(),
                weight: FactPriority::QUALIFYING,
            );
        }
        return $edges;
    }

    /** @return Edge[] */
    protected function edgesCostCenter(PlannerProject $project): array
    {
        if (! $project->customer_cost_center) {
            return [];
        }
        return [new Edge(
            relation: 'abgerechnet_auf',
            targetType: 'cost_center',
            targetId: null,
            targetLabel: $project->customer_cost_center,
            claim: Claim::systemVerified(),
            weight: FactPriority::CONTEXT,
        )];
    }
}
