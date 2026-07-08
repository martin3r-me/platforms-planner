<?php

namespace Platform\Planner\Exceptions;

use RuntimeException;

/**
 * Raised by LifecycleService when a requested transition is not allowed
 * from the current state (e.g. completing an already-completed project,
 * reopening an active project, discarding a soft-deleted one).
 *
 * Caller is expected to catch and translate into a user-facing message,
 * or to check `LifecycleService::canTransitionTo()` before invoking.
 */
class InvalidLifecycleTransitionException extends RuntimeException
{
    public static function forProject(string $from, string $to): self
    {
        return new self("Project lifecycle transition not allowed: {$from} → {$to}");
    }

    public static function forTask(string $from, string $to): self
    {
        return new self("Task lifecycle transition not allowed: {$from} → {$to}");
    }
}
