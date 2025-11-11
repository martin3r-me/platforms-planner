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
    public function view(User $user, $project): bool
    {
        // 1. Projekt-Mitgliedschaft prüfen (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        if ($userRole !== null) {
            return true;
        }

        // 2. User hat Aufgaben im Projekt (auch wenn nicht als Mitglied eingetragen)
        // Das entspricht der Sidebar-Logik: Projekte mit User-Aufgaben werden angezeigt
        $hasTasks = $project->tasks()
            ->where('user_in_charge_id', $user->id)
            ->exists();

        if ($hasTasks) {
            return true;
        }

        // 3. User hat Aufgaben in Project-Slots
        $hasTasksInSlots = $project->projectSlots()
            ->whereHas('tasks', function ($q) use ($user) {
                $q->where('user_in_charge_id', $user->id);
            })
            ->exists();

        if ($hasTasksInSlots) {
            return true;
        }

        return false;
    }

    /**
     * Darf der User dieses Projekt bearbeiten?
     */
    public function update(User $user, $project): bool
    {
        // Projekt-Schreibrolle prüfen (egal in welchem Team)
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
    public function delete(User $user, $project): bool
    {
        // Nur Owner darf löschen (egal in welchem Team)
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
    public function invite(User $user, $project): bool
    {
        // Nur Owner und Admin können einladen (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        return in_array($userRole, [
            ProjectRole::OWNER->value,
            ProjectRole::ADMIN->value
        ], true);
    }

    /**
     * Darf der User Mitglieder entfernen?
     */
    public function removeMember(User $user, $project): bool
    {
        // Nur Owner und Admin können entfernen (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        return in_array($userRole, [
            ProjectRole::OWNER->value,
            ProjectRole::ADMIN->value
        ], true);
    }

    /**
     * Darf der User Rollen ändern?
     */
    public function changeRole(User $user, $project): bool
    {
        // Nur Owner kann Rollen ändern (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole === ProjectRole::OWNER->value;
    }

    /**
     * Darf der User das Projekt verlassen?
     */
    public function leave(User $user, $project): bool
    {
        // Owner kann nicht gehen (muss erst Ownership übertragen)
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole !== ProjectRole::OWNER->value;
    }

    /**
     * Darf der User Ownership übertragen?
     */
    public function transferOwnership(User $user, $project): bool
    {
        // Nur Owner kann Ownership übertragen (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole === ProjectRole::OWNER->value;
    }

    /**
     * Darf der User die Settings öffnen?
     * Jeder mit view-Rechten kann Settings öffnen (auch Viewer)
     */
    public function settings(User $user, $project): bool
    {
        // Jeder Projekt-Mitglied kann Settings öffnen
        return $this->view($user, $project);
    }

    /**
     * Hole die Projekt-Rolle des Users
     */
    protected function getUserProjectRole(User $user, $project): ?string
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