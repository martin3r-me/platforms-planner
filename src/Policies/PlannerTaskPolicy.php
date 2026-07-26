<?php

namespace Platform\Planner\Policies;

use Platform\Core\Policies\BasePolicy;
use Platform\Core\Models\User;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;

class PlannerTaskPolicy extends BasePolicy
{
    /**
     * Darf der User diese Aufgabe sehen?
     */
    public function view(User $user, $task): bool
    {
        // 1. Owner hat immer Zugriff
        if ($this->isOwner($user, $task)) {
            return true;
        }

        // 2. Zugewiesener User (Verantwortlicher) kann sehen
        if ($task->user_in_charge_id === $user->id) {
            return true;
        }

        // 3. Aufgabe ohne Projekt: Nur Owner
        if (!$task->project_id) {
            return false;
        }

        // 4. Aufgabe mit Projekt: Projekt-Mitgliedschaft reicht (egal in welchem Team)
        $project = $task->project;
        if (!$project) {
            return false;
        }

        return $this->canAccessProject($user, $project);
    }

    /**
     * Darf der User diese Aufgabe bearbeiten?
     */
    public function update(User $user, $task): bool
    {
        // 1. Owner hat immer Zugriff
        if ($this->isOwner($user, $task)) {
            return true;
        }

        // 2. Aufgabe ohne Projekt: Nur Owner
        if (!$task->project_id) {
            return false;
        }

        // 3. Aufgabe mit Projekt: Projekt-Schreibberechtigung prüfen
        $project = $task->project;
        if (!$project) {
            return false;
        }

        return $this->canWriteProject($user, $project);
    }

    /**
     * Darf der User diese Aufgabe löschen?
     */
    public function delete(User $user, $task): bool
    {
        // 1. Owner hat immer Zugriff
        if ($this->isOwner($user, $task)) {
            return true;
        }

        // 2. Aufgabe ohne Projekt: Nur Owner
        if (!$task->project_id) {
            return false;
        }

        // 3. Aufgabe mit Projekt: Projekt-Admin-Berechtigung prüfen
        $project = $task->project;
        if (!$project) {
            return false;
        }

        return $this->canAdminProject($user, $project);
    }

    /**
     * Darf der User diese Aufgabe erstellen?
     */
    public function create(User $user, ?PlannerProject $project = null): bool
    {
        // Ohne Projekt: Jeder kann persönliche Aufgaben erstellen
        if (!$project) {
            return true;
        }

        // Mit Projekt: Projekt-Schreibberechtigung prüfen
        return $this->canWriteProject($user, $project);
    }

    /**
     * Darf der User diese Aufgabe zuweisen?
     */
    public function assign(User $user, $task): bool
    {
        // 1. Owner hat immer Zugriff
        if ($this->isOwner($user, $task)) {
            return true;
        }

        // 2. Aufgabe ohne Projekt: Nur Owner
        if (!$task->project_id) {
            return false;
        }

        // 3. Aufgabe mit Projekt: Projekt-Schreibberechtigung prüfen
        $project = $task->project;
        if (!$project) {
            return false;
        }

        return $this->canWriteProject($user, $project);
    }

    /**
     * Darf der User diese Aufgabe abschließen?
     */
    public function complete(User $user, $task): bool
    {
        // 1. Owner hat immer Zugriff
        if ($this->isOwner($user, $task)) {
            return true;
        }

        // 2. Zugewiesener User (Verantwortlicher) kann abschließen
        if ($task->user_in_charge_id === $user->id) {
            return true;
        }

        // 3. Aufgabe ohne Projekt: Nur Owner
        if (!$task->project_id) {
            return false;
        }

        // 4. Aufgabe mit Projekt: Projekt-Schreibberechtigung prüfen
        $project = $task->project;
        if (!$project) {
            return false;
        }

        return $this->canWriteProject($user, $project);
    }

    /**
     * Graph-Autorisierung auf dem PROJEKT (Ersteller oder strukturell erreichbar).
     * Aktiv wenn authz.enforce_planner an ist — Mitgliedschaft greift dann nicht.
     */
    protected function projectGraphAllows(User $user, $project, string $cap): bool
    {
        if (! $project || ! $project->id) {
            return false;
        }
        $resolver = app(\Platform\Core\Authz\AuthzResolver::class);
        $type = PlannerProject::class;

        return $resolver->may($user, $cap, $type, (int) $project->id)
            || $resolver->owns($user, $type, (int) $project->id);
    }

    /**
     * Prüft Projekt-Zugriff (Lesen)
     */
    protected function canAccessProject(User $user, $project): bool
    {
        if (config('authz.enforce_planner')) {
            return $this->projectGraphAllows($user, $project, 'read');
        }
        // Projekt-Mitgliedschaft prüfen (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        return $userRole !== null;
    }

    /**
     * Prüft Projekt-Schreibzugriff
     */
    protected function canWriteProject(User $user, $project): bool
    {
        if (config('authz.enforce_planner')) {
            return $this->projectGraphAllows($user, $project, 'write');
        }
        // Projekt-Schreibrolle prüfen (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        return in_array($userRole, ['owner', 'admin', 'member'], true);
    }

    /**
     * Prüft Projekt-Admin-Zugriff
     */
    protected function canAdminProject(User $user, $project): bool
    {
        if (config('authz.enforce_planner')) {
            return $this->projectGraphAllows($user, $project, 'manage');
        }
        // Projekt-Admin-Rolle prüfen (egal in welchem Team)
        $userRole = $this->getUserProjectRole($user, $project);
        return in_array($userRole, ['owner', 'admin'], true);
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
        // Für Aufgaben ohne Projekt: Owner-Pattern
        if (!$model->project_id) {
            return $this->isOwner($user, $model) ? 'owner' : null;
        }

        // Für Aufgaben mit Projekt: Projekt-Rolle
        $project = $model->project;
        if (!$project) {
            return null;
        }

        return $this->getUserProjectRole($user, $project);
    }
}