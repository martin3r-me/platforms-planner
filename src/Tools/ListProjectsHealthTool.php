<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Support\ProjectNavSummary;

/**
 * Portfolio-Sicht des Planners: alle sichtbaren Projekte eines Teams mit Health,
 * Arbeit, Kickoff- und Logbuch-Zustand. Ersetzt projects.metrics.GET und
 * project_snapshots.summary. Aggregation ueber Org-Einheiten liefert die
 * Organisation (planner_* Kennzahlen via EntityLinkProvider).
 */
class ListProjectsHealthTool implements ToolContract, ToolMetadataContract
{
    protected const SORTS = ['attention', 'health', 'overdue', 'risks', 'logbook_quiet', 'complexity', 'name'];

    public function getName(): string
    {
        return 'planner.projects.health.GET';
    }

    public function getDescription(): string
    {
        return 'Portfolio-Sicht: alle sichtbaren Projekte eines Teams mit Health (letzter Snapshot), Arbeit (offen/überfällig/erledigt 7 Tage, Story Points), '
            . 'Kickoff-Status, Logbuch-Ruhe und offenen Risiken — plus Zusammenfassung (Health-Verteilung, Kickoff offen, Logbuch ruhig). '
            . 'Default: laufende Projekte (aktiv/ruhend), sortiert nach Handlungsbedarf. Optional: team_id, lifecycle_state, sort, limit/offset. '
            . 'Aggregate über Org-Einheiten: organization.entity.summary.GET (Kennzahlen planner_*).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'lifecycle_state' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => array_map(fn ($c) => $c->value, ProjectLifecycleState::cases())],
                    'description' => 'Optional: Projekt-Lebenszyklen. Default: ["aktiv","ruhend"].',
                ],
                'sort' => ['type' => 'string', 'enum' => self::SORTS, 'description' => 'Optional: attention (Default: Health schlecht, Überfälliges, Risiken, Kickoff offen zuerst), health, overdue, risks, logbook_quiet, complexity, name.'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Projekte (Default 50, max 200).'],
                'offset' => ['type' => 'integer', 'description' => 'Optional: Offset für Seiten.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (! $context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            $teamId = (int) ($arguments['team_id'] ?? ($context->team?->id ?? 0));
            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Team im Kontext und kein team_id angegeben.');
            }
            if (! $context->user->teams()->where('teams.id', $teamId)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Team.');
            }

            $states = (array) ($arguments['lifecycle_state'] ?? [ProjectLifecycleState::ACTIVE->value, ProjectLifecycleState::DORMANT->value]);
            foreach ($states as $st) {
                if (! ProjectLifecycleState::tryFrom((string) $st)) {
                    return ToolResult::error('VALIDATION_ERROR', "Unbekannter lifecycle_state: {$st}.");
                }
            }
            $sort = in_array($arguments['sort'] ?? null, self::SORTS, true) ? $arguments['sort'] : 'attention';
            $limit = max(1, min(200, (int) ($arguments['limit'] ?? 50)));
            $offset = max(0, (int) ($arguments['offset'] ?? 0));

            // Sichtbarkeit in der Abfrage (nicht nachgelagert) → Zaehlungen und Seiten stimmen
            $projects = PlannerProject::query()
                ->where('team_id', $teamId)
                ->whereIn('lifecycle_state', $states)
                ->visibleTo($context->user)
                ->get(['id', 'name', 'lifecycle_state', 'project_type']);
            $ids = $projects->pluck('id')->all();

            $rows = $this->buildRows($projects, $ids);

            $summary = [
                'total_projects' => count($rows),
                'health_distribution' => collect(['red', 'yellow', 'green', 'gray'])
                    ->mapWithKeys(fn ($c) => [$c => collect($rows)->where('health.color', $c === 'gray' ? null : $c)->count()])->all(),
                'kickoff_open' => collect($rows)->where('canvas.kickoff_done', false)->count(),
                'logbook_quiet' => collect($rows)->where('logbook.quiet', true)->count(),
                'risks_open' => collect($rows)->sum('logbook.risks_open'),
                'tasks_overdue' => collect($rows)->sum('tasks.overdue'),
            ];

            $sorted = collect($rows)->sortBy(match ($sort) {
                'health' => fn ($r) => $r['health']['score'] ?? 999,
                'overdue' => fn ($r) => -$r['tasks']['overdue'],
                'risks' => fn ($r) => -$r['logbook']['risks_open'],
                'logbook_quiet' => fn ($r) => -($r['logbook']['days_since_last_entry'] ?? 9999),
                'complexity' => fn ($r) => -($r['tasks']['story_points_open'] * 10 + $r['tasks']['open']),
                'name' => fn ($r) => mb_strtolower($r['name']),
                default => fn ($r) => -$r['attention_score'],
            })->values();

            return ToolResult::success([
                'team_id' => $teamId,
                'lifecycle_state' => $states,
                'summary' => $summary,
                'projects' => $sorted->slice($offset, $limit)->values()->all(),
                'pagination' => ['offset' => $offset, 'limit' => $limit, 'total' => $sorted->count()],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Portfolio-Sicht: ' . $e->getMessage());
        }
    }

    protected function buildRows($projects, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $active = TaskLifecycleState::ACTIVE->value;
        $done = TaskLifecycleState::COMPLETED->value;
        $spCase = 'CASE story_points ' . collect(TaskStoryPoints::cases())->map(fn ($c) => "WHEN '{$c->value}' THEN {$c->points()}")->implode(' ') . ' ELSE 0 END';

        $work = PlannerTask::withStale()->whereIn('project_id', $ids)
            ->groupBy('project_id')->select('project_id')
            ->selectRaw('SUM(CASE WHEN lifecycle_state = ? THEN 1 ELSE 0 END) as open_cnt', [$active])
            ->selectRaw('SUM(CASE WHEN lifecycle_state = ? AND due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as overdue_cnt', [$active, now()->startOfDay()->toDateTimeString()])
            ->selectRaw('SUM(CASE WHEN lifecycle_state = ? AND lifecycle_state_changed_at >= ? THEN 1 ELSE 0 END) as done_7d', [$done, now()->subDays(7)->toDateTimeString()])
            ->selectRaw("SUM(CASE WHEN lifecycle_state = ? THEN {$spCase} ELSE 0 END) as sp_open", [$active])
            ->get()->keyBy('project_id');

        $snapshots = PlannerProjectSnapshot::whereIn('project_id', $ids)
            ->where('taken_on', '>=', now()->subDays(14)->toDateString())
            ->orderByDesc('taken_on')
            ->get(['project_id', 'taken_on', 'health_score', 'health_color', 'worst_axis', 'confidence_score', 'delta_health_score', 'canvas_completeness_percent', 'minutes_logged'])
            ->unique('project_id')->keyBy('project_id');

        $canvases = PlannerProjectCanvas::whereIn('project_id', $ids)
            ->where('status', '!=', PlannerProjectCanvas::STATUS_DISCARDED)
            ->orderBy('id')
            ->get(['id', 'project_id', 'kickoff_completed_at'])
            ->unique('project_id')->keyBy('project_id');

        $logbook = PlannerProjectLogbookEntry::whereIn('project_id', $ids)
            ->groupBy('project_id')->select('project_id')
            ->selectRaw('MAX(created_at) as last_at')
            ->get()->keyBy('project_id');
        $risks = PlannerProjectLogbookEntry::whereIn('project_id', $ids)->current()
            ->where('type', 'risk')->groupBy('project_id')
            ->selectRaw('project_id, COUNT(*) as n')->pluck('n', 'project_id');

        $rows = [];
        foreach ($projects as $p) {
            $w = $work[$p->id] ?? null;
            $s = $snapshots[$p->id] ?? null;
            $c = $canvases[$p->id] ?? null;
            $lastAt = $logbook[$p->id]->last_at ?? null;
            $days = $lastAt ? (int) \Illuminate\Support\Carbon::parse($lastAt)->startOfDay()->diffInDays(now()->startOfDay()) : null;

            $row = [
                'id' => $p->id,
                'name' => $p->name,
                'lifecycle_state' => $p->lifecycle_state?->value,
                'project_type' => $p->project_type?->value,
                'health' => [
                    'score' => $s?->health_score,
                    'color' => $s?->health_color,
                    'delta' => $s?->delta_health_score,
                    'worst_axis' => $s?->worst_axis,
                    'confidence' => $s?->confidence_score,
                    'taken_on' => $s?->taken_on ? \Illuminate\Support\Carbon::parse($s->taken_on)->toDateString() : null,
                ],
                'tasks' => [
                    'open' => (int) ($w->open_cnt ?? 0),
                    'overdue' => (int) ($w->overdue_cnt ?? 0),
                    'done_7d' => (int) ($w->done_7d ?? 0),
                    'story_points_open' => (int) ($w->sp_open ?? 0),
                    'minutes_logged' => $s?->minutes_logged,
                ],
                'canvas' => [
                    'kickoff_done' => (bool) $c?->kickoff_completed_at,
                    'completeness' => $s?->canvas_completeness_percent,
                ],
                'logbook' => [
                    'days_since_last_entry' => $days,
                    'quiet' => $days === null || $days >= ProjectNavSummary::LOGBOOK_STALE_DAYS,
                    'risks_open' => (int) ($risks[$p->id] ?? 0),
                ],
            ];

            // Handlungsbedarf: schlechte Health, Ueberfaelliges, Risiken, kein Kickoff, ruhiges Logbuch bei Aktivitaet
            $row['attention_score'] = round(
                (100 - (float) ($s?->health_score ?? 70)) * 0.5
                + min($row['tasks']['overdue'], 10) * 3
                + $row['logbook']['risks_open'] * 5
                + ($row['canvas']['kickoff_done'] ? 0 : 10)
                + ($row['logbook']['quiet'] && $row['tasks']['done_7d'] > 0 ? 5 : 0),
                1
            );

            $rows[] = $row;
        }

        return $rows;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'projects', 'portfolio', 'health', 'metrics'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
