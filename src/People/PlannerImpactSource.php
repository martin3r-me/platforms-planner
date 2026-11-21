<?php

namespace Platform\Planner\People;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;
use Platform\People\Support\ImpactSourceBase;

/**
 * Wirkungs-Quelle des Planners für people (Registry-Muster, lose gekoppelt).
 *
 * Liefert nur die WORK-Facette: im Zeitraum abgenommene (erledigte) Aufgaben je Mitarbeiter —
 * und im Zeitraum wieder geöffnete (manual:reopen) als Abzug. Zuordnung über user_in_charge_id
 * (der Verantwortliche ist der, dessen Arbeit es ist — Mensch wie Agent).
 *
 * Registrierung in PlannerServiceProvider::boot() nur, wenn people geladen ist (diese Klasse
 * erbt von einer people-Klasse und darf ohne people nie instanziiert werden).
 */
class PlannerImpactSource extends ImpactSourceBase
{
    /**
     * Nicht-dokumentierende agent_summary-Werte: Rückfrage/Fehler-Notizen bleiben nach einem
     * späteren (menschlichen) Abschluss stehen, die Default-Notiz von createTask sagt nichts
     * über die Arbeit — beides zählt nicht als Dokumentation.
     */
    protected const NON_DOCUMENTING_SUMMARY_PREFIXES = ['RÜCKFRAGE:', 'FAILED:', 'Vom Worker aus einer Nachricht angelegt.'];

    public function key(): string
    {
        return 'planner';
    }

    public function work(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array
    {
        // user_id → employee_id (Subjekte ohne User haben keine Planner-Aufgaben).
        $employeeByUser = [];
        foreach ($subjects as $subject) {
            $userId = $subject['user_id'] ?? null;
            if ($userId) {
                $employeeByUser[(int) $userId] = (int) $subject['employee_id'];
            }
        }
        if (empty($employeeByUser)) {
            return [];
        }

        $completed = TaskLifecycleState::COMPLETED->value;

        // Ein Query für beides: erledigt im Zeitraum ODER im Zeitraum wieder geöffnet.
        // Staleness-Scope raus: abgenommene Arbeit zählt, egal ob jemand die Aufgabe noch ansieht.
        $tasks = PlannerTask::query()
            ->withoutGlobalScope(\Platform\Core\Scopes\StalenessScope::class)
            ->whereIn('team_id', $this->teamIds($teamId))
            ->whereIn('user_in_charge_id', array_keys($employeeByUser))
            ->whereBetween('lifecycle_state_changed_at', [$from, $to])
            ->where(function ($q) use ($completed) {
                $q->where('lifecycle_state', $completed)
                    ->orWhere('lifecycle_state_reason', 'manual:reopen');
            })
            ->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $minutes = $this->bookedMinutes($tasks->pluck('id')->all());
        $taskAlias = $this->morphAlias(PlannerTask::class, 'planner_task');
        $projectAlias = $this->morphAlias(PlannerProject::class, 'project');

        $rows = [];
        foreach ($tasks as $task) {
            $userId = (int) $task->user_in_charge_id;
            $employeeId = $employeeByUser[$userId] ?? null;
            if ($employeeId === null || ! $task->lifecycle_state_changed_at) {
                continue;
            }

            $reopened = $task->lifecycle_state_reason === 'manual:reopen'
                && $task->lifecycle_state !== TaskLifecycleState::COMPLETED;

            $contextRefs = [$taskAlias.':'.$task->id];
            if ($task->project_id) {
                $contextRefs[] = $projectAlias.':'.$task->project_id;
            }

            $rows[] = [
                'employee_id' => $employeeId,
                'ref' => $taskAlias.':'.$task->id,
                'title' => $task->title, // title ist nicht verschlüsselt (nur description/dod)
                'points' => $task->story_points instanceof TaskStoryPoints ? $task->story_points->points() : null,
                'accepted_at' => $task->lifecycle_state_changed_at,
                'documented' => $this->isDocumented($task),
                'reopened' => $reopened,
                'minutes' => $minutes[$task->id][$userId] ?? null,
                'context_refs' => $contextRefs,
                'self_initiated' => (int) $task->user_id === $userId,
            ];
        }

        return $rows;
    }

    /**
     * „Dokumentiert" = nachvollziehbar, WAS getan wurde bzw. dass das Vereinbarte erfüllt ist:
     *  - eine inhaltliche agent_summary (Abschluss-Notiz des Workers; Rückfrage/Fehler-Notizen
     *    und die Default-Notiz zählen nicht), ODER
     *  - eine DoD, die vollständig erfüllt ist (alle Checklisten-Punkte abgehakt) — dieselbe
     *    Auswertung wie die KR-Metrik dod_completion_ratio (PlannerTask::dod_progress, die
     *    JSON- wie Alt-Plaintext-DoD versteht).
     * Eine nur teilweise abgehakte DoD zählt bewusst nicht: sie dokumentiert offene Punkte,
     * nicht die erledigte Arbeit.
     */
    protected function isDocumented(PlannerTask $task): bool
    {
        $summary = trim((string) $task->agent_summary);
        if ($summary !== '') {
            $isMarker = false;
            foreach (self::NON_DOCUMENTING_SUMMARY_PREFIXES as $prefix) {
                if (str_starts_with($summary, $prefix)) {
                    $isMarker = true;
                    break;
                }
            }
            if (! $isMarker) {
                return true;
            }
        }

        return (bool) ($task->dod_progress['isComplete'] ?? false);
    }

    /**
     * Gebuchte Minuten je Task und User (Organization-Zeiteinträge, z. B. über den Agent-
     * Endpoint log-time). Ein Aggregat-Query; ohne organization-Modul leer (→ minutes null).
     * context_type kann FQCN (StoreTimeEntry aus dem Agent-Controller) oder Morph-Alias
     * (morphMany) sein — beides wird gezählt.
     *
     * @param  array<int,int>  $taskIds
     * @return array<int,array<int,int>> task_id → user_id → minutes
     */
    protected function bookedMinutes(array $taskIds): array
    {
        $entryClass = \Platform\Organization\Models\OrganizationTimeEntry::class;
        if (empty($taskIds) || ! class_exists($entryClass)) {
            return [];
        }

        try {
            $rows = $entryClass::query()
                ->whereIn('context_type', array_values(array_unique([
                    PlannerTask::class,
                    $this->morphAlias(PlannerTask::class, 'planner_task'),
                ])))
                ->whereIn('context_id', $taskIds)
                ->selectRaw('context_id, user_id, sum(minutes) as total')
                ->groupBy('context_id', 'user_id')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->context_id][(int) $row->user_id] = (int) $row->total;
        }

        return $out;
    }

    /** Team + alle Kind-Teams (wie die Datawarehouse-Endpunkte). */
    protected function teamIds(int $teamId): array
    {
        $teamClass = \Platform\Core\Models\Team::class;
        $team = class_exists($teamClass) ? $teamClass::find($teamId) : null;

        return $team && method_exists($team, 'getAllTeamIdsIncludingChildren')
            ? $team->getAllTeamIdsIncludingChildren()
            : [$teamId];
    }

    /** Morph-Alias aus der registrierten Relation::morphMap (Fallback, falls nicht gemappt). */
    protected function morphAlias(string $class, string $fallback): string
    {
        $alias = array_search($class, Relation::morphMap(), true);

        return is_string($alias) && $alias !== '' ? $alias : $fallback;
    }
}
