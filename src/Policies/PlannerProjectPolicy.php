<?php

namespace Platform\Planner\Policies;

use Platform\Core\Models\User;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Enums\ProjectRole;

class PlannerProjectPolicy
{
    /**
     * Hole die Projektrolle des Users (oder null, falls nicht beteiligt)
     */
    protected function getUserRole(User $user, PlannerProject $project): ?string
    {
        $relation = $project->projectUsers->firstWhere('user_id', $user->id);
        return $relation?->role ?? null;
    }

    /**
     * Darf der User dieses Projekt sehen?
     */
    public function view(User $user, PlannerProject $project): bool
    {
        // Nur Owner und Admins dürfen bearbeiten
        return in_array(
            $this->getUserRole($user, $project),
            [ProjectRole::OWNER->value, ProjectRole::ADMIN->value, ProjectRole::MEMBER->value],
            true
        );
    }

    /**
     * Darf der User dieses Projekt bearbeiten?
     */
    public function update(User $user, PlannerProject $project): bool
    {
        // Nur Owner und Admins dürfen bearbeiten
        return in_array(
            $this->getUserRole($user, $project),
            [ProjectRole::OWNER->value, ProjectRole::ADMIN->value, ProjectRole::MEMBER->value],
            true
        );
    }

    /**
     * Darf der User dieses Projekt löschen?
     */
    public function delete(User $user, PlannerProject $project): bool
    {
        // Nur Owner darf löschen
        return $this->getUserRole($user, $project) === ProjectRole::OWNER->value;
    }

    // Weitere Methoden nach Bedarf (create, assign, invite, ...)
}