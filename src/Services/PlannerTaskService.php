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

    // Liest Aufgaben des aktuellen Users, die an einem Datum fällig sind (Default: heute)
    public function listMyTasksDue(array $slots): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['ok' => false, 'message' => 'Nicht angemeldet'];
        }
        $teamId = $user->currentTeam?->id;
        if (!$teamId) {
            return ['ok' => false, 'message' => 'Kein Team konfiguriert'];
        }

        $date = $slots['date'] ?? now()->toDateString();

        $tasks = PlannerTask::query()
            ->where('team_id', $teamId)
            ->where(function($q) use ($user){
                $q->where('user_id', $user->id)
                  ->orWhere('user_in_charge_id', $user->id);
            })
            ->whereNull('deleted_at')
            ->whereDate('due_date', $date)
            ->orderBy('is_done')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id','title','due_date','is_done']);

        return [
            'ok' => true,
            'data' => [ 'tasks' => $tasks->toArray(), 'date' => $date ],
            'message' => 'Fällige Aufgaben für '.$date,
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

    // Generische Aufgabenabfrage (sichere Filter/Sort/Limit/Fields)
    public function queryTasks(array $slots): array
    {
        $user = auth()->user();
        if (!$user) return ['ok' => false, 'message' => 'Nicht angemeldet'];
        $teamId = $user->currentTeam?->id;
        if (!$teamId) return ['ok' => false, 'message' => 'Kein Team konfiguriert'];

        $q          = trim((string)($slots['q'] ?? ''));
        $projectId  = isset($slots['project_id']) ? (int)$slots['project_id'] : null;
        $projectName= isset($slots['project_name']) ? (string)$slots['project_name'] : null;
        $assignedTo = isset($slots['assigned_to']) ? (int)$slots['assigned_to'] : null;
        $isDone     = array_key_exists('is_done', $slots) ? (bool)$slots['is_done'] : null;
        $status     = isset($slots['status']) ? (string)$slots['status'] : null;
        $dueFrom    = isset($slots['due_from']) ? (string)$slots['due_from'] : null;
        $dueTo      = isset($slots['due_to']) ? (string)$slots['due_to'] : null;
        $sort       = in_array(($slots['sort'] ?? 'id'), ['id','due_date','title'], true) ? $slots['sort'] : 'id';
        $order      = strtolower((string)($slots['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $limit      = min(max((int)($slots['limit'] ?? 20), 1), 100);
        $fields     = array_intersect(
            array_map('trim', explode(',', (string)($slots['fields'] ?? 'id,title,due_date,is_done,project_id'))),
            ['id','uuid','title','due_date','is_done','project_id','user_id','user_in_charge_id','status']
        );
        if (empty($fields)) { $fields = ['id','title','due_date','is_done']; }

        $query = PlannerTask::query()->where('team_id', $teamId)->whereNull('deleted_at');
        // Sichtbarkeit: eigene Aufgaben standardmäßig
        $query->where(function($q2) use ($user){
            $q2->where('user_id', $user->id)->orWhere('user_in_charge_id', $user->id);
        });

        if ($q !== '') {
            $query->where('title', 'LIKE', '%'.$q.'%');
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        } elseif ($projectName) {
            $project = \Platform\Planner\Models\PlannerProject::where('team_id', $teamId)
                ->where('name', 'LIKE', '%'.$projectName.'%')
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$projectName])
                ->orderBy('name')
                ->first();
            if ($project) {
                $query->where('project_id', $project->id);
            }
        }
        if (!is_null($assignedTo)) {
            $query->where(function($q3) use ($assignedTo){
                $q3->where('user_id', $assignedTo)->orWhere('user_in_charge_id', $assignedTo);
            });
        }
        if (!is_null($isDone)) {
            $query->where('is_done', $isDone);
        }
        if (!empty($status)) {
            $query->where('status', $status);
        }
        if (!empty($dueFrom)) {
            $query->whereDate('due_date', '>=', $dueFrom);
        }
        if (!empty($dueTo)) {
            $query->whereDate('due_date', '<=', $dueTo);
        }

        $tasks = $query->orderBy($sort, $order)->limit($limit)->get($fields);
        return [
            'ok' => true,
            'data' => [ 'tasks' => $tasks->toArray() ],
            'message' => 'Aufgaben gefunden ('.$tasks->count().')',
        ];
    }
}


