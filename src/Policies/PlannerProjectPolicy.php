<?php

namespace Platform\Planner\Policies;

use Platform\Core\Policies\RolePolicy;
use Platform\Core\Models\User;
use Platform\Planner\Models\PlannerProject;

/**
 * Zugriff auf Projekte = Ersteller (owns) ODER strukturell im Org-Graphen
 * erreichbar (may). Keine Projekt-Mitgliedschaft mehr — Teilen = im Graphen
 * aufhängen. read < write < manage.
 */
class PlannerProjectPolicy extends RolePolicy
{
    public function view(User $user, $project): bool
    {
        return $this->graphAllows($user, $project, 'read');
    }

    public function update(User $user, $project): bool
    {
        return $this->graphAllows($user, $project, 'write');
    }

    public function delete(User $user, $project): bool
    {
        return $this->graphAllows($user, $project, 'manage');
    }

    public function create(User $user): bool
    {
        // Jedes Team-Mitglied kann Projekte erstellen (wird sein Ersteller).
        return $user->currentTeam !== null;
    }

    public function settings(User $user, $project): bool
    {
        return $this->view($user, $project);
    }

    // Member-Verwaltung gibt es nicht mehr: „einladen/entfernen/Rollen" =
    // Projekt im Graphen (um)hängen. Als manage abgebildet; „leave" ist tot.
    public function invite(User $user, $project): bool
    {
        return $this->graphAllows($user, $project, 'manage');
    }

    public function removeMember(User $user, $project): bool
    {
        return $this->graphAllows($user, $project, 'manage');
    }

    public function changeRole(User $user, $project): bool
    {
        return $this->graphAllows($user, $project, 'manage');
    }

    public function transferOwnership(User $user, $project): bool
    {
        return $this->graphAllows($user, $project, 'manage');
    }

    public function leave(User $user, $project): bool
    {
        return false;
    }

    /**
     * Graph-Autorisierung: Ersteller (owns) ODER strukturell erreichbar (may).
     */
    protected function graphAllows(User $user, $project, string $cap): bool
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
     * Kein Projekt-Rollen-Konzept mehr (BasePolicy-Interface).
     */
    protected function getUserRole(User $user, $model): ?string
    {
        return null;
    }
}
