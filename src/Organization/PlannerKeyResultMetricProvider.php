<?php

namespace Platform\Planner\Organization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Contracts\KeyResultMetricProvider;
use Platform\Core\KeyResult\MetricRequest;
use Platform\Core\KeyResult\MetricValue;
use Platform\Planner\Models\PlannerTask;

/**
 * KR-Metriken aus dem Planner. Scopes über eigene metric_keys
 * (planner.{scope}.{concept}) statt Selector-Optionen — Discovery bleibt eindeutig.
 *
 * Provider ist dumm: liefert Rohwerte. Zielerreichung macht die OKR-Engine.
 */
class PlannerKeyResultMetricProvider implements KeyResultMetricProvider
{
    public function metricDefinitions(): array
    {
        $projectSel = [['field' => 'project_id', 'type' => 'integer', 'required' => true, 'label' => 'Projekt', 'lookup_tool' => 'planner.projects.GET']];
        $userSel    = [['field' => 'user_id', 'type' => 'integer', 'required' => true, 'label' => 'Person', 'lookup_tool' => 'core.users.GET']];
        $slotSel    = [['field' => 'project_slot_id', 'type' => 'integer', 'required' => true, 'label' => 'Slot', 'lookup_tool' => 'planner.project_slots.GET']];

        $roles = ['score', 'gate', 'cap', 'info'];

        return [
            ['metric_key' => 'planner.project.tasks_done_ratio', 'module' => 'planner',
             'label' => 'Aufgaben erledigt (Quote, Projekt)', 'value_type' => 'ratio', 'unit' => '%',
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'instance',
             'selector_schema' => $projectSel, 'supports_window' => false],

            ['metric_key' => 'planner.project.dod_completion_ratio', 'module' => 'planner',
             'label' => 'DoD-Erfüllung (checked/total, Projekt)', 'value_type' => 'ratio', 'unit' => '%',
             'default_polarity' => 'up', 'supported_roles' => ['gate', 'score', 'cap', 'info'], 'binding' => 'instance',
             'selector_schema' => $projectSel, 'supports_window' => false],

            ['metric_key' => 'planner.project.tasks_overdue_count', 'module' => 'planner',
             'label' => 'Überfällige Aufgaben (Projekt)', 'value_type' => 'count', 'unit' => null,
             'default_polarity' => 'down', 'supported_roles' => ['cap', 'gate', 'info'], 'binding' => 'instance',
             'selector_schema' => $projectSel, 'supports_window' => false],

            ['metric_key' => 'planner.user.tasks_done_ratio', 'module' => 'planner',
             'label' => 'Aufgaben erledigt (Quote, Person/in charge)', 'value_type' => 'ratio', 'unit' => '%',
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'instance',
             'selector_schema' => $userSel, 'supports_window' => false],

            ['metric_key' => 'planner.delegated.tasks_done_ratio', 'module' => 'planner',
             'label' => 'Delegierte Aufgaben erledigt (Quote)', 'value_type' => 'ratio', 'unit' => '%',
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'instance',
             'selector_schema' => $userSel, 'supports_window' => false],

            ['metric_key' => 'planner.slot.tasks_done_ratio', 'module' => 'planner',
             'label' => 'Aufgaben erledigt (Quote, Slot)', 'value_type' => 'ratio', 'unit' => '%',
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'instance',
             'selector_schema' => $slotSel, 'supports_window' => false],
        ];
    }

    public function resolveBatch(string $metricKey, array $requests): array
    {
        return match ($metricKey) {
            'planner.project.tasks_done_ratio'     => $this->doneRatio($requests, 'project_id', 'project_id'),
            'planner.slot.tasks_done_ratio'        => $this->doneRatio($requests, 'project_slot_id', 'project_slot_id'),
            'planner.user.tasks_done_ratio'        => $this->doneRatio($requests, 'user_id', 'user_in_charge_id'),
            'planner.delegated.tasks_done_ratio'   => $this->delegatedDoneRatio($requests),
            'planner.project.dod_completion_ratio' => $this->dodRatio($requests),
            'planner.project.tasks_overdue_count'  => $this->overdueCount($requests),
            default                                => array_map(fn () => MetricValue::unavailable('unknown metric'), $requests),
        };
    }

    /** Erledigt-Quote gruppiert nach einer Spalte; selectorField → queryColumn. */
    protected function doneRatio(array $requests, string $selectorField, string $queryColumn): array
    {
        $ids = $this->collectSelectorIds($requests, $selectorField);
        $rows = empty($ids) ? collect() : $this->scoped($requests)
            ->whereIn($queryColumn, $ids)
            ->selectRaw("$queryColumn as k, count(*) as total, sum(case when is_done = 1 then 1 else 0 end) as done")
            ->groupBy($queryColumn)
            ->get()->keyBy('k');

        $out = [];
        foreach ($requests as $key => $req) {
            $id = $req->selector[$selectorField] ?? null;
            $row = $id !== null ? ($rows[$id] ?? null) : null;
            $total = (int) ($row->total ?? 0);
            if ($total === 0) {
                $out[$key] = MetricValue::unavailable('no tasks');
                continue;
            }
            $done = (int) $row->done;
            $out[$key] = MetricValue::of($done / $total, ['done' => $done, 'total' => $total], "$done von $total erledigt");
        }

        return $out;
    }

    /** Delegiert: Owner = selektierte Person, in charge = jemand anderes. */
    protected function delegatedDoneRatio(array $requests): array
    {
        $ids = $this->collectSelectorIds($requests, 'user_id');
        $rows = empty($ids) ? collect() : $this->scoped($requests)
            ->whereIn('user_id', $ids)
            ->whereNotNull('user_in_charge_id')
            ->whereColumn('user_in_charge_id', '!=', 'user_id')
            ->selectRaw('user_id as k, count(*) as total, sum(case when is_done = 1 then 1 else 0 end) as done')
            ->groupBy('user_id')
            ->get()->keyBy('k');

        $out = [];
        foreach ($requests as $key => $req) {
            $id = $req->selector['user_id'] ?? null;
            $row = $id !== null ? ($rows[$id] ?? null) : null;
            $total = (int) ($row->total ?? 0);
            if ($total === 0) {
                $out[$key] = MetricValue::unavailable('keine delegierten Aufgaben');
                continue;
            }
            $done = (int) $row->done;
            $out[$key] = MetricValue::of($done / $total, ['done' => $done, 'total' => $total], "$done von $total delegiert erledigt");
        }

        return $out;
    }

    /** DoD-Erfüllung (Σchecked/Σtotal) je Projekt — DoD-Items sind JSON, PHP-Aggregation. */
    protected function dodRatio(array $requests): array
    {
        $ids = $this->collectSelectorIds($requests, 'project_id');
        $tasks = empty($ids) ? collect() : $this->scoped($requests)
            ->whereIn('project_id', $ids)
            ->get(['id', 'project_id', 'dod_items']);

        $agg = []; // project_id => [checked, total]
        foreach ($tasks as $task) {
            $p = $task->dod_progress; // {total, checked}
            if (($p['total'] ?? 0) > 0) {
                $agg[$task->project_id]['checked'] = ($agg[$task->project_id]['checked'] ?? 0) + $p['checked'];
                $agg[$task->project_id]['total'] = ($agg[$task->project_id]['total'] ?? 0) + $p['total'];
            }
        }

        $out = [];
        foreach ($requests as $key => $req) {
            $id = $req->selector['project_id'] ?? null;
            $a = $id !== null ? ($agg[$id] ?? null) : null;
            $total = (int) ($a['total'] ?? 0);
            if ($total === 0) {
                $out[$key] = MetricValue::unavailable('keine DoD-Items');
                continue;
            }
            $checked = (int) $a['checked'];
            $out[$key] = MetricValue::of($checked / $total, ['checked' => $checked, 'total' => $total], "$checked von $total DoD-Items");
        }

        return $out;
    }

    /** Überfällige, nicht erledigte Aufgaben je Projekt (down). */
    protected function overdueCount(array $requests): array
    {
        $ids = $this->collectSelectorIds($requests, 'project_id');
        $rows = empty($ids) ? collect() : $this->scoped($requests)
            ->whereIn('project_id', $ids)
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::now())
            ->selectRaw('project_id as k, count(*) as overdue')
            ->groupBy('project_id')
            ->get()->keyBy('k');

        $out = [];
        foreach ($requests as $key => $req) {
            $id = $req->selector['project_id'] ?? null;
            $row = $id !== null ? ($rows[$id] ?? null) : null;
            $overdue = (int) ($row?->overdue ?? 0);
            $out[$key] = MetricValue::of($overdue, ['overdue' => $overdue], "$overdue überfällig");
        }

        return $out;
    }

    /** Basis-Query mit Team-Scope (falls die Spalte existiert). Nutzt scope der ersten Request. */
    protected function scoped(array $requests): \Illuminate\Database\Eloquent\Builder
    {
        $query = PlannerTask::query();

        $teamIds = [];
        foreach ($requests as $req) {
            $teamIds = array_merge($teamIds, $req->scope['team_ids'] ?? []);
        }
        $teamIds = array_values(array_unique($teamIds));

        if (! empty($teamIds) && Schema::hasColumn('planner_tasks', 'team_id')) {
            $query->whereIn('team_id', $teamIds);
        }

        return $query;
    }

    protected function collectSelectorIds(array $requests, string $field): array
    {
        $ids = [];
        foreach ($requests as $req) {
            $id = $req->selector[$field] ?? null;
            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
