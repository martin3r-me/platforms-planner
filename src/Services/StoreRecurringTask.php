<?php

namespace Platform\Planner\Services;

use Carbon\Carbon;
use Platform\Planner\Models\PlannerRecurringTask;

/**
 * Legt eine wiederkehrende Aufgabe (Vorlage) an — EINE Stelle für das MCP-Tool
 * (planner.recurring_tasks.POST) UND den Agent-Endpoint (POST planner/agent/recurring-tasks).
 *
 * Die Vorlage erzeugt (per Cron über shouldCreateTask/createTask) die eigentlichen PlannerTasks;
 * hier wird nur das Muster erstellt. Erwartet vor-validierte Werte (Enums/Policy prüft der Aufrufer);
 * parst next_due_date/end_date defensiv und lässt die Model-Defaults (booted()) die Restfelder füllen.
 */
class StoreRecurringTask
{
    /** @param array<string,mixed> $data */
    public function store(array $data): PlannerRecurringTask
    {
        $type = (string) ($data['recurrence_type'] ?? 'weekly');
        if (! in_array($type, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            $type = 'weekly';
        }

        $attrs = [
            'user_id'             => $data['user_id'] ?? null,
            'user_in_charge_id'   => $data['user_in_charge_id'] ?? ($data['user_id'] ?? null),
            'team_id'             => $data['team_id'] ?? null,
            'title'               => $data['title'],
            'description'         => $data['description'] ?? null,
            'recurrence_type'     => $type,
            'recurrence_interval' => max(1, (int) ($data['recurrence_interval'] ?? 1)),
            'next_due_date'       => $this->date($data['next_due_date'] ?? null) ?? now(),
            'recurrence_end_date' => $this->date($data['recurrence_end_date'] ?? null),
            'is_active'           => true,
        ];

        // Optionale Felder nur setzen, wenn geliefert — sonst greifen die Model-Defaults.
        foreach ([
            'project_id', 'project_slot_id', 'story_points', 'priority', 'planned_minutes',
            'weekday_mask', 'monthly_pattern', 'monthly_day_of_month', 'monthly_ordinal',
            'monthly_weekday', 'lead_time_days', 'max_occurrences', 'chain_on_complete',
            'skip_weekends', 'auto_delete_old_tasks', 'auto_mark_as_done',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $attrs[$key] = $data[$key];
            }
        }

        return PlannerRecurringTask::create($attrs);
    }

    private function date($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
