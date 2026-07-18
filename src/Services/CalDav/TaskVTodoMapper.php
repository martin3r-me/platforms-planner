<?php

namespace Platform\Planner\Services\CalDav;

use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Enums\TaskPriority;
use Platform\Planner\Models\PlannerTask;
use Sabre\VObject\Component\VCalendar;

/**
 * Bildet einen {@see PlannerTask} auf ein iCalendar-VTODO ab (read-only CalDAV).
 *
 * VTODO wird bewusst gewählt (nicht VEVENT): Apple *Erinnerungen* zeigt die
 * Kalender dadurch als abhakbare Aufgabenlisten. Der Mapper ist rein (kein
 * DB-Zugriff, kein HTTP). Siehe docs/caldav.md.
 */
class TaskVTodoMapper
{
    /** TaskPriority → iCal-PRIORITY (1 = höchste … 9 = niedrigste, 0 = keine). */
    private const PRIORITY_MAP = [
        'high'   => 1,
        'normal' => 5,
        'low'    => 9,
    ];

    public function toVCalendar(PlannerTask $task): VCalendar
    {
        $cal = new VCalendar([
            'PRODID' => '-//Platform Planner//CalDAV//DE',
            'VTODO' => [
                'UID' => $task->uuid,
                'SUMMARY' => (string) ($task->title ?? 'Aufgabe '.$task->getKey()),
            ],
        ]);

        $vtodo = $cal->VTODO;

        if ($task->due_date) {
            // DUE als Datum-Zeit; vobject erzeugt daraus das korrekte iCal-Format.
            $vtodo->add('DUE', $task->due_date);
        }

        $priority = $this->priority($task->priority);
        if ($priority !== null) {
            $vtodo->add('PRIORITY', $priority);
        }

        $this->addStatus($vtodo, $task);

        if (! empty($task->description)) {
            $vtodo->add('DESCRIPTION', $task->description);
        }

        if ($task->updated_at) {
            $vtodo->add('LAST-MODIFIED', $task->updated_at->format('Ymd\THis\Z'));
        }

        return $cal;
    }

    public function serialize(PlannerTask $task): string
    {
        return $this->toVCalendar($task)->serialize();
    }

    /**
     * Stabiler ETag, identisch im CalDAV-Backend genutzt.
     */
    public static function etagFor(PlannerTask $task): string
    {
        return '"' . md5(($task->updated_at?->getTimestamp() ?? 0) . ':' . $task->getKey()) . '"';
    }

    private function priority(?TaskPriority $priority): ?int
    {
        if ($priority === null) {
            return null;
        }

        return self::PRIORITY_MAP[$priority->value] ?? null;
    }

    private function addStatus($vtodo, PlannerTask $task): void
    {
        $state = $task->lifecycle_state;

        if ($state === TaskLifecycleState::COMPLETED) {
            $vtodo->add('STATUS', 'COMPLETED');
            $vtodo->add('PERCENT-COMPLETE', 100);
            // COMPLETED-Zeitpunkt, falls bekannt.
            if ($task->lifecycle_state_changed_at) {
                $vtodo->add('COMPLETED', $task->lifecycle_state_changed_at->format('Ymd\THis\Z'));
            }

            return;
        }

        // aktiv (und alles nicht-terminale) -> offene Aufgabe.
        $vtodo->add('STATUS', 'NEEDS-ACTION');
    }
}
