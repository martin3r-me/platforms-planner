<?php

namespace Platform\Planner\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\TaskLifecycleState;
use Illuminate\Support\Facades\Gate;

/**
 * Aggregations-Tool: Projekt-Metriken (Komplexität) für ein Team
 *
 * Ziel: LLM soll "höchste Komplexität" ohne 30+ Toolcalls bestimmen können.
 * Default: Backlog wird ausgeschlossen (project_slot_id = null), da Backlog laut User oft nicht relevant ist.
 */
class ListProjectMetricsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.projects.metrics.GET';
    }

    public function getDescription(): string
    {
        return 'GET /planner/projects/metrics?team_id=... - Liefert aggregierte Projekt-Metriken (Tasks-Anzahl, Story-Points, geplante Minuten) pro Projekt und ein Ranking nach Komplexität. Ideal, um "komplexestes Projekt" zu bestimmen ohne viele Einzel-Toolcalls. Default: Backlog ausgeschlossen (project_slot_id = null).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Wenn nicht angegeben, wird das aktuelle Team aus dem Kontext verwendet.',
                ],
                'include_done_tasks' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Erledigte Tasks in die Metriken einbeziehen. Standard: true.',
                ],
                'include_backlog' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Backlog-Tasks einbeziehen (project_slot_id = null). Standard: false.',
                ],
                'tasks_weight' => [
                    'type' => 'number',
                    'description' => 'Optional: Gewicht für Anzahl Tasks bei der Ranking-Score-Berechnung. Standard: 1.0.',
                ],
                'points_weight' => [
                    'type' => 'number',
                    'description' => 'Optional: Gewicht für Story-Points bei der Ranking-Score-Berechnung. Standard: 1.0.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Anzahl Projekte im Ergebnis (Top-N). Standard: 50.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if ($teamId === 0 || $teamId === '0') {
                $teamId = null;
            }
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            // Zugriff: User muss im Team sein
            $userHasAccess = $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $includeDone = (bool)($arguments['include_done_tasks'] ?? true);
            $includeBacklog = (bool)($arguments['include_backlog'] ?? false);
            $tasksWeight = (float)($arguments['tasks_weight'] ?? 1.0);
            $pointsWeight = (float)($arguments['points_weight'] ?? 1.0);
            $limit = (int)($arguments['limit'] ?? 50);
            if ($limit <= 0) { $limit = 50; }
            $limit = min($limit, 200);

            $projects = PlannerProject::withStale()
                ->where('team_id', $teamId)
                ->get(['id', 'name', 'done', 'user_id', 'created_at']);

            // Policy: nur Projekte, die der User sehen darf (wie UI)
            $projects = $projects->filter(fn($p) => Gate::forUser($context->user)->allows('view', $p))->values();

            if ($projects->isEmpty()) {
                return ToolResult::success([
                    'team_id' => (int)$teamId,
                    'projects' => [],
                    'count' => 0,
                    'message' => 'Keine Projekte gefunden.',
                ]);
            }

            $projectIds = $projects->pluck('id')->values()->all();

            // Story points mapping (enum values are strings)
            $pointsCase = "CASE story_points
                WHEN 'xs' THEN 1
                WHEN 's' THEN 2
                WHEN 'm' THEN 3
                WHEN 'l' THEN 5
                WHEN 'xl' THEN 8
                WHEN 'xxl' THEN 13
                ELSE 0
            END";

            $tasksQuery = PlannerTask::withStale()
                ->whereIn('project_id', $projectIds)
                ->whereNotNull('project_id');

            if (!$includeBacklog) {
                $tasksQuery->whereNotNull('project_slot_id');
            }

            if (!$includeDone) {
                $tasksQuery->where('lifecycle_state', TaskLifecycleState::ACTIVE->value);
            }

            // Task-IDs für planned_minutes Batch-Query sammeln
            $taskIdsForPlanned = (clone $tasksQuery)->pluck('id')->all();

            $rows = $tasksQuery
                ->select([
                    'project_id',
                    DB::raw('COUNT(*) as tasks_total'),
                    DB::raw("SUM(CASE WHEN lifecycle_state = 'aktiv' THEN 1 ELSE 0 END) as tasks_open"),
                    DB::raw("SUM(CASE WHEN lifecycle_state = 'erledigt' THEN 1 ELSE 0 END) as tasks_done"),
                    DB::raw("SUM({$pointsCase}) as points_total"),
                    DB::raw("SUM(CASE WHEN lifecycle_state = 'aktiv' THEN {$pointsCase} ELSE 0 END) as points_open"),
                    DB::raw("SUM(CASE WHEN lifecycle_state = 'erledigt' THEN {$pointsCase} ELSE 0 END) as points_done"),
                ])
                ->groupBy('project_id')
                ->get();

            // Planned minutes aus zentralem System aggregieren
            $plannedByTask = [];
            if (!empty($taskIdsForPlanned)) {
                $plannedByTask = OrganizationTimePlanned::where('context_type', PlannerTask::class)
                    ->whereIn('context_id', $taskIdsForPlanned)
                    ->where('is_active', true)
                    ->groupBy('context_id')
                    ->selectRaw('context_id, SUM(planned_minutes) as total')
                    ->pluck('total', 'context_id')
                    ->toArray();
            }

            // Task→Project Mapping für planned_minutes Zuordnung
            $taskProjectMap = PlannerTask::withStale()
                ->whereIn('id', array_keys($plannedByTask))
                ->pluck('project_id', 'id')
                ->toArray();

            // Planned minutes pro Projekt aggregieren
            $plannedByProject = [];
            foreach ($plannedByTask as $taskId => $minutes) {
                $pid = $taskProjectMap[$taskId] ?? null;
                if ($pid) {
                    $plannedByProject[$pid] = ($plannedByProject[$pid] ?? 0) + (int) $minutes;
                }
            }

            $byProjectId = [];
            foreach ($rows as $r) {
                $pid = (int)($r->project_id ?? 0);
                $byProjectId[$pid] = [
                    'tasks_total' => (int)($r->tasks_total ?? 0),
                    'tasks_open' => (int)($r->tasks_open ?? 0),
                    'tasks_done' => (int)($r->tasks_done ?? 0),
                    'points_total' => (int)($r->points_total ?? 0),
                    'points_open' => (int)($r->points_open ?? 0),
                    'points_done' => (int)($r->points_done ?? 0),
                    'planned_minutes_total' => $plannedByProject[$pid] ?? 0,
                ];
            }

            $out = [];
            foreach ($projects as $p) {
                $pid = (int)$p->id;
                $m = $byProjectId[$pid] ?? [
                    'tasks_total' => 0,
                    'tasks_open' => 0,
                    'tasks_done' => 0,
                    'points_total' => 0,
                    'points_open' => 0,
                    'points_done' => 0,
                    'planned_minutes_total' => 0,
                ];

                $score = ($m['points_total'] * $pointsWeight) + ($m['tasks_total'] * $tasksWeight);

                $out[] = [
                    'project_id' => $pid,
                    'project_name' => $p->name,
                    'done' => (bool)$p->done,
                    'created_at' => $p->created_at?->toIso8601String(),
                    'metrics' => $m,
                    'score' => $score,
                ];
            }

            usort($out, fn($a, $b) => ($b['score'] <=> $a['score']));
            $out = array_slice($out, 0, $limit);

            $top = $out[0] ?? null;

            return ToolResult::success([
                'team_id' => (int)$teamId,
                'include_done_tasks' => $includeDone,
                'include_backlog' => $includeBacklog,
                'weights' => [
                    'tasks_weight' => $tasksWeight,
                    'points_weight' => $pointsWeight,
                ],
                'projects' => $out,
                'count' => count($out),
                'top_project' => $top,
                'note' => $includeBacklog
                    ? 'Backlog ist EINBEZOGEN (project_slot_id kann null sein).'
                    : 'Backlog ist AUSGESCHLOSSEN (nur Tasks mit project_slot_id != null).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Projekt-Metriken: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'projects', 'metrics', 'aggregation', 'overview'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}


