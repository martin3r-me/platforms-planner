<?php

namespace Platform\Planner\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Core\Health\Services\ConfidenceCalculator;
use Platform\Core\Health\Services\HealthCompositor;
use Platform\Core\Models\User;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\Analysis\TrafficLightAnalyzer;

/**
 * Erstellt einen Projekt-Snapshot mit allen Skalaren + Sub-Tabellen (slots/frogs/people).
 *
 * Nutzt die core Health-Library (HealthCompositor + ConfidenceCalculator) — gleiches
 * Pattern wie Helpdesk und Dev. Planner-spezifisch bleibt: Achsen-Definitionen
 * (strategy/progress/burn), Sub-Tabellen, last_movement_at-Berechnung.
 *
 * Idempotent: max 1 Snapshot pro Projekt pro Tag — existiert er, wird er ueberschrieben.
 * Top-5 Frogs: overdue zuerst, dann due_date asc, dann postpone_count desc.
 * People: nur User mit >=1 offener Aufgabe.
 */
class ProjectSnapshotService
{
    public function __construct(
        protected HealthCompositor $compositor,
        protected ConfidenceCalculator $confidence,
    ) {}

    public function snapshot(PlannerProject $project, string $trigger = 'cron'): PlannerProjectSnapshot
    {
        return DB::transaction(function () use ($project, $trigger) {
            $now = now();
            $today = $now->toDateString();

            $project->loadMissing([
                'tasks',
                'projectSlots',
                'canvases.blocks.entries',
            ]);

            $existing = PlannerProjectSnapshot::where('project_id', $project->id)
                ->whereDate('taken_on', $today)
                ->first();

            if ($existing) {
                $existing->slots()->delete();
                $existing->frogs()->delete();
                $existing->people()->delete();
            }

            $payload = $this->computeScalars($project, $now);

            $prev = PlannerProjectSnapshot::where('project_id', $project->id)
                ->whereDate('taken_on', '<', $today)
                ->orderByDesc('taken_on')
                ->first();

            if ($prev) {
                $payload['prev_snapshot_id'] = $prev->id;
                $payload['delta_health_score'] = $this->safeDelta($payload['health_score'], $prev->health_score);
                $payload['delta_canvas_score'] = $this->safeDelta($payload['canvas_score'], $prev->canvas_score);
                $payload['delta_tasks_done'] = $payload['tasks_done'] - (int) $prev->tasks_done;
            }

            $payload['trigger'] = $trigger;
            $payload['taken_at'] = $now;
            $payload['taken_on'] = $today;
            $payload['project_id'] = $project->id;

            if ($existing) {
                $existing->update($payload);
                $snapshot = $existing->fresh();
            } else {
                $snapshot = PlannerProjectSnapshot::create($payload);
            }

            $this->writeSlots($snapshot, $project);
            $this->writeFrogs($snapshot, $project);
            $this->writePeople($snapshot, $project);

            return $snapshot;
        });
    }

    private function safeDelta(?int $current, ?int $prev): ?int
    {
        if ($current === null || $prev === null) {
            return null;
        }
        return $current - $prev;
    }

    private function computeScalars(PlannerProject $project, Carbon $now): array
    {
        $tasks = $project->tasks;
        $openTasks = $tasks->where('lifecycle_state', TaskLifecycleState::ACTIVE);
        $doneTasks = $tasks->where('lifecycle_state', TaskLifecycleState::COMPLETED);
        $overdueTasks = $openTasks->filter(fn ($t) => $t->due_date && $t->due_date->isPast());
        $frogTasks = $openTasks->where('is_frog', true);
        $postponedTasks = $tasks->filter(fn ($t) => ((int) ($t->postpone_count ?? 0)) > 0);

        $spTotal = $tasks->sum(fn ($t) => $t->story_points?->points() ?? 0);
        $spOpen = $openTasks->sum(fn ($t) => $t->story_points?->points() ?? 0);
        $spDone = $doneTasks->sum(fn ($t) => $t->story_points?->points() ?? 0);

        $minutesPlanned = (int) $project->totalPlannedMinutes();
        $minutesLogged = (int) $project->totalLoggedMinutes();
        $minutesBilled = (int) $project->billedMinutes();
        $minutesUnbilled = (int) $project->unbilledMinutes();

        $hourlyRate = $project->hourly_rate ? (float) $project->hourly_rate : null;
        $budgetUsed = $hourlyRate ? round($minutesLogged / 60 * $hourlyRate, 2) : null;

        $plannedStart = $project->plannedStart();
        $plannedEnd = $project->plannedEnd();
        $daysToEnd = null;
        if ($plannedEnd) {
            $daysToEnd = (int) $now->copy()->startOfDay()->diffInDays($plannedEnd->copy()->startOfDay(), false);
        }

        // Canvas — erster Canvas am Projekt zaehlt als "Strategie-Snapshot".
        $canvasScore = $canvasColor = $canvasCompleteness = null;
        $canvasFilled = $canvasTotal = $canvasRisk = $canvasOverdueMs = null;

        // Das Projekt-Canvas (eins pro Projekt; bei Altbestand das aelteste nicht verworfene)
        $primaryCanvas = $project->canvases
            ->sortBy(fn ($c) => [$c->status === \Platform\Planner\Models\PlannerProjectCanvas::STATUS_DISCARDED ? 1 : 0, $c->id])
            ->first();
        if ($primaryCanvas) {
            try {
                $analyzer = new TrafficLightAnalyzer();
                $analysis = $analyzer->analyze($primaryCanvas, config('planner.canvas_analysis_config', []));
                $canvasScore = (int) ($analysis['score'] ?? 0);
                $canvasColor = $analysis['color'] ?? null;
                $canvasCompleteness = (float) ($analysis['completeness_percent'] ?? 0);
                $canvasFilled = (int) ($analysis['filled_blocks'] ?? 0);
                $canvasTotal = (int) ($analysis['total_blocks'] ?? 0);
                $canvasRisk = (int) ($analysis['risk_count'] ?? 0);
                $canvasOverdueMs = (int) ($analysis['overdue_milestones'] ?? 0);
            } catch (\Throwable $e) {
                // Canvas-Analyse fehlgeschlagen — Strategie-Achse bleibt null
            }
        }

        // Drei Health-Achsen (0..100, null wenn nicht berechenbar).
        // Plan-Achse bewusst NICHT mehr drin — misst nur Datenpflege, nicht Projektzustand.
        // Datenpflege-Status liegt in Confidence.
        $axes = [];
        if ($canvasScore !== null) {
            $axes['strategy'] = $canvasScore;
        }
        if ($tasks->count() > 0) {
            $axes['progress'] = self::progressScore($spDone, $spTotal, $doneTasks->count(), $tasks->count(), $plannedStart, $plannedEnd, $now);
        }
        $axes['burn'] = $this->burnScore(
            $overdueTasks->count(),
            $frogTasks->count(),
            $minutesLogged,
            $minutesPlanned,
            $budgetUsed,
            $project->budget_amount ? (float) $project->budget_amount : null,
        );

        // Lifetime-Signal: junge Projekte (< 24h alt) sind noch nicht "ausgereift"
        // genug fuer eine Health-Aussage — fliesst als A) Subject-Level Confidence-Punkt
        // und C) als progress-Achsen-Aussagekraft ein.
        $hoursOld = $project->created_at ? (int) $project->created_at->diffInHours(now()) : 24 * 365;
        $isMature = $hoursOld >= 24;

        // Confidence-Berechnung (core) — A: lifetime_mature als zusaetzlicher Datenpunkt
        ['score' => $confScore, 'reason' => $confReason] = $this->confidence->compute([
            'canvas' => $canvasScore !== null,
            'planned_period' => $plannedStart || $plannedEnd,
            'planned_minutes' => $minutesPlanned > 0,
            'tasks' => $tasks->count() > 0,
            'lifetime_mature' => $isMature,
        ]);

        // C) Axis-Confidence: progress-Achse ist bei jungen Projekten nicht aussagekraeftig.
        // Linear ramp ueber 7 Tage: 0h → 0.0, 24h → ~0.14, 7d → 1.0.
        $progressConfidence = max(0.0, min(1.0, $hoursOld / (24 * 7)));
        $axisConfidence = [
            'progress' => $progressConfidence,
            // strategy + burn bleiben default 1.0 (volle Aussagekraft)
        ];

        // Composite (core) — Worst-of-Color + gewichteter Score + Confidence-Gate + Axis-Confidence
        $weights = ['strategy' => 30, 'progress' => 40, 'burn' => 30];
        $composed = $this->compositor->compose(
            $axes,
            $weights,
            $confScore,
            axisConfidence: $axisConfidence,
        );
        $healthScore = $composed['score'];
        $healthColor = $composed['color'];
        $worstAxis = $composed['worst_axis'];

        // Snapshot.status column continues to exist for historical audit but
        // now stores the lifecycle_state string (aktiv/ruhend/abgeschlossen/verworfen).
        // Later Schritt: rename column to lifecycle_state or drop it entirely.
        return [
            'team_id' => $project->team_id,
            'kind' => $project->kind?->value,
            'status' => $project->lifecycle_state?->value ?? 'aktiv',
            'color' => $project->color,

            'tasks_total' => $tasks->count(),
            'tasks_open' => $openTasks->count(),
            'tasks_done' => $doneTasks->count(),
            'tasks_overdue' => $overdueTasks->count(),
            'tasks_frog' => $frogTasks->count(),
            'tasks_postponed' => $postponedTasks->count(),

            'story_points_total' => $spTotal,
            'story_points_open' => $spOpen,
            'story_points_done' => $spDone,

            'minutes_planned' => $minutesPlanned,
            'minutes_logged' => $minutesLogged,
            'minutes_billed' => $minutesBilled,
            'minutes_unbilled' => $minutesUnbilled,

            'budget_amount' => $project->budget_amount,
            'hourly_rate' => $project->hourly_rate,
            'currency' => $project->currency,
            'budget_used_euro' => $budgetUsed,

            'planned_start' => $plannedStart?->toDateString(),
            'planned_end' => $plannedEnd?->toDateString(),
            'days_to_planned_end' => $daysToEnd,

            'canvas_score' => $canvasScore,
            'canvas_color' => $canvasColor,
            'canvas_completeness_percent' => $canvasCompleteness,
            'canvas_filled_blocks' => $canvasFilled,
            'canvas_total_blocks' => $canvasTotal,
            'canvas_risk_count' => $canvasRisk,
            'canvas_overdue_milestones' => $canvasOverdueMs,

            'health_score' => $healthScore,
            'health_color' => $healthColor,
            'worst_axis' => $worstAxis,
            'axis_scores' => empty($axes) ? null : $axes,

            'confidence_score' => $confScore,
            'confidence_reason' => $confReason,

            'last_movement_at' => $this->computeLastMovement($project),
        ];
    }

    // Abzüge der Druck-Achse — eine Quelle für Berechnung und Erklärung (explain())
    public const BURN_PER_OVERDUE = 10;
    public const BURN_MAX_OVERDUE = 40;
    public const BURN_PER_FROG = 5;
    public const BURN_MAX_FROGS = 20;
    public const BURN_HOURS_OVER = 20;
    public const BURN_BUDGET_OVER = 20;

    /**
     * Fortschritt gemessen an der Komplexität (Story Points; ohne Schätzung: Anzahl)
     * und — wenn ein Zeitraum geplant ist — relativ zum erwarteten Stand:
     * pro Prozentpunkt Rückstand gegenüber der verstrichenen Zeit 2 Punkte Abzug.
     * Ohne Zeitraum bleibt es beim reinen Erledigt-Anteil.
     */
    public static function progressScore(int $spDone, int $spTotal, int $done, int $total, ?Carbon $start, ?Carbon $end, ?Carbon $now = null): int
    {
        $actual = self::progressPercent($spDone, $spTotal, $done, $total);
        if ($actual === null) {
            return 0;
        }

        $expected = self::expectedPercent($start, $end, $now);
        if ($expected === null) {
            return (int) round($actual);
        }

        $gap = max(0, $expected - $actual);

        return (int) max(0, min(100, round(100 - 2 * $gap)));
    }

    /** Erledigt-Anteil in % — nach Story Points, sonst nach Anzahl; null ohne Aufgaben. */
    public static function progressPercent(int $spDone, int $spTotal, int $done, int $total): ?float
    {
        if ($spTotal > 0) {
            return $spDone / $spTotal * 100;
        }

        return $total > 0 ? $done / $total * 100 : null;
    }

    /** Verstrichener Anteil des geplanten Zeitraums in % (0..100), null ohne Zeitraum. */
    public static function expectedPercent(?Carbon $start, ?Carbon $end, ?Carbon $now = null): ?float
    {
        if (! $start || ! $end || $end->lte($start)) {
            return null;
        }
        $now ??= now();
        $total = $start->diffInSeconds($end);
        $elapsed = $start->diffInSeconds($now, false);

        return max(0, min(100, $elapsed / $total * 100));
    }

    private function burnScore(int $overdue, int $frogs, int $minutesLogged, int $minutesPlanned, ?float $budgetUsed, ?float $budgetAmount): int
    {
        $score = 100;
        $score -= min(self::BURN_MAX_OVERDUE, $overdue * self::BURN_PER_OVERDUE);
        $score -= min(self::BURN_MAX_FROGS, $frogs * self::BURN_PER_FROG);
        if ($minutesPlanned > 0 && $minutesLogged > $minutesPlanned * 1.1) {
            $score -= self::BURN_HOURS_OVER;
        }
        if ($budgetAmount !== null && $budgetAmount > 0 && $budgetUsed !== null && $budgetUsed > $budgetAmount) {
            $score -= self::BURN_BUDGET_OVER;
        }
        return max(0, $score);
    }

    /**
     * Warum hat jede Achse ihren Wert? Konkrete Gründe aus den Snapshot-Feldern —
     * dieselben Regeln wie in der Berechnung.
     *
     * @return array<string, array{reasons: array<int, array{text: string, delta: ?int}>}>
     */
    public static function explain(PlannerProjectSnapshot $s): array
    {
        $out = ['strategy' => ['reasons' => []], 'progress' => ['reasons' => []], 'burn' => ['reasons' => []]];

        // Strategie (Canvas)
        if ($s->canvas_score === null) {
            $out['strategy']['reasons'][] = ['text' => 'Kein Canvas — Achse wird nicht gerechnet', 'delta' => null];
        } else {
            $out['strategy']['reasons'][] = ['text' => "Canvas zu {$s->canvas_completeness_percent} % ausgefüllt ({$s->canvas_filled_blocks} von {$s->canvas_total_blocks} Blöcken)", 'delta' => null];
            if ((int) $s->canvas_overdue_milestones > 0) {
                $out['strategy']['reasons'][] = ['text' => "{$s->canvas_overdue_milestones} überfällige(r) Meilenstein(e)", 'delta' => null];
            }
            if ((int) $s->canvas_risk_count === 0) {
                $out['strategy']['reasons'][] = ['text' => 'Keine Risiken im Canvas benannt', 'delta' => null];
            } elseif ((int) $s->canvas_risk_count > 5) {
                $out['strategy']['reasons'][] = ['text' => "Viele Risiken im Canvas ({$s->canvas_risk_count})", 'delta' => null];
            }
        }

        // Fortschritt
        $actual = self::progressPercent((int) $s->story_points_done, (int) $s->story_points_total, (int) $s->tasks_done, (int) $s->tasks_total);
        $start = $s->planned_start ? Carbon::parse($s->planned_start) : null;
        $end = $s->planned_end ? Carbon::parse($s->planned_end) : null;
        $expected = self::expectedPercent($start, $end, $s->taken_at ? Carbon::parse($s->taken_at) : null);
        if ($actual === null) {
            $out['progress']['reasons'][] = ['text' => 'Keine Aufgaben', 'delta' => null];
        } else {
            $basis = (int) $s->story_points_total > 0
                ? "{$s->story_points_done} von {$s->story_points_total} SP erledigt"
                : "{$s->tasks_done} von {$s->tasks_total} Aufgaben erledigt (keine Story Points)";
            $out['progress']['reasons'][] = ['text' => $basis . ' — ' . (int) round($actual) . ' %', 'delta' => null];
            if ($expected === null) {
                $out['progress']['reasons'][] = ['text' => 'Kein Zeitraum geplant — Fortschritt nicht am Soll gemessen', 'delta' => null];
            } else {
                $gap = (int) round($expected - $actual);
                $out['progress']['reasons'][] = $gap > 0
                    ? ['text' => (int) round($expected) . " % der Zeit verstrichen — {$gap} Prozentpunkte Rückstand", 'delta' => -min(100, 2 * $gap)]
                    : ['text' => (int) round($expected) . ' % der Zeit verstrichen — im Soll', 'delta' => null];
            }
        }

        // Druck
        if ((int) $s->tasks_overdue > 0) {
            $out['burn']['reasons'][] = ['text' => "{$s->tasks_overdue} überfällige Aufgabe(n)", 'delta' => -min(self::BURN_MAX_OVERDUE, $s->tasks_overdue * self::BURN_PER_OVERDUE)];
        }
        if ((int) $s->tasks_frog > 0) {
            $out['burn']['reasons'][] = ['text' => "{$s->tasks_frog} offene(r) Frosch/Frösche", 'delta' => -min(self::BURN_MAX_FROGS, $s->tasks_frog * self::BURN_PER_FROG)];
        }
        if ((int) $s->minutes_planned > 0 && $s->minutes_logged > $s->minutes_planned * 1.1) {
            $out['burn']['reasons'][] = ['text' => round($s->minutes_logged / 60, 1) . ' h erfasst bei ' . round($s->minutes_planned / 60, 1) . ' h geplant', 'delta' => -self::BURN_HOURS_OVER];
        }
        if ((float) $s->budget_amount > 0 && $s->budget_used_euro !== null && (float) $s->budget_used_euro > (float) $s->budget_amount) {
            $out['burn']['reasons'][] = ['text' => 'Budget überschritten (' . number_format((float) $s->budget_used_euro, 0, ',', '.') . ' von ' . number_format((float) $s->budget_amount, 0, ',', '.') . ' ' . ($s->currency ?? 'EUR') . ')', 'delta' => -self::BURN_BUDGET_OVER];
        }
        if (empty($out['burn']['reasons'])) {
            $out['burn']['reasons'][] = ['text' => 'Nichts überfällig, keine Frösche, Stunden und Budget im Rahmen', 'delta' => null];
        }

        return $out;
    }

    // compositeHealth / resolveWorstAxis / valueToColor / computeConfidence
    // wurden in die core Health-Library verschoben (HealthCompositor +
    // ConfidenceCalculator + HealthColor-Enum). Planner ruft die ueber DI.

    private function computeLastMovement(PlannerProject $project): ?Carbon
    {
        $candidates = [];

        $lastTaskUpdate = PlannerTask::where('project_id', $project->id)->max('updated_at');
        if ($lastTaskUpdate) {
            $candidates[] = Carbon::parse($lastTaskUpdate);
        }

        $lastProjectTimeEntry = DB::table('organization_time_entries')
            ->where('context_type', PlannerProject::class)
            ->where('context_id', $project->id)
            ->whereNull('deleted_at')
            ->max('created_at');
        if ($lastProjectTimeEntry) {
            $candidates[] = Carbon::parse($lastProjectTimeEntry);
        }

        $taskIds = PlannerTask::where('project_id', $project->id)->pluck('id');
        if ($taskIds->isNotEmpty()) {
            $lastTaskTimeEntry = DB::table('organization_time_entries')
                ->where('context_type', PlannerTask::class)
                ->whereIn('context_id', $taskIds)
                ->whereNull('deleted_at')
                ->max('created_at');
            if ($lastTaskTimeEntry) {
                $candidates[] = Carbon::parse($lastTaskTimeEntry);
            }
        }

        $lastCanvasUpdate = $project->canvases->max('updated_at');
        if ($lastCanvasUpdate) {
            $candidates[] = Carbon::parse($lastCanvasUpdate);
        }

        if (empty($candidates)) {
            return null;
        }

        return collect($candidates)->max();
    }

    private function writeSlots(PlannerProjectSnapshot $snapshot, PlannerProject $project): void
    {
        foreach ($project->projectSlots as $slot) {
            $slotTasks = $project->tasks->where('project_slot_id', $slot->id);
            $open = $slotTasks->where('lifecycle_state', TaskLifecycleState::ACTIVE)->count();
            $done = $slotTasks->where('lifecycle_state', TaskLifecycleState::COMPLETED)->count();

            $snapshot->slots()->create([
                'slot_id' => $slot->id,
                'slot_name' => mb_substr((string) ($slot->name ?? '—'), 0, 255),
                'slot_order' => (int) ($slot->order ?? 0),
                'open_tasks' => $open,
                'done_tasks' => $done,
                'total_tasks' => $slotTasks->count(),
            ]);
        }
    }

    private function writeFrogs(PlannerProjectSnapshot $snapshot, PlannerProject $project): void
    {
        $frogs = $project->tasks
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->where('is_frog', true)
            ->sort(function ($a, $b) {
                $aOverdue = ($a->due_date && $a->due_date->isPast()) ? 0 : 1;
                $bOverdue = ($b->due_date && $b->due_date->isPast()) ? 0 : 1;
                if ($aOverdue !== $bOverdue) {
                    return $aOverdue <=> $bOverdue;
                }
                $aDue = $a->due_date ? $a->due_date->timestamp : PHP_INT_MAX;
                $bDue = $b->due_date ? $b->due_date->timestamp : PHP_INT_MAX;
                if ($aDue !== $bDue) {
                    return $aDue <=> $bDue;
                }
                return ((int) ($b->postpone_count ?? 0)) <=> ((int) ($a->postpone_count ?? 0));
            })
            ->take(5)
            ->values();

        foreach ($frogs as $idx => $task) {
            $snapshot->frogs()->create([
                'task_id' => $task->id,
                'task_uuid' => $task->uuid,
                'task_title' => mb_substr((string) ($task->title ?? '—'), 0, 500),
                'due_date' => $task->due_date,
                'is_overdue' => $task->due_date && $task->due_date->isPast(),
                'postpone_count' => (int) ($task->postpone_count ?? 0),
                'story_points' => $task->story_points?->value,
                'rank' => $idx + 1,
            ]);
        }
    }

    private function writePeople(PlannerProjectSnapshot $snapshot, PlannerProject $project): void
    {
        $byUser = $project->tasks
            ->whereNotNull('user_in_charge_id')
            ->groupBy('user_in_charge_id');

        if ($byUser->isEmpty()) {
            return;
        }

        $userIds = $byUser->keys()->all();
        $userNames = User::whereIn('id', $userIds)->pluck('name', 'id');

        foreach ($byUser as $userId => $userTasks) {
            $openTasks = $userTasks->where('lifecycle_state', TaskLifecycleState::ACTIVE);
            if ($openTasks->isEmpty()) {
                continue;
            }
            $doneTasks = $userTasks->where('lifecycle_state', TaskLifecycleState::COMPLETED);

            $snapshot->people()->create([
                'user_id' => $userId,
                'user_name' => mb_substr((string) ($userNames[$userId] ?? ('User #' . $userId)), 0, 255),
                'open_tasks' => $openTasks->count(),
                'done_tasks' => $doneTasks->count(),
                'sp_open' => $openTasks->sum(fn ($t) => $t->story_points?->points() ?? 0),
                'sp_done' => $doneTasks->sum(fn ($t) => $t->story_points?->points() ?? 0),
                'overdue_tasks' => $openTasks->filter(fn ($t) => $t->due_date && $t->due_date->isPast())->count(),
            ]);
        }
    }
}
