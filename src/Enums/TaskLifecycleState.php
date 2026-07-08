<?php

namespace Platform\Planner\Enums;

/**
 * Task lifecycle — finer than project, no dormant state.
 *
 * Manual:
 *   active -> completed  (task done, read-only)
 *   active -> discarded  (task will not be done, read-only)
 *
 * Cascade:
 *   When the parent project transitions to `discarded`, all still-active
 *   tasks in that project are automatically set to `discarded`.
 *
 * No activity-based auto-flip: tasks are granular enough that a human
 * manually closes or discards them.
 *
 * String values stay in German (product vocabulary, DB storage).
 */
enum TaskLifecycleState: string
{
    case ACTIVE = 'aktiv';
    case COMPLETED = 'erledigt';
    case DISCARDED = 'verworfen';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::COMPLETED => 'Erledigt',
            self::DISCARDED => 'Verworfen',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::DISCARDED], true);
    }

    public function isLive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function tone(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::COMPLETED => 'blue',
            self::DISCARDED => 'zinc',
        };
    }
}
