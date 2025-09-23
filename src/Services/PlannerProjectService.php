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
        // Route erwartet Parameter-Namen 'plannerProject' (Model-Binding)
        $url = route('planner.projects.show', ['plannerProject' => $proj->id]);
        return ['ok' => true, 'navigate' => $url, 'message' => 'Projekt öffnen'];
    }

    // Generische Projektabfrage
    public function queryProjects(array $slots): array
    {
        $user = auth()->user();
        if (!$user) return ['ok' => false, 'message' => 'Nicht angemeldet'];
        $teamId = $user->currentTeam?->id;
        if (!$teamId) return ['ok' => false, 'message' => 'Kein Team konfiguriert'];

        $q     = trim((string)($slots['q'] ?? ''));
        $id    = isset($slots['id']) ? (int)$slots['id'] : null;
        $uuid  = isset($slots['uuid']) ? (string)$slots['uuid'] : null;
        $sortInput = $slots['sort'] ?? 'name';
        $sort  = in_array($sortInput, ['name','id'], true) ? $sortInput : 'name';
        $order = strtolower((string)($slots['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $limit = min(max((int)($slots['limit'] ?? 50), 1), 100);
        $fieldsRaw = (string)($slots['fields'] ?? 'id,uuid,name');
        $fields = array_intersect(
            array_filter(array_map('trim', explode(',', $fieldsRaw))),
            ['id','uuid','name']
        );
        if (empty($fields)) { $fields = ['id','name']; }

        $query = PlannerProject::query()->where('team_id', $teamId);
        if ($id) { $query->where('id', $id); }
        if ($uuid) { $query->where('uuid', $uuid); }
        if ($q !== '') {
            $query->where('name', 'LIKE', '%'.$q.'%');
        }
        $projects = $query->orderBy($sort, $order)->limit($limit)->get($fields);
        return [
            'ok' => true,
            'data' => [ 'projects' => $projects->toArray() ],
            'message' => 'Projekte gefunden ('.$projects->count().')',
        ];
    }
}


