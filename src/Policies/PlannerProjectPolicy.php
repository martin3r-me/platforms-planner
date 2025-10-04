<?php

namespace Platform\Planner\Policies;

use Platform\Core\Policies\RolePolicy;
use Platform\Core\Models\User;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Enums\ProjectRole;

class PlannerProjectPolicy extends RolePolicy
{
    /**
     * Darf der User dieses Projekt sehen?
     */
    public function view(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Projekt-Rolle prüfen (alle Rollen haben Leszugriff)
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole !== null;
    }

    /**
     * Darf der User dieses Projekt bearbeiten?
     */
    public function update(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Projekt-Schreibrolle prüfen
        $userRole = $this->getUserProjectRole($user, $project);
        return in_array($userRole, [
            ProjectRole::OWNER->value,
            ProjectRole::ADMIN->value,
            ProjectRole::MEMBER->value
        ], true);
    }

    /**
     * Darf der User dieses Projekt löschen?
     */
    public function delete(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Nur Owner darf löschen
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole === ProjectRole::OWNER->value;
    }

    /**
     * Darf der User dieses Projekt erstellen?
     */
    public function create(User $user): bool
    {
        // Jeder Team-Mitglied kann Projekte erstellen
        return $user->currentTeam !== null;
    }

    /**
     * Darf der User Mitglieder einladen?
     */
    public function invite(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Nur Owner und Admin können einladen
        $userRole = $this->getUserProjectRole($user, $project);
        return in_array($userRole, [
            ProjectRole::OWNER->value,
            ProjectRole::ADMIN->value
        ], true);
    }

    /**
     * Darf der User Mitglieder entfernen?
     */
    public function removeMember(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Nur Owner und Admin können entfernen
        $userRole = $this->getUserProjectRole($user, $project);
        return in_array($userRole, [
            ProjectRole::OWNER->value,
            ProjectRole::ADMIN->value
        ], true);
    }

    /**
     * Darf der User Rollen ändern?
     */
    public function changeRole(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Nur Owner kann Rollen ändern
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole === ProjectRole::OWNER->value;
    }

    /**
     * Darf der User das Projekt verlassen?
     */
    public function leave(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Owner kann nicht gehen (muss erst Ownership übertragen)
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole !== ProjectRole::OWNER->value;
    }

    /**
     * Darf der User Ownership übertragen?
     */
    public function transferOwnership(User $user, PlannerProject $project): bool
    {
        // Team-Zugriff prüfen
        if (!$this->isInTeam($user, $project)) {
            return false;
        }

        // Nur Owner kann Ownership übertragen
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole === ProjectRole::OWNER->value;
    }

    /**
     * Hole die Projekt-Rolle des Users
     */
    protected function getUserProjectRole(User $user, PlannerProject $project): ?string
    {
        $relation = $project->projectUsers()->where('user_id', $user->id)->first();
        return $relation?->role ?? null;
    }

    /**
     * BasePolicy-Interface implementieren
     */
    protected function getUserRole(User $user, $model): ?string
    {
        return $this->getUserProjectRole($user, $model);
    }
}