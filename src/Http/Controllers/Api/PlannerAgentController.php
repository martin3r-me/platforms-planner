<?php

namespace Platform\Planner\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerTask;

/**
 * Agent-API für Backoffice-Worker — analog zum Dev-AgentController, aber schlanker:
 * die Arbeit sind Planner-Tasks, die dem Worker (= der authentifizierte User des
 * Bearer-Tokens) EXKLUSIV zugewiesen sind (user_in_charge_id). Kein Package/Board-
 * Gate — die Identität selbst ist der Filter.
 *
 * Routen: POST /api/planner/agent/next-task · /tasks/{id}/complete|defer|fail|unlock
 */
class PlannerAgentController extends Controller
{
    /** Die nächste dem Worker zugewiesene, offene Task holen und sperren. */
    public function nextTask(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = PlannerTask::query()
            ->assignedTo($userId)
            ->agentClaimable();

        // Story-Points-Filter (Worker sendet sein Limit aus den Settings).
        $maxPoints = $request->input('max_story_points');
        if ($maxPoints !== null) {
            $allowed = collect(TaskStoryPoints::cases())
                ->filter(fn ($sp) => $sp->points() <= (int) $maxPoints)
                ->pluck('value')->all();
            $query->where(function ($q) use ($allowed) {
                $q->whereNull('story_points')->orWhereIn('story_points', $allowed);
            });
        }

        $task = $query
            ->orderByRaw('due_date IS NULL, due_date ASC')  // Fällige zuerst, Undatierte danach
            ->orderBy('project_slot_order')
            ->orderBy('order')
            ->orderBy('created_at')
            ->first();

        if (! $task) {
            return response()->json(null, 204);
        }

        $task->update([
            'agent_locked_at' => now(),
            'agent_locked_by' => 'worker:' . $userId,
        ]);

        return response()->json([
            'data' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,   // Encryptable entschlüsselt beim Zugriff
                'dod' => $task->dod,
                'due_date' => optional($task->due_date)->toIso8601String(),
                'story_points' => $task->story_points?->value,
                'project_id' => $task->project_id,
                'type' => 'task',
            ],
        ]);
    }

    /** Task als erledigt melden + Notiz des Workers. */
    public function complete(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate(['summary' => 'nullable|string|max:10000']);
        $task->agentComplete($data['summary'] ?? null);
        Log::info('[Planner Agent] Task completed', ['task_id' => $task->id]);

        return response()->json(['message' => 'Task completed', 'data' => ['id' => $task->id, 'lifecycle_state' => 'erledigt']]);
    }

    /**
     * Verbrauchte Laufzeit auf die Task buchen (autonomer Worker, am Laufende).
     *
     * POST /api/planner/agent/tasks/{id}/log-time  { minutes, note? }
     *
     * Schreibt einen Organization-Zeiteintrag mit Kontext = diese PlannerTask, so
     * rollt die Laufzeit auf Task/Projekt/Team hoch. Worker (eigener Token) = User,
     * Team = Team der Task.
     */
    public function logTime(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate([
            'minutes' => 'required|integer|min:1|max:100000',
            'note' => 'nullable|string|max:1000',
        ]);

        // Organization-Service wiederverwenden (context_type = PlannerTask), guarded.
        $storeClass = \Platform\Organization\Services\StoreTimeEntry::class;
        if (! class_exists($storeClass)) {
            return response()->json(['message' => 'Time tracking unavailable (organization module missing)'], 501);
        }

        $entry = app($storeClass)->store([
            'team_id' => $task->team_id,
            'user_id' => $request->user()->id,
            'context_type' => PlannerTask::class,
            'context_id' => $task->id,
            'work_date' => now()->toDateString(),
            'minutes' => (int) $data['minutes'],
            'note' => $data['note'] ?? null,
            'metadata' => ['source' => 'agent'],
        ]);

        Log::info('[Planner Agent] Time logged', ['task_id' => $task->id, 'minutes' => (int) $data['minutes'], 'entry_id' => $entry->id]);

        return response()->json(['message' => 'Time logged', 'data' => ['id' => $entry->id, 'minutes' => $entry->minutes, 'task_id' => $task->id]]);
    }

    /** Rückfrage: zurück an den Delegierer, Frage anhängen. */
    public function defer(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate(['question' => 'required|string|max:10000']);
        $task->agentDefer($data['question']);
        Log::info('[Planner Agent] Task deferred (Rückfrage)', ['task_id' => $task->id]);

        return response()->json(['message' => 'Task deferred to delegator', 'data' => ['id' => $task->id]]);
    }

    /** Fehlgeschlagen: Notiz, Lock lösen (bleibt aktiv). */
    public function fail(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate(['error' => 'nullable|string|max:10000']);
        $task->agentFail($data['error'] ?? 'Unknown error');
        Log::warning('[Planner Agent] Task failed', ['task_id' => $task->id]);

        return response()->json(['message' => 'Task marked as failed', 'data' => ['id' => $task->id]]);
    }

    /** Hängengebliebenen Lock lösen (z. B. Worker-Crash). */
    public function unlock(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $task->agentUnlock();

        return response()->json(['message' => 'Task unlocked', 'data' => ['id' => $task->id]]);
    }

    /** Task nur, wenn sie dem Worker gehört (oder er sie gerade gelockt hat). */
    protected function ownedTask(Request $request, int $id): ?PlannerTask
    {
        $userId = $request->user()?->id;
        $task = PlannerTask::find($id);
        if (! $task || ! $userId) {
            return null;
        }
        // Zuweisung ODER eigener Lock (nach defer wechselt user_in_charge_id → dann greift der Lock).
        if ((int) $task->user_in_charge_id === (int) $userId || $task->agent_locked_by === 'worker:' . $userId) {
            return $task;
        }

        return null;
    }
}
