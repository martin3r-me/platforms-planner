<?php

namespace Platform\Planner\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;

/**
 * Aggregat-Sicht ueber die juengsten Project-Snapshots:
 * - Verteilung nach Health-Ampel
 * - Verteilung nach Confidence-Bucket
 * - Top-N "rote" Projekte
 * - Top-N daten-arme Projekte
 *
 * Default-Scope: aktuelles Team des Users (oder explizit via team_id).
 */
class ListProjectSnapshotsSummaryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project_snapshots.summary';
    }

    public function getDescription(): string
    {
        return 'GET /project-snapshots/summary - Aggregat-Sicht ueber die juengsten Project-Snapshots eines Teams: Health-Ampel-Verteilung (red/yellow/green/gray), Confidence-Verteilung (high/medium/low/none), Top-N rote Projekte, Top-N daten-arme Projekte. Optional team_id (default: aktuelles Team), top_n (default 5).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team des Users.'],
                'top_n' => ['type' => 'integer', 'description' => 'Optional: Anzahl der Top-Eintraege in worst_health + low_confidence. Default 5, max 20.'],
                'include_done' => ['type' => 'boolean', 'description' => 'Optional: bezieht auch als done markierte Projekte ein. Default true.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $teamId = (int) ($arguments['team_id'] ?? ($context->team?->id ?? 0));
            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Team im Kontext und kein team_id gegeben.');
            }

            $topN = max(1, min(20, (int) ($arguments['top_n'] ?? 5)));
            $includeDone = $arguments['include_done'] ?? true;

            // Juengsten Snapshot je Projekt holen (per Subquery)
            $latestIds = DB::table('planner_project_snapshots as a')
                ->where('a.team_id', $teamId)
                ->whereRaw('a.taken_on = (
                    SELECT MAX(b.taken_on) FROM planner_project_snapshots b
                    WHERE b.project_id = a.project_id
                )')
                ->pluck('a.id');

            $latest = PlannerProjectSnapshot::with('project:id,name,kind,status,done')
                ->whereIn('id', $latestIds)
                ->get();

            if (! $includeDone) {
                $latest = $latest->filter(fn ($s) => ! ($s->project?->done ?? false))->values();
            }

            $total = $latest->count();
            if ($total === 0) {
                return ToolResult::success([
                    'team_id' => $teamId,
                    'total_projects' => 0,
                    'message' => 'Noch keine Snapshots fuer Projekte in diesem Team vorhanden.',
                ]);
            }

            // Health-Verteilung
            $healthDist = ['green' => 0, 'yellow' => 0, 'red' => 0, 'gray' => 0];
            foreach ($latest as $s) {
                $key = $s->health_color ?: 'gray';
                $healthDist[$key] = ($healthDist[$key] ?? 0) + 1;
            }

            // Confidence-Verteilung
            $confDist = ['high_75_100' => 0, 'medium_50_74' => 0, 'low_25_49' => 0, 'none_0_24' => 0];
            foreach ($latest as $s) {
                $c = (int) $s->confidence_score;
                if ($c >= 75) {
                    $confDist['high_75_100']++;
                } elseif ($c >= 50) {
                    $confDist['medium_50_74']++;
                } elseif ($c >= 25) {
                    $confDist['low_25_49']++;
                } else {
                    $confDist['none_0_24']++;
                }
            }

            // Datenbasis-Lücken (welche Ebene fehlt am haeufigsten?)
            $missingDist = ['canvas' => 0, 'planned_period' => 0, 'planned_minutes' => 0, 'tasks' => 0];
            foreach ($latest as $s) {
                $reason = $s->confidence_reason ?? '';
                if (! str_starts_with($reason, 'missing:')) {
                    continue;
                }
                $items = explode(',', substr($reason, strlen('missing:')));
                foreach ($items as $item) {
                    $item = trim($item);
                    if (isset($missingDist[$item])) {
                        $missingDist[$item]++;
                    }
                }
            }

            // Top-N worst-health (color=red first, then lowest score)
            $colorRank = ['red' => 0, 'yellow' => 1, 'green' => 2, 'gray' => 3];
            $worstHealth = $latest
                ->sort(function ($a, $b) use ($colorRank) {
                    $ra = $colorRank[$a->health_color ?? 'gray'] ?? 9;
                    $rb = $colorRank[$b->health_color ?? 'gray'] ?? 9;
                    if ($ra !== $rb) {
                        return $ra <=> $rb;
                    }
                    return (int) ($a->health_score ?? 999) <=> (int) ($b->health_score ?? 999);
                })
                ->take($topN)
                ->map(fn (PlannerProjectSnapshot $s) => $this->compact($s))
                ->values()
                ->all();

            // Top-N low-confidence
            $lowConfidence = $latest
                ->sortBy('confidence_score')
                ->take($topN)
                ->map(fn (PlannerProjectSnapshot $s) => $this->compact($s))
                ->values()
                ->all();

            return ToolResult::success([
                'team_id' => $teamId,
                'taken_on_range' => [
                    'from' => $latest->min('taken_on')?->toDateString(),
                    'to' => $latest->max('taken_on')?->toDateString(),
                ],
                'total_projects' => $total,
                'health_distribution' => $healthDist,
                'confidence_distribution' => $confDist,
                'missing_data_layers' => $missingDist,
                'worst_health' => $worstHealth,
                'low_confidence' => $lowConfidence,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    private function compact(PlannerProjectSnapshot $s): array
    {
        return [
            'project_id' => $s->project_id,
            'project_name' => $s->project?->name,
            'kind' => $s->kind,
            'status' => $s->status,
            'health_score' => $s->health_score,
            'health_color' => $s->health_color,
            'worst_axis' => $s->worst_axis,
            'confidence_score' => $s->confidence_score,
            'confidence_reason' => $s->confidence_reason,
            'tasks_open' => $s->tasks_open,
            'tasks_overdue' => $s->tasks_overdue,
            'tasks_frog' => $s->tasks_frog,
            'taken_on' => $s->taken_on?->toDateString(),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'project', 'snapshot', 'summary', 'aggregate'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
