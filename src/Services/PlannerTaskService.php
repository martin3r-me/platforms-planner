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
}


