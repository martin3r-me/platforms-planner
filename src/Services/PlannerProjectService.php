<?php

namespace Platform\Planner\Services;

use Platform\Planner\Models\PlannerProject;

class PlannerProjectService
{
    public function listProjects(array $slots): array
    {
        $user = auth()->user();
        if (!$user) return ['ok' => false, 'message' => 'Nicht angemeldet'];
        $teamId = $user->currentTeam?->id;
        if (!$teamId) return ['ok' => false, 'message' => 'Kein Team konfiguriert'];

        $projects = PlannerProject::query()
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->limit(50)
            ->get(['id','uuid','name']);

        return ['ok' => true, 'data' => ['projects' => $projects->toArray()], 'message' => 'Projekte geladen'];
    }

    public function openProject(array $slots): array
    {
        $id = $slots['id'] ?? null;
        $uuid = $slots['uuid'] ?? null;
        $name = $slots['name'] ?? null;
        $proj = null;
        if ($id) {
            $proj = PlannerProject::find($id);
        } elseif ($uuid) {
            $proj = PlannerProject::where('uuid', $uuid)->first();
        } elseif ($name) {
            // Einfache unscharfe Suche nach Name
            $proj = PlannerProject::where('name', 'LIKE', '%'.$name.'%')
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$name])
                ->orderBy('name')
                ->first();
        }
        if (!$proj) return ['ok' => false, 'message' => 'Projekt nicht gefunden'];
        $url = route('planner.projects.show', ['project' => $proj->id]);
        return ['ok' => true, 'navigate' => $url, 'message' => 'Projekt öffnen'];
    }
}


