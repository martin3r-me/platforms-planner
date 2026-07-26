<?php

namespace Platform\Planner\Services;

use Illuminate\Support\Carbon;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerTask;

/**
 * Persönliche Aufgaben-Zusammenfassung (user-scoped) für die persönliche Sicht (home).
 *
 * Kontrakt für „meine offenen Aufgaben" — analog zu PersonTimeSummary (organization).
 * Kapselt die Abfrage von PlannerTask, damit Konsumenten (home) nicht am Modell hängen.
 * Geliefert werden die Aufgaben, für die der User verantwortlich ist
 * (user_in_charge_id) und die noch aktiv sind — Frogs zuerst, dann nach Fälligkeit.
 */
class PersonTaskSummary
{
    /**
     * @return array{
     *   items: array<int, array<string,mixed>>,
     *   total: int, overdue: int, frogs: int
     * }
     */
    public function openForUser(int $userId): array
    {
        $tasks = PlannerTask::query()
            ->where('user_in_charge_id', $userId)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->with('project')
            ->get();

        $today = Carbon::today();

        $items = $tasks->map(function (PlannerTask $t) use ($today) {
            $due = $t->due_date ? Carbon::parse($t->due_date) : null;
            $isOverdue = $due !== null && $due->copy()->startOfDay()->lt($today);
            $isFrog = (bool) ($t->is_frog || $t->is_forced_frog);

            return [
                'id'             => (int) $t->id,
                'title'          => $t->title ?: 'Aufgabe',
                'project'        => $t->project?->name,
                'is_frog'        => $isFrog,
                'is_overdue'     => $isOverdue,
                'due'            => $due?->toDateString(),
                'due_label'      => $due
                    ? ($isOverdue
                        ? 'überfällig · ' . $due->locale('de')->isoFormat('D. MMM')
                        : $due->locale('de')->isoFormat('dd, D. MMM'))
                    : null,
                'priority'       => $t->priority?->value,
                'priority_label' => $t->priority?->label(),
                'priority_icon'  => method_exists($t->priority, 'icon') ? $t->priority?->icon() : null,
                '_frog_sort'     => $isFrog ? 0 : 1,
                '_due_sort'      => $due ? $due->timestamp : PHP_INT_MAX,
            ];
        })
        ->sortBy(fn ($i) => sprintf('%d-%020d', $i['_frog_sort'], $i['_due_sort']))
        ->map(function ($i) {
            unset($i['_frog_sort'], $i['_due_sort']);
            return $i;
        })
        ->values()
        ->all();

        return [
            'items'   => $items,
            'total'   => count($items),
            'overdue' => count(array_filter($items, fn ($i) => $i['is_overdue'])),
            'frogs'   => count(array_filter($items, fn ($i) => $i['is_frog'])),
        ];
    }
}
