<?php

namespace Platform\Planner\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Services\DimensionLinkService;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

/**
 * ActivityClock — die eine Wahrheit ueber "Wann wurde zuletzt an etwas gearbeitet?".
 *
 * Aggregiert alle objektiven Aktivitaets-Signale zu einem einzigen Zeitstempel
 * pro Projekt bzw. Task. Konsumenten (Lifecycle-Automatik, Hygiene, Cleanup,
 * Health-Snapshot) lesen aus dieser Quelle, damit alle Sichten konsistent sind.
 *
 * Signale (Projekt):
 *   - last_viewed_at des Projekts
 *   - MAX(updated_at) aller aktiven Tasks des Projekts
 *   - MAX(last_viewed_at) aller aktiven Tasks des Projekts
 *   - MAX(created_at) aller Time-Entries an Projekt oder dessen Tasks
 *   - MAX(updated_at) aller Canvas-Bloecke des Projekts
 *
 * Signale (Task):
 *   - last_viewed_at des Tasks
 *   - updated_at des Tasks
 *   - MAX(created_at) aller Time-Entries auf dem Task
 *
 * Beruecksichtigt Morph-Aliase (Time-Entries koennen sowohl mit Alias als auch
 * mit vollem Klassennamen gespeichert sein — je nachdem wer geschrieben hat).
 *
 * Ausschluesse:
 *   - Projekt.updated_at ist NICHT drin — jedes Feld-Update wuerde faelschlich
 *     als Aktivitaet zaehlen (z. B. lifecycle_state_changed_at selbst!).
 *   - Task.updated_at ist drin, weil Task-Edits das eigentliche Arbeitssignal
 *     sind. Falls Task-Updates ebenfalls Rauschen produzieren (z. B. via Batch),
 *     kann das hier spaeter nachgeschaerft werden.
 */
class ActivityClock
{
    /**
     * Aktivitaets-Timestamp fuer ein einzelnes Projekt.
     */
    public function lastActivityForProject(int $projectId): ?Carbon
    {
        $result = $this->lastActivityForProjects([$projectId]);
        return $result[$projectId] ?? null;
    }

    /**
     * Aktivitaets-Timestamps fuer viele Projekte in einem Rutsch.
     * Fuer Konsumenten wie Cleanup, die hunderte Rows gleichzeitig rendern.
     *
     * @param  int[]  $projectIds
     * @return array<int, ?Carbon>  keyed by project_id
     */
    public function lastActivityForProjects(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $projectAlias = DimensionLinkService::resolveContextType(PlannerProject::class);
        $taskAlias = DimensionLinkService::resolveContextType(PlannerTask::class);

        // Signal 1: Projekt-Views
        $projectViews = DB::table('planner_projects')
            ->whereIn('id', $projectIds)
            ->pluck('last_viewed_at', 'id')
            ->all();

        // Signal 2 + 3: Task-Aktivitaet (updated_at, last_viewed_at)
        $taskActivity = DB::table('planner_tasks')
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->selectRaw('project_id,
                MAX(updated_at) as max_updated,
                MAX(last_viewed_at) as max_viewed')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        // Task-IDs pro Projekt fuer Time-Entry-Lookup an Tasks
        $taskProjectMap = DB::table('planner_tasks')
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->pluck('project_id', 'id')
            ->all();
        $taskIds = array_keys($taskProjectMap);

        // Signal 4a: Time-Entries direkt am Projekt
        $projectTimes = DB::table('organization_time_entries')
            ->whereIn('context_type', [$projectAlias, PlannerProject::class])
            ->whereIn('context_id', $projectIds)
            ->selectRaw('context_id, MAX(created_at) as latest')
            ->groupBy('context_id')
            ->pluck('latest', 'context_id')
            ->all();

        // Signal 4b: Time-Entries an Tasks des Projekts → zurueckmappen auf Projekt
        $taskTimes = [];
        if (! empty($taskIds)) {
            $rows = DB::table('organization_time_entries')
                ->whereIn('context_type', [$taskAlias, PlannerTask::class])
                ->whereIn('context_id', $taskIds)
                ->selectRaw('context_id, MAX(created_at) as latest')
                ->groupBy('context_id')
                ->get();
            foreach ($rows as $r) {
                $pid = $taskProjectMap[$r->context_id] ?? null;
                if (! $pid) continue;
                if (! isset($taskTimes[$pid]) || $r->latest > $taskTimes[$pid]) {
                    $taskTimes[$pid] = $r->latest;
                }
            }
        }

        // Signal 5: Canvas-Block-Edits (via canvases → blocks)
        $canvasEdits = DB::table('planner_project_canvas_blocks as b')
            ->join('planner_project_canvases as c', 'c.id', '=', 'b.canvas_id')
            ->whereIn('c.project_id', $projectIds)
            ->whereNull('b.deleted_at')
            ->whereNull('c.deleted_at')
            ->selectRaw('c.project_id, MAX(b.updated_at) as latest')
            ->groupBy('c.project_id')
            ->pluck('latest', 'c.project_id')
            ->all();

        // Aggregieren
        $result = [];
        foreach ($projectIds as $pid) {
            $candidates = array_filter([
                $projectViews[$pid] ?? null,
                $taskActivity->get($pid)?->max_updated ?? null,
                $taskActivity->get($pid)?->max_viewed ?? null,
                $projectTimes[$pid] ?? null,
                $taskTimes[$pid] ?? null,
                $canvasEdits[$pid] ?? null,
            ]);
            $result[$pid] = empty($candidates)
                ? null
                : Carbon::parse(max($candidates));
        }
        return $result;
    }

    /**
     * Aktivitaets-Timestamp fuer einen einzelnen Task.
     */
    public function lastActivityForTask(int $taskId): ?Carbon
    {
        $result = $this->lastActivityForTasks([$taskId]);
        return $result[$taskId] ?? null;
    }

    /**
     * Aktivitaets-Timestamps fuer viele Tasks.
     *
     * @param  int[]  $taskIds
     * @return array<int, ?Carbon>  keyed by task_id
     */
    public function lastActivityForTasks(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $taskAlias = DimensionLinkService::resolveContextType(PlannerTask::class);

        // Signal 1+2: Task-eigene Timestamps
        $taskSelf = DB::table('planner_tasks')
            ->whereIn('id', $taskIds)
            ->selectRaw('id, last_viewed_at, updated_at')
            ->get()
            ->keyBy('id');

        // Signal 3: Time-Entries auf Task
        $taskTimes = DB::table('organization_time_entries')
            ->whereIn('context_type', [$taskAlias, PlannerTask::class])
            ->whereIn('context_id', $taskIds)
            ->selectRaw('context_id, MAX(created_at) as latest')
            ->groupBy('context_id')
            ->pluck('latest', 'context_id')
            ->all();

        $result = [];
        foreach ($taskIds as $tid) {
            $self = $taskSelf->get($tid);
            $candidates = array_filter([
                $self?->last_viewed_at ?? null,
                $self?->updated_at ?? null,
                $taskTimes[$tid] ?? null,
            ]);
            $result[$tid] = empty($candidates)
                ? null
                : Carbon::parse(max($candidates));
        }
        return $result;
    }

    /**
     * Convenience: Tage seit letzter Aktivitaet.
     *
     * @return int|null  null wenn keine Aktivitaet je stattfand
     */
    public function forgottenDaysForProject(int $projectId): ?int
    {
        $at = $this->lastActivityForProject($projectId);
        return $at ? (int) now()->diffInDays($at, absolute: true) : null;
    }

    public function forgottenDaysForTask(int $taskId): ?int
    {
        $at = $this->lastActivityForTask($taskId);
        return $at ? (int) now()->diffInDays($at, absolute: true) : null;
    }
}
