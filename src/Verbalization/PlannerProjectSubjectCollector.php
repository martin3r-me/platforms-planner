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
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;

/**
 * Sammler fuer PlannerProject — Knoten + Snapshot + Live-Topup -> Subject.
 *
 * Strategie:
 *  - Stammdaten: live aus dem Project-Model (billig)
 *  - Aggregate (Task-Counts, Health): juengster Snapshot (heute Nacht)
 *  - Live-Topup: Tasks done seit Snapshot (damit der Verbalizer ehrlich
 *    formulieren kann "Stand 3 Uhr, seither X erledigt")
 */
class PlannerProjectSubjectCollector
{
    public function collectState(int|PlannerProject $project): Subject
    {
        $project = $project instanceof PlannerProject
            ? $project
            : PlannerProject::with('user:id,name')->findOrFail($project);

        $snapshot = PlannerProjectSnapshot::where('project_id', $project->id)
            ->orderByDesc('taken_on')
            ->first();

        [$source, $asOf] = $this->resolveFreshness($snapshot);

        $facts = $this->buildFacts($project, $snapshot);
        $edges = $this->buildEdges($project);

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

    /**
     * @return array{0: DataSource, 1: \DateTimeInterface}
     */
    protected function resolveFreshness(?PlannerProjectSnapshot $snapshot): array
    {
        if (! $snapshot) {
            return [DataSource::LIVE, now()];
        }
        $taken = $snapshot->taken_at ?? $snapshot->taken_on?->setTime(0, 0) ?? now();
        return [DataSource::SNAPSHOT_WITH_LIVE_TOPUP, $taken];
    }

    /**
     * @return Fact[]
     */
    protected function buildFacts(PlannerProject $project, ?PlannerProjectSnapshot $snapshot): array
    {
        $facts = [];

        // CORE: was den Knoten ueberhaupt charakterisiert
        $kindLabel = match ($project->kind) {
            ProjectKind::PROJECT => 'Projekt',
            ProjectKind::RUN => 'Run',
            default => 'Vorhaben',
        };
        $statusLabel = $project->done ? 'erledigt' : ($project->status?->value ?? 'aktiv');
        $facts[] = new Fact(FactPriority::CORE, "{$kindLabel} im Status: {$statusLabel}.", 'project.kind+status');

        if ($snapshot) {
            // Health-Score nur erwaehnen, wenn die Confidence ausreicht (sonst lieber schweigen).
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
            } else {
                $facts[] = new Fact(FactPriority::CONTEXT, "Health-Score liegt nicht vor (Datenbasis zu duenn).", 'snapshot.health.missing');
            }

            // Task-Lage: Snapshot + Live-Topup
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
                $taskTxt .= ", {$snapshot->tasks_overdue} ueberfaellig";
            }
            if ($snapshot->tasks_frog > 0) {
                $taskTxt .= ", {$snapshot->tasks_frog} davon Froesche";
            }
            $facts[] = new Fact(FactPriority::CORE, $taskTxt . '.', 'snapshot.tasks+live.done');

            // Story Points wenn vorhanden
            if ($snapshot->story_points_total !== null && $snapshot->story_points_total > 0) {
                $spDone = (int) $snapshot->story_points_done;
                $spTotal = (int) $snapshot->story_points_total;
                $pct = $spTotal > 0 ? round($spDone / $spTotal * 100) : 0;
                $facts[] = new Fact(FactPriority::QUALIFYING, "{$spDone} von {$spTotal} Story Points erledigt ({$pct}%).", 'snapshot.story_points');
            }

            // Canvas-Status
            if ($snapshot->canvas_completeness_percent !== null) {
                $pct = (int) $snapshot->canvas_completeness_percent;
                $facts[] = new Fact(FactPriority::QUALIFYING, "Canvas zu {$pct}% gefuellt.", 'snapshot.canvas_completeness');
            }

            // Termine
            if ($snapshot->days_to_planned_end !== null) {
                $days = (int) $snapshot->days_to_planned_end;
                if ($days < 0) {
                    $facts[] = new Fact(FactPriority::CORE, "Geplanter Endtermin liegt " . abs($days) . " Tage zurueck.", 'snapshot.days_to_end');
                } elseif ($days <= 14) {
                    $facts[] = new Fact(FactPriority::QUALIFYING, "Geplanter Endtermin in {$days} Tagen.", 'snapshot.days_to_end');
                } else {
                    $facts[] = new Fact(FactPriority::CONTEXT, "Geplanter Endtermin in {$days} Tagen.", 'snapshot.days_to_end');
                }
            }

            // Confidence (Beiwerk, aber wichtig fuer Aussage-Geltung)
            if ($snapshot->confidence_score !== null) {
                $cs = (int) $snapshot->confidence_score;
                $reason = $snapshot->confidence_reason;
                if ($cs < 50) {
                    $facts[] = new Fact(
                        FactPriority::CONTEXT,
                        "Datenbasis fuer Bewertung unvollstaendig (Konfidenz {$cs}/100" . ($reason ? ', Grund: ' . $reason : '') . ').',
                        'snapshot.confidence',
                    );
                }
            }
        } else {
            $facts[] = new Fact(FactPriority::CONTEXT, 'Es liegen noch keine Snapshot-Daten zu diesem Knoten vor.', 'snapshot.absent');
        }

        return $facts;
    }

    /**
     * @return Edge[]
     */
    protected function buildEdges(PlannerProject $project): array
    {
        $edges = [];

        if ($project->user_id) {
            $name = $project->user?->name ?? ('User #' . $project->user_id);
            $edges[] = new Edge(
                relation: 'verantwortet_von',
                targetType: 'person',
                targetId: (string) $project->user_id,
                targetLabel: $name,
                claim: Claim::systemVerified(),
                weight: FactPriority::CORE,
            );
        }

        if ($project->customer_cost_center) {
            $edges[] = new Edge(
                relation: 'abgerechnet_auf',
                targetType: 'cost_center',
                targetId: null,
                targetLabel: $project->customer_cost_center,
                claim: Claim::systemVerified(),
                weight: FactPriority::CONTEXT,
            );
        }

        return $edges;
    }
}
