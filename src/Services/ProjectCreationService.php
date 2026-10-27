<?php

namespace Platform\Planner\Services;

use Platform\Core\Models\User;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;

/**
 * Anlegen aus der UI ("+ Neu" in der Actionbar, Modul-Sidebar): Projekt mit
 * Standard-Spalten und Canvas (Kickoff) bzw. Aufgabe — an einer Stelle.
 */
class ProjectCreationService
{
    public const DEFAULT_SLOTS = ['To Do', 'Doing', 'On Hold'];

    /** @return array{0: PlannerProject, 1: PlannerProjectCanvas} */
    public function createDefault(User $user, string $name = 'Neues Projekt'): array
    {
        $teamId = $user->currentTeam->id;

        $project = new PlannerProject();
        $project->name = $name;
        $project->user_id = $user->id;
        $project->team_id = $teamId;
        $project->order = (int) PlannerProject::where('team_id', $teamId)->max('order') + 1;
        $project->save();
        // Ersteller = user_id. Kein projectUsers-OWNER-Eintrag mehr (Zugriff = Graph).

        foreach (self::DEFAULT_SLOTS as $index => $slotName) {
            PlannerProjectSlot::create([
                'project_id' => $project->id,
                'name' => $slotName,
                'order' => $index + 1,
                'user_id' => $user->id,
                'team_id' => $teamId,
            ]);
        }

        $canvas = app(ProjectCanvasService::class)->ensureForProject($project, $user->id);

        return [$project, $canvas];
    }

    /** Neue Aufgabe oben in der Liste — im Projekt-Backlog oder persönlich (ohne Projekt). */
    public function createTask(User $user, ?PlannerProject $project = null, ?string $title = null): PlannerTask
    {
        $teamId = $project?->team_id ?? $user->currentTeam->id;

        $lowestOrder = PlannerTask::where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->min('order') ?? 0;

        return PlannerTask::create([
            'user_id' => $user->id,
            'user_in_charge_id' => $user->id,
            'project_id' => $project?->id,
            'project_slot_id' => null,
            'title' => $title ?: 'Neue Aufgabe',
            'team_id' => $teamId,
            'order' => $lowestOrder - 1,
        ]);
    }
}
