<?php

namespace Platform\Planner\Tools;

use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;

/**
 * Zeitreihe der wichtigsten Snapshot-Metriken eines Projekts.
 * Liefert eine flache Liste von Stuetzpunkten — zum Plotten gedacht.
 */
class GetProjectSnapshotTrendTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project_snapshots.trend';
    }

    public function getDescription(): string
    {
        return 'GET /project-snapshots/trend - Zeitreihe der Health-Score, Canvas-Score, Task-Counts und Burn-Werte eines Projekts. Default: letzte 30 Tage. days=N (1..365) oder from/to (YYYY-MM-DD) zum Eingrenzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'Projekt-ID (ERFORDERLICH).'],
                'days' => ['type' => 'integer', 'description' => 'Optional: letzte N Tage (1..365). Default 30.'],
                'from' => ['type' => 'string', 'description' => 'Optional: Startdatum YYYY-MM-DD (ueberschreibt days).'],
                'to' => ['type' => 'string', 'description' => 'Optional: Enddatum YYYY-MM-DD. Default heute.'],
            ],
            'required' => ['project_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            if (empty($arguments['project_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'project_id ist erforderlich.');
            }

            $project = PlannerProject::find($arguments['project_id']);
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('view', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Lesezugriff.');
            }

            $to = !empty($arguments['to']) ? Carbon::parse($arguments['to']) : now();
            if (!empty($arguments['from'])) {
                $from = Carbon::parse($arguments['from']);
            } else {
                $days = max(1, min(365, (int) ($arguments['days'] ?? 30)));
                $from = $to->copy()->subDays($days - 1);
            }

            $snapshots = PlannerProjectSnapshot::where('project_id', $project->id)
                ->whereBetween('taken_on', [$from->toDateString(), $to->toDateString()])
                ->orderBy('taken_on')
                ->get();

            $points = $snapshots->map(fn (PlannerProjectSnapshot $s) => [
                'taken_on' => $s->taken_on?->toDateString(),
                'health_score' => $s->health_score,
                'health_color' => $s->health_color,
                'worst_axis' => $s->worst_axis,
                'axis_scores' => $s->axis_scores,
                'canvas_score' => $s->canvas_score,
                'canvas_color' => $s->canvas_color,
                'confidence_score' => $s->confidence_score,
                'tasks_total' => $s->tasks_total,
                'tasks_open' => $s->tasks_open,
                'tasks_done' => $s->tasks_done,
                'tasks_overdue' => $s->tasks_overdue,
                'tasks_frog' => $s->tasks_frog,
                'story_points_open' => $s->story_points_open,
                'story_points_done' => $s->story_points_done,
                'minutes_logged' => $s->minutes_logged,
                'minutes_planned' => $s->minutes_planned,
                'budget_used_euro' => $s->budget_used_euro ? (float) $s->budget_used_euro : null,
                'delta_tasks_done' => $s->delta_tasks_done,
            ])->all();

            return ToolResult::success([
                'project_id' => $project->id,
                'project_title' => $project->title,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'count' => count($points),
                'points' => $points,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'project', 'snapshot', 'trend', 'timeseries'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
