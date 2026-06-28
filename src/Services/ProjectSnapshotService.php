<?php

namespace Platform\Planner\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\Analysis\TrafficLightAnalyzer;

/**
 * Erstellt einen Projekt-Snapshot mit allen Skalaren + Sub-Tabellen (slots/frogs/people).
 *
 * Idempotent: max 1 Snapshot pro Projekt pro Tag — existiert er, wird er ueberschrieben.
 * Composite-Health: Worst-of-Four-Ampel + gewichteter Score.
 * Top-5 Frogs: overdue zuerst, dann due_date asc, dann postpone_count desc.
 * People: nur User mit >=1 offener Aufgabe.
 */
class ProjectSnapshotService
{
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
        $openTasks = $tasks->where('is_done', false);
        $doneTasks = $tasks->where('is_done', true);
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

        $primaryCanvas = $project->canvases->first();
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
            $axes['progress'] = $this->progressScore($doneTasks->count(), $tasks->count());
        }
        $axes['burn'] = $this->burnScore(
            $overdueTasks->count(),
            $frogTasks->count(),
            $minutesLogged,
            $minutesPlanned,
            $budgetUsed,
            $project->budget_amount ? (float) $project->budget_amount : null,
        );

        [$healthScore, $healthColor] = $this->compositeHealth($axes);
        $worstAxis = $this->resolveWorstAxis($axes);

        [$confScore, $confReason] = $this->computeConfidence([
            'canvas' => $canvasScore !== null,
            'planned_period' => $plannedStart || $plannedEnd,
            'planned_minutes' => $minutesPlanned > 0,
            'tasks' => $tasks->count() > 0,
        ]);

        // Confidence-Gate: bei zu duenner Datenbasis ist die Ampel "gray" und der Score null —
        // ein 100er-Score ohne Datenbasis (z.B. "keine Tasks → keine Frogs → burn=100") ist
        // irrefuehrend. Lieber ehrlich "wissen wir nicht" als faelschlich "alles top".
        if ($confScore < 50) {
            if ($healthColor !== null) {
                $healthColor = 'gray';
            }
            $healthScore = null;
        }

        return [
            'team_id' => $project->team_id,
            'kind' => $project->kind?->value,
            'status' => $project->status?->value,
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

    private function progressScore(int $done, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }
        return (int) round(($done / $total) * 100);
    }

    private function burnScore(int $overdue, int $frogs, int $minutesLogged, int $minutesPlanned, ?float $budgetUsed, ?float $budgetAmount): int
    {
        $score = 100;
        $score -= min(40, $overdue * 10);
        $score -= min(20, $frogs * 5);
        if ($minutesPlanned > 0 && $minutesLogged > $minutesPlanned * 1.1) {
            $score -= 20;
        }
        if ($budgetAmount !== null && $budgetAmount > 0 && $budgetUsed !== null && $budgetUsed > $budgetAmount) {
            $score -= 20;
        }
        return max(0, $score);
    }

    private function compositeHealth(array $axes): array
    {
        if (empty($axes)) {
            return [null, null];
        }

        // Gewichte addieren auf 100. Plan-Achse ist entfernt — Pflege-Status sitzt in Confidence.
        $weights = [
            'strategy' => 30,
            'progress' => 40,
            'burn' => 30,
        ];

        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($axes as $key => $val) {
            $w = $weights[$key] ?? 25;
            $totalWeight += $w;
            $weightedSum += $w * $val;
        }
        $score = $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : null;

        $colors = array_map(fn ($v) => $this->valueToColor((int) $v), $axes);
        $color = 'green';
        if (in_array('red', $colors, true)) {
            $color = 'red';
        } elseif (in_array('yellow', $colors, true)) {
            $color = 'yellow';
        }

        return [$score, $color];
    }

    /**
     * Die "schwaechste" Achse: zuerst rote, dann gelbe; bei Gleichstand die mit dem niedrigsten Score.
     * Null, wenn keine Achsen vorhanden oder alle gruen sind.
     */
    private function resolveWorstAxis(array $axes): ?string
    {
        if (empty($axes)) {
            return null;
        }
        $colorRank = ['red' => 0, 'yellow' => 1, 'green' => 2];
        $bestColorRank = 9;
        $bestScore = PHP_INT_MAX;
        $bestKey = null;
        foreach ($axes as $key => $val) {
            $color = $this->valueToColor((int) $val);
            $rank = $colorRank[$color] ?? 9;
            if ($rank < $bestColorRank || ($rank === $bestColorRank && $val < $bestScore)) {
                $bestColorRank = $rank;
                $bestScore = (int) $val;
                $bestKey = $key;
            }
        }
        // Nur wenn schwaechste Achse rot oder gelb ist, melden wir sie — alles gruen ist kein "worst".
        if ($bestColorRank > 1) {
            return null;
        }
        return $bestKey;
    }

    private function valueToColor(int $value): string
    {
        if ($value >= 70) {
            return 'green';
        }
        if ($value >= 40) {
            return 'yellow';
        }
        return 'red';
    }

    private function computeConfidence(array $hasData): array
    {
        // 25 Punkte pro Datenebene — 4 Ebenen, max 100
        $present = array_filter($hasData);
        $score = count($present) * 25;
        $missing = array_keys(array_filter($hasData, fn ($v) => ! $v));
        $reason = empty($missing) ? null : 'missing:' . implode(',', $missing);
        return [$score, $reason];
    }

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
            $open = $slotTasks->where('is_done', false)->count();
            $done = $slotTasks->where('is_done', true)->count();

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
            ->where('is_done', false)
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
            $openTasks = $userTasks->where('is_done', false);
            if ($openTasks->isEmpty()) {
                continue;
            }
            $doneTasks = $userTasks->where('is_done', true);

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
