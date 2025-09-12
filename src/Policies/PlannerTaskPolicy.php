<?php

namespace Platform\Planner\Policies;

use Platform\Core\Models\User;
use Platform\Planner\Models\PlannerTask;

class PlannerTaskPolicy
{
    /**
     * Darf der User diese Aufgabe sehen?
     */
    public function view(User $user, PlannerTask $task): bool
    {
        // Persönliche Aufgabe (Owner)
        if ($task->user_id === $user->id) {
            return true;
        }

        // Team-Aufgabe: User ist im aktuellen Team
        if (
            $task->team_id &&
            $user->currentTeam &&
            $task->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    /**
     * Darf der User diese Aufgabe bearbeiten?
     */
    public function update(User $user, PlannerTask $task): bool
    {
        // Persönliche Aufgabe (Owner)
        if ($task->user_id === $user->id) {
            return true;
        }

        // Team-Aufgabe: User ist im aktuellen Team
        if (
            $task->team_id &&
            $user->currentTeam &&
            $task->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    /**
     * Darf der User diese Aufgabe löschen?
     */
    public function delete(User $user, PlannerTask $task): bool
    {
        // Persönliche Aufgabe (Owner)
        if ($task->user_id === $user->id) {
            return true;
        }

        // Team-Aufgabe: User ist im aktuellen Team
        if (
            $task->team_id &&
            $user->currentTeam &&
            $task->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    // Weitere Methoden nach Bedarf (create, complete, assign, ...)
}