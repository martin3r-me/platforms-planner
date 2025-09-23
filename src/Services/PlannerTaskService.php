<?php

namespace Platform\Planner\Services;

use Platform\Planner\Models\PlannerTask;

class PlannerTaskService
{
    // Liest Aufgaben des aktuellen Users im aktuellen Team
    public function listMyTasks(array $slots): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['ok' => false, 'message' => 'Nicht angemeldet'];
        }
        $teamId = $user->currentTeam?->id;
        if (!$teamId) {
            return ['ok' => false, 'message' => 'Kein Team konfiguriert'];
        }

        $tasks = PlannerTask::query()
            ->where('team_id', $teamId)
            ->where(function($q) use ($user){
                $q->where('user_id', $user->id)
                  ->orWhere('user_in_charge_id', $user->id);
            })
            ->whereNull('deleted_at')
            ->latest('id')
            ->limit(20)
            ->get(['id','title','due_date','is_done']);

        return [
            'ok' => true,
            'data' => [ 'tasks' => $tasks->toArray() ],
            'message' => 'Eigene Aufgaben (Top 20) geladen',
        ];
    }

    // Liest Aufgaben eines Projekts anhand ID/UUID/Name
    public function listProjectTasks(array $slots): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['ok' => false, 'message' => 'Nicht angemeldet'];
        }
        $teamId = $user->currentTeam?->id;
        if (!$teamId) {
            return ['ok' => false, 'message' => 'Kein Team konfiguriert'];
        }

        $projectId = $slots['project_id'] ?? null;
        $projectUuid = $slots['project_uuid'] ?? null;
        $projectName = $slots['project_name'] ?? null;

        $query = PlannerTask::query()
            ->where('team_id', $teamId)
            ->whereNull('deleted_at');

        if ($projectId) {
            $query->where('project_id', (int)$projectId);
        } elseif ($projectUuid) {
            $project = \Platform\Planner\Models\PlannerProject::where('uuid', $projectUuid)->first();
            if (!$project) return ['ok' => false, 'message' => 'Projekt nicht gefunden'];
            $query->where('project_id', $project->id);
        } elseif ($projectName) {
            $project = \Platform\Planner\Models\PlannerProject::where('team_id', $teamId)
                ->where('name', 'LIKE', '%'.$projectName.'%')
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$projectName])
                ->orderBy('name')
                ->first();
            if (!$project) return ['ok' => false, 'message' => 'Projekt nicht gefunden'];
            $query->where('project_id', $project->id);
        }

        $tasks = $query
            ->latest('id')
            ->limit(50)
            ->get(['id','uuid','title','due_date','is_done','project_id']);

        return [
            'ok' => true,
            'data' => [ 'tasks' => $tasks->toArray() ],
            'message' => 'Projekt-Aufgaben (Top 50) geladen',
        ];
    }

    // Öffnet einen Task via Route
    public function openTask(array $slots): array
    {
        $id = $slots['id'] ?? null;
        $uuid = $slots['uuid'] ?? null;
        $title = $slots['title'] ?? null;

        $task = null;
        if ($id) {
            $task = PlannerTask::find($id);
        } elseif ($uuid) {
            $task = PlannerTask::where('uuid', $uuid)->first();
        } elseif ($title) {
            $task = PlannerTask::where('title', 'LIKE', '%'.$title.'%')
                ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', [$title])
                ->orderBy('id', 'desc')
                ->first();
        }
        if (!$task) {
            return ['ok' => false, 'message' => 'Aufgabe nicht gefunden'];
        }

        $url = route('planner.tasks.show', $task);
        return ['ok' => true, 'navigate' => $url, 'message' => 'Aufgabe öffnen'];
    }
}


