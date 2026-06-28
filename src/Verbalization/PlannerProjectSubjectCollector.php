<?php

namespace Platform\Planner\Verbalization;

use DateTimeImmutable;
use Platform\Core\Verbalization\Claim;
use Platform\Core\Verbalization\Edge;
use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Enums\SubjectKind;
use Platform\Core\Verbalization\Fact;
use Platform\Core\Verbalization\Freshness;
use Platform\Core\Verbalization\Identity;
use Platform\Core\Verbalization\Subject;
use Platform\Planner\Enums\ProjectKind;
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
class PlannerProjectSubjectCollector
{
    public function collectState(int|PlannerProject $project): Subject
    {
        $project = $project instanceof PlannerProject
            ? $project
            : PlannerProject::with(['user:id,name', 'projectUsers.user:id,name', 'entityLinks.entity:id,name'])
                ->findOrFail($project);

        $snapshot = PlannerProjectSnapshot::with(['slots', 'frogs', 'people'])
            ->where('project_id', $project->id)
            ->orderByDesc('taken_on')
            ->first();

        [$source, $asOf] = $this->resolveFreshness($snapshot);

        $facts = array_merge(
            $this->factsDescription($project),
            $this->factsCore($project, $snapshot),
            $this->factsSlots($snapshot),
            $this->factsFrogs($snapshot),
            $this->factsPeople($snapshot),
            $this->factsCanvas($project),
            $this->factsBudget($project, $snapshot),
            $this->factsTermine($snapshot),
            $this->factsConfidence($snapshot),
        );

        $edges = array_merge(
            $this->edgesOwner($project),
            $this->edgesOrgAnchors($project),
            $this->edgesTeam($project),
            $this->edgesCostCenter($project),
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
            ],
        );
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

    /** @return Fact[] */
    protected function factsCore(PlannerProject $project, ?PlannerProjectSnapshot $snapshot): array
    {
        $facts = [];

        $kindLabel = match ($project->kind) {
            ProjectKind::PROJECT => 'Projekt',
            ProjectKind::RUN => 'Run',
            default => 'Vorhaben',
        };
        $statusLabel = $project->done ? 'erledigt' : ($project->status?->value ?? 'aktiv');
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
            ->where('is_done', true)
            ->where('done_at', '>=', $sinceSnapshot)
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
            $spDone = (int) $snapshot->story_points_done;
            $spTotal = (int) $snapshot->story_points_total;
            $pct = $spTotal > 0 ? round($spDone / $spTotal * 100) : 0;
            $facts[] = new Fact(FactPriority::QUALIFYING, "{$spDone} von {$spTotal} Story Points erledigt ({$pct}%).", 'snapshot.story_points');
        }

        if ((int) $snapshot->minutes_logged > 0) {
            $hours = round($snapshot->minutes_logged / 60, 1);
            $facts[] = new Fact(FactPriority::QUALIFYING, "Bisher erfasste Arbeitszeit: {$hours} Stunden.", 'snapshot.minutes_logged');
        }

        return $facts;
    }

    /** @return Fact[] */
    protected function factsSlots(?PlannerProjectSnapshot $snapshot): array
    {
        if (! $snapshot || $snapshot->slots->isEmpty()) {
            return [];
        }
        $topSlots = $snapshot->slots
            ->filter(fn ($s) => (int) $s->open_tasks > 0)
            ->sortByDesc('open_tasks')
            ->take(3);

        if ($topSlots->isEmpty()) {
            return [];
        }

        $parts = $topSlots->map(function ($s) {
            return "{$s->slot_name} ({$s->open_tasks} offen / {$s->done_tasks} erledigt)";
        })->implode('; ');

        return [new Fact(FactPriority::QUALIFYING, "Aktive Slots: {$parts}.", 'snapshot.slots.top3')];
    }

    /** @return Fact[] */
    protected function factsFrogs(?PlannerProjectSnapshot $snapshot): array
    {
        if (! $snapshot || $snapshot->frogs->isEmpty()) {
            return [];
        }
        $topFrogs = $snapshot->frogs
            ->sortByDesc(fn ($f) => ($f->is_overdue ? 100 : 0) + (int) $f->postpone_count)
            ->take(3);

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

        return [new Fact(FactPriority::CORE, "Frösche, die liegen bleiben: {$parts}.", 'snapshot.frogs.top3')];
    }

    /** @return Fact[] */
    protected function factsPeople(?PlannerProjectSnapshot $snapshot): array
    {
        if (! $snapshot || $snapshot->people->isEmpty()) {
            return [];
        }
        $topPeople = $snapshot->people
            ->filter(fn ($p) => (int) $p->open_tasks > 0)
            ->sortByDesc('open_tasks')
            ->take(3);

        if ($topPeople->isEmpty()) {
            return [];
        }

        $parts = $topPeople->map(function ($p) {
            $sp = (int) $p->sp_open > 0 ? ", {$p->sp_open} SP" : '';
            $od = (int) $p->overdue_tasks > 0 ? ", davon {$p->overdue_tasks} überfällig" : '';
            return "{$p->user_name}: {$p->open_tasks} offen{$sp}{$od}";
        })->implode('; ');

        return [new Fact(FactPriority::QUALIFYING, "Aktuelle Workload-Verteilung: {$parts}.", 'snapshot.people.top3')];
    }

    /** @return Fact[] */
    protected function factsCanvas(PlannerProject $project): array
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

        // Highlights: pro befuelltem Block die ersten 1-2 Entries (max 6 insgesamt)
        $highlights = [];
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
                // truncate long content
                if (mb_strlen($combined) > 140) {
                    $combined = mb_substr($combined, 0, 137) . '...';
                }
                $highlights[] = "[{$block->label}] {$combined}";
                if (count($highlights) >= 6) {
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
        if ($days < 0) {
            return [new Fact(FactPriority::CORE, "Geplanter Endtermin liegt " . abs($days) . " Tage zurück.", 'snapshot.days_to_end')];
        }
        if ($days <= 14) {
            return [new Fact(FactPriority::QUALIFYING, "Geplanter Endtermin in {$days} Tagen.", 'snapshot.days_to_end')];
        }
        return [new Fact(FactPriority::CONTEXT, "Geplanter Endtermin in {$days} Tagen.", 'snapshot.days_to_end')];
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
        if (! $project->user_id) {
            return [];
        }
        return [new Edge(
            relation: 'verantwortet_von',
            targetType: 'person',
            targetId: (string) $project->user_id,
            targetLabel: $project->user?->name ?? ('User #' . $project->user_id),
            claim: Claim::systemVerified(),
            weight: FactPriority::CORE,
        )];
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
