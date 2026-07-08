<?php

namespace Platform\Planner\Enums;

/**
 * Project lifecycle — one axis, four states.
 *
 * Automatic (ActivityClock, 45d threshold):
 *   active  <-> dormant
 *
 * Manual (owner):
 *   active/dormant -> completed  (goal achieved, read-only)
 *   active/dormant -> discarded  (ended without result; cascades open tasks)
 *   completed -> active          (reopen, logged)
 *   discarded -> active          (revive, logged)
 *
 * Technical (cleanup/admin):
 *   any -> soft-delete           (housekeeping, not a lifecycle state)
 *   soft-delete -> any           (restore, admin only)
 *
 * String values remain the German product vocabulary because they are
 * user-facing and stored in DB. Case names are English (dev vocabulary).
 */
enum ProjectLifecycleState: string
{
    case ACTIVE = 'aktiv';
    case DORMANT = 'ruhend';
    case COMPLETED = 'abgeschlossen';
    case DISCARDED = 'verworfen';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::DORMANT => 'Ruhend',
            self::COMPLETED => 'Abgeschlossen',
            self::DISCARDED => 'Verworfen',
        };
    }

    /** Terminal state (read-only, owner may reopen/revive). */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::DISCARDED], true);
    }

    /** "In play" — counts in health, portfolio, movement views. */
    public function isLive(): bool
    {
        return $this === self::ACTIVE;
    }

    /** UI colour hint — semantic only, no CSS coupling. */
    public function tone(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::DORMANT => 'amber',
            self::COMPLETED => 'blue',
            self::DISCARDED => 'zinc',
        };
    }
}
