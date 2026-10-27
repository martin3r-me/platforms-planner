<?php

namespace Platform\Planner\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasContextParent;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Organization\Contracts\HasPersonMetrics;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

class PlannerEntityLinkProvider implements EntityLinkProvider, HasContextParent, HasMetricDefinitions, HasPersonMetrics
{
    public function morphAliases(): array
    {
        return ['project', 'planner_task'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'project' => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'route' => 'planner.projects.show'],
            'planner_task' => ['label' => 'Aufgaben', 'singular' => 'Aufgabe', 'icon' => 'clipboard-document-check', 'route' => null],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        match ($morphAlias) {
            'project' => $query
                ->with([
                    'user:id,name',
                    'tasks' => fn($q) => $q
                        ->with('userInCharge:id,name')
                        ->selectRaw("planner_tasks.*, ({$this->timeMinutesSubquery('planner_tasks', PlannerTask::class)}) as logged_minutes_sum")
                        ->orderBy('order'),
                ])
                ->withCount([
                    'tasks',
                    'tasks as done_tasks_count' => fn($q) => $q->where('lifecycle_state', TaskLifecycleState::COMPLETED->value),
                ]),
            'planner_task' => $query
                ->with('userInCharge:id,name')
                ->selectRaw("planner_tasks.*, ({$this->timeMinutesSubquery('planner_tasks', $fqcn)}) as logged_minutes_sum"),
            default => null,
        };
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return match ($morphAlias) {
            'project' => $this->extractProjectMetadata($model),
            'planner_task' => [
                'is_done' => $model->lifecycle_state === TaskLifecycleState::COMPLETED,
                'lifecycle_state' => $model->lifecycle_state?->value,
                'is_frog' => (bool) ($model->is_frog ?? false),
                'responsible' => $model->userInCharge?->name,
                'priority' => $model->priority?->value ?? null,
                'story_points' => $model->story_points?->value ?? null,
                'logged_minutes' => (int) ($model->logged_minutes_sum ?? 0),
                'due_date' => $model->due_date?->format('d.m.Y'),
            ],
            default => [],
        };
    }

    public function metadataDisplayRules(): array
    {
        return [
            'project' => [
                ['field' => 'responsible', 'format' => 'text'],
                ['field' => 'task_count', 'format' => 'count_ratio', 'done_field' => 'done_task_count', 'suffix' => 'Tasks'],
                ['field' => 'logged_minutes', 'format' => 'time'],
                ['field' => 'done', 'format' => 'boolean_done'],
                ['field' => 'task_items', 'format' => 'expandable_children', 'child_type' => 'planner_task', 'name_field' => 'name', 'done_field' => 'is_done'],
            ],
            'planner_task' => [
                ['field' => 'responsible', 'format' => 'text'],
                ['field' => 'priority', 'format' => 'text'],
                ['field' => 'story_points', 'format' => 'text', 'suffix' => 'SP'],
                ['field' => 'is_frog', 'format' => 'boolean_frog'],
                ['field' => 'logged_minutes', 'format' => 'time'],
                ['field' => 'due_date', 'format' => 'text'],
                ['field' => 'is_done', 'format' => 'boolean_done'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [
            'project' => [PlannerProject::class, ['tasks', 'projectSlots.tasks']],
            'planner_task' => [PlannerTask::class, []],
        ];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        if ($morphAlias !== 'project' || empty($linkableIds)) {
            return [];
        }

        $taskIds = PlannerTask::whereIn('project_id', $linkableIds)->pluck('id')->all();

        if (empty($taskIds)) {
            return [];
        }

        return [PlannerTask::class => $taskIds];
    }

    /**
     * Kennzahlen je Org-Einheit aus den dort verlinkten Projekten — die Projekt-Achsen
     * (Arbeit, Zustand/Canvas, Verlauf/Logbuch, Health) fuer Radar, Roll-up und Signale.
     *
     * Bestandskennzahlen (offen, ueberfaellig, Health, Risiken, Kickoff) zaehlen nur
     * laufende Projekte (aktiv/ruhend). Kumulierte Zaehler (erledigt) alle Projekte,
     * damit sie monoton bleiben. Verworfene Aufgaben zaehlen nirgends; lange nicht
     * angesehene (Staleness) zaehlen mit.
     */
    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'project') {
            return [];
        }

        $allIds = array_values(array_unique(array_merge(...array_values($linksByEntity ?: [[]]))));
        if (empty($allIds)) {
            return [];
        }

        $running = [ProjectLifecycleState::ACTIVE->value, ProjectLifecycleState::DORMANT->value];
        $projects = PlannerProject::whereIn('id', $allIds)->get(['id', 'lifecycle_state'])->keyBy('id');

        // ── Arbeit: eine aggregierte Abfrage ueber alle Projekte ──
        $active = TaskLifecycleState::ACTIVE->value;
        $done = TaskLifecycleState::COMPLETED->value;
        $spCase = 'CASE story_points ' . collect(\Platform\Planner\Enums\TaskStoryPoints::cases())
            ->map(fn ($c) => "WHEN '{$c->value}' THEN {$c->points()}")->implode(' ') . ' ELSE 0 END';
        $today = now()->startOfDay()->toDateTimeString();
        $weekAgo = now()->subDays(7)->toDateTimeString();

        $work = PlannerTask::withStale()
            ->whereIn('project_id', $allIds)
            ->where('lifecycle_state', '!=', TaskLifecycleState::DISCARDED->value)
            ->groupBy('project_id')
            ->select('project_id')
            ->selectRaw("SUM(CASE WHEN lifecycle_state = ? THEN 1 ELSE 0 END) as open_cnt", [$active])
            ->selectRaw("SUM(CASE WHEN lifecycle_state = ? THEN 1 ELSE 0 END) as done_cnt", [$done])
            ->selectRaw("SUM(CASE WHEN lifecycle_state = ? AND due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as overdue_cnt", [$active, $today])
            ->selectRaw("SUM(CASE WHEN lifecycle_state = ? AND lifecycle_state_changed_at >= ? THEN 1 ELSE 0 END) as done_7d", [$done, $weekAgo])
            ->selectRaw("SUM({$spCase}) as sp_total")
            ->selectRaw("SUM(CASE WHEN lifecycle_state = ? THEN {$spCase} ELSE 0 END) as sp_done", [$done])
            ->get()
            ->keyBy('project_id');

        // ── Health + Canvas-Vollstaendigkeit: juengster Projekt-Snapshot (naechtlich) ──
        $latestSnapshot = \Platform\Planner\Models\PlannerProjectSnapshot::whereIn('project_id', $allIds)
            ->where('taken_on', '>=', now()->subDays(14)->toDateString())
            ->orderByDesc('taken_on')
            ->get(['project_id', 'health_score', 'canvas_completeness_percent', 'taken_on'])
            ->unique('project_id')
            ->keyBy('project_id');

        // ── Zustand: Kickoff offen (Canvas ohne abgeschlossenen Kickoff) ──
        $kickoffOpen = \Platform\Planner\Models\PlannerProjectCanvas::whereIn('project_id', $allIds)
            ->where('status', '!=', \Platform\Planner\Models\PlannerProjectCanvas::STATUS_DISCARDED)
            ->whereNull('kickoff_completed_at')
            ->distinct()
            ->pluck('project_id')
            ->flip();

        // ── Verlauf: offene Risiken, Entscheidungen/Meilensteine der letzten 30 Tage ──
        $logbook = \Platform\Planner\Models\PlannerProjectLogbookEntry::whereIn('project_id', $allIds)
            ->current()
            ->groupBy('project_id')
            ->select('project_id')
            ->selectRaw("SUM(CASE WHEN type = 'risk' THEN 1 ELSE 0 END) as risks_open")
            ->selectRaw("SUM(CASE WHEN type = 'decision' AND occurred_on >= ? THEN 1 ELSE 0 END) as decisions_30d", [now()->subDays(30)->toDateString()])
            ->selectRaw("SUM(CASE WHEN type = 'milestone' AND occurred_on >= ? THEN 1 ELSE 0 END) as milestones_30d", [now()->subDays(30)->toDateString()])
            ->get()
            ->keyBy('project_id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $m = [
                'items_total' => 0, 'items_done' => 0, 'story_points_total' => 0, 'story_points_done' => 0,
                'planner_items_open' => 0, 'planner_items_overdue' => 0, 'planner_items_done_7d' => 0,
                'planner_projects_active' => 0, 'planner_kickoff_open' => 0,
                'planner_risks_open' => 0, 'planner_decisions_30d' => 0, 'planner_milestones_30d' => 0,
            ];
            $health = [];
            $completeness = [];

            foreach (array_unique($ids) as $id) {
                $p = $projects[$id] ?? null;
                if (! $p) {
                    continue;
                }
                $isRunning = in_array($p->lifecycle_state?->value, $running, true);
                $w = $work[$id] ?? null;

                // kumuliert / Bestand ueber alle Projekte
                $m['items_done'] += (int) ($w->done_cnt ?? 0);
                $m['story_points_total'] += (int) ($w->sp_total ?? 0);
                $m['story_points_done'] += (int) ($w->sp_done ?? 0);
                $m['planner_items_done_7d'] += (int) ($w->done_7d ?? 0);
                $m['items_total'] += (int) ($w->open_cnt ?? 0) + (int) ($w->done_cnt ?? 0);

                $lb = $logbook[$id] ?? null;
                $m['planner_decisions_30d'] += (int) ($lb->decisions_30d ?? 0);
                $m['planner_milestones_30d'] += (int) ($lb->milestones_30d ?? 0);

                if (! $isRunning) {
                    continue;
                }
                // nur laufende Projekte
                $m['planner_projects_active']++;
                $m['planner_items_open'] += (int) ($w->open_cnt ?? 0);
                $m['planner_items_overdue'] += (int) ($w->overdue_cnt ?? 0);
                $m['planner_risks_open'] += (int) ($lb->risks_open ?? 0);
                if (isset($kickoffOpen[$id])) {
                    $m['planner_kickoff_open']++;
                }
                if (($s = $latestSnapshot[$id] ?? null) && $s->health_score !== null) {
                    $health[] = (float) $s->health_score;
                }
                if ($s && $s->canvas_completeness_percent !== null) {
                    $completeness[] = (float) $s->canvas_completeness_percent;
                }
            }

            $m['planner_health_avg'] = $health ? round(array_sum($health) / count($health), 1) : null;
            $m['planner_canvas_completeness_avg'] = $completeness ? round(array_sum($completeness) / count($completeness), 1) : null;

            $result[$entityId] = array_filter($m, fn ($v) => $v !== null);
        }

        return $result;
    }

    protected function timeMinutesSubquery(string $table, string $fqcn): string
    {
        $quoted = DB::getPdo()->quote($fqcn);
        return "SELECT COALESCE(SUM(minutes), 0) FROM organization_time_entries WHERE context_id = {$table}.id AND context_type = {$quoted} AND deleted_at IS NULL";
    }

    protected function extractProjectMetadata(mixed $project): array
    {
        $tasks = $project->tasks ?? collect();
        $loggedMinutes = $tasks->sum(fn($t) => (int) ($t->logged_minutes_sum ?? 0));

        $taskItems = [];
        foreach ($tasks as $task) {
            $taskMinutes = (int) ($task->logged_minutes_sum ?? 0);
            $taskItems[] = [
                'id' => $task->id,
                'name' => $task->title ?? '—',
                'is_done' => $task->lifecycle_state === TaskLifecycleState::COMPLETED,
                'lifecycle_state' => $task->lifecycle_state?->value,
                'is_frog' => (bool) ($task->is_frog ?? false),
                'responsible' => $task->userInCharge?->name,
                'priority' => $task->priority?->value ?? null,
                'story_points' => $task->story_points?->value ?? null,
                'logged_minutes' => $taskMinutes,
                'due_date' => $task->due_date?->format('d.m.Y'),
            ];
        }

        return [
            'done' => $project->lifecycle_state === ProjectLifecycleState::COMPLETED,
            'lifecycle_state' => $project->lifecycle_state?->value,
            'responsible' => $project->user?->name,
            'task_count' => $project->tasks_count ?? 0,
            'done_task_count' => $project->done_tasks_count ?? 0,
            'logged_minutes' => $loggedMinutes,
            'budget_amount' => $project->budget_amount,
            'has_tasks' => count($taskItems) > 0,
            'task_items' => $taskItems,
        ];
    }

    public function metricDefinitions(): array
    {
        return [
            // Generische Arbeits-Kennzahlen (gemeinsame Keys mit anderen Modulen, z.B. Dev)
            'items_total'        => ['label' => 'Items (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'is_dimension_primary' => true],
            'items_done'         => ['label' => 'Items (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'count', 'pair' => 'items_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start', 'is_dimension_primary' => true],
            'story_points_total' => ['label' => 'Story Points (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'points', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'story_points_done'  => ['label' => 'Story Points (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'points', 'pair' => 'story_points_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],

            // Planner — Arbeit (laufende Projekte)
            'planner_items_open'    => ['label' => 'Aufgaben offen', 'group' => 'planner', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'subset_of' => 'items_total'],
            'planner_items_overdue' => ['label' => 'Aufgaben überfällig', 'group' => 'planner', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'energy', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'planner_items_open'],
            'planner_items_done_7d' => ['label' => 'Aufgaben erledigt (7 Tage)', 'group' => 'planner', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d'],

            // Planner — Projekte & Zustand (Canvas)
            'planner_projects_active'         => ['label' => 'Laufende Projekte', 'group' => 'planner', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'planner_kickoff_open'            => ['label' => 'Projekte ohne Kickoff', 'group' => 'planner', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'pair' => 'planner_projects_active'],
            'planner_canvas_completeness_avg' => ['label' => 'Canvas-Vollständigkeit (Ø)', 'group' => 'planner', 'direction' => 'up', 'unit' => 'percentage', 'dimension' => 'org_capital', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'avg', 'basis' => 'modulator_factor'],
            'planner_health_avg'              => ['label' => 'Projekt-Health (Ø)', 'group' => 'planner', 'direction' => 'up', 'unit' => 'score', 'dimension' => 'quality', 'type' => 'modulator', 'aggregation_mode' => 'own', 'roll_up_function' => 'avg', 'basis' => 'modulator_factor'],

            // Planner — Verlauf (Logbuch)
            'planner_risks_open'     => ['label' => 'Offene Projekt-Risiken', 'group' => 'planner', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'planner_decisions_30d'  => ['label' => 'Entscheidungen (30 Tage)', 'group' => 'planner', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_30d'],
            'planner_milestones_30d' => ['label' => 'Meilensteine (30 Tage)', 'group' => 'planner', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_30d'],
        ];
    }

    public function personMetrics(array $userIds, int $teamId): array
    {
        if (empty($userIds)) {
            return [];
        }

        // story_points ist ein Enum-String (xs..xxl) → in Punkte uebersetzen
        $spCase = 'CASE story_points ' . collect(\Platform\Planner\Enums\TaskStoryPoints::cases())
            ->map(fn ($c) => "WHEN '{$c->value}' THEN {$c->points()}")->implode(' ') . ' ELSE 0 END';

        $rows = PlannerTask::withStale()
            ->whereIn('user_in_charge_id', $userIds)
            ->where('team_id', $teamId)
            ->select(
                'user_in_charge_id',
                DB::raw("SUM(CASE WHEN lifecycle_state = 'aktiv' THEN 1 ELSE 0 END) as active_items"),
                DB::raw("SUM(CASE WHEN lifecycle_state = 'erledigt' THEN 1 ELSE 0 END) as completed_items"),
                DB::raw("SUM(CASE WHEN lifecycle_state = 'aktiv' THEN {$spCase} ELSE 0 END) as story_points_total"),
                DB::raw("SUM(CASE WHEN lifecycle_state = 'erledigt' THEN {$spCase} ELSE 0 END) as story_points_done"),
            )
            ->groupBy('user_in_charge_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->user_in_charge_id] = [
                'active_items' => (int) $row->active_items,
                'completed_items' => (int) $row->completed_items,
                'story_points_total' => (int) $row->story_points_total,
                'story_points_done' => (int) $row->story_points_done,
            ];
        }

        return $result;
    }

    public function personMetricDefinitions(): array
    {
        return [
            'active_items'       => ['label' => 'Aktive Items', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'count'],
            'completed_items'    => ['label' => 'Erledigte Items', 'group' => 'persons', 'direction' => 'up', 'unit' => 'count'],
            'story_points_total' => ['label' => 'Story Points gesamt', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'points'],
            'story_points_done'  => ['label' => 'Story Points erledigt', 'group' => 'persons', 'direction' => 'up', 'unit' => 'points'],
        ];
    }

    /**
     * Task ohne eigene Verortung erbt vom Projekt.
     */
    public function contextParent(string $morphAlias, Model $object): ?array
    {
        return $morphAlias === 'planner_task' && $object->project_id
            ? ['project', (int) $object->project_id]
            : null;
    }

    /**
     * Beteiligte für ein Gespräch im Kontext (Parlan) — organization fragt per method_exists
     * (Vertrag HasContextParticipants). Projekt: Ersteller + Zuständige offener Aufgaben;
     * Aufgabe: Ersteller + Zuständiger. Sichtbarkeit prüft organization.
     *
     * @return list<int>
     */
    public function contextParticipants(string $morphAlias, Model $object): array
    {
        if ($morphAlias === 'project') {
            $ids = PlannerTask::query()->where('project_id', $object->id)
                ->whereNotNull('user_in_charge_id')
                ->where(fn ($q) => $q->whereNull('lifecycle_state')->orWhere('lifecycle_state', '!=', TaskLifecycleState::COMPLETED->value))
                ->distinct()->limit(25)->pluck('user_in_charge_id')->all();

            return array_values(array_unique(array_filter(array_merge([(int) $object->user_id], array_map('intval', $ids)))));
        }
        if ($morphAlias === 'planner_task') {
            return array_values(array_unique(array_filter([(int) $object->user_id, (int) $object->user_in_charge_id])));
        }

        return [];
    }

    /**
     * Ist das Objekt erledigt? Dann ruht sein Gespräch in Parlan (rooms/0.3) — es kommt zurück,
     * sobald jemand schreibt oder das Objekt wieder aufgeht. null = kein Planner-Objekt.
     */
    public function contextDone(string $morphAlias, Model $object): ?bool
    {
        if ($morphAlias === 'project') {
            return in_array($object->lifecycle_state, [
                ProjectLifecycleState::COMPLETED, ProjectLifecycleState::DISCARDED, ProjectLifecycleState::DORMANT,
            ], true);
        }
        if ($morphAlias === 'planner_task') {
            return in_array($object->lifecycle_state, [TaskLifecycleState::COMPLETED, TaskLifecycleState::DISCARDED], true);
        }

        return null;
    }
}
