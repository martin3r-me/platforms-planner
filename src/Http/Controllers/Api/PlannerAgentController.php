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

        // Reihenfolge = Board-Layout (der Mensch priorisiert per Anordnung):
        // Slot-Order → Position im Slot. Ohne Slot zuletzt. Keine Fälligkeits-Heuristik.
        $task = $query
            ->leftJoin('planner_project_slots', 'planner_tasks.project_slot_id', '=', 'planner_project_slots.id')
            ->orderByRaw('planner_project_slots.order IS NULL')
            ->orderBy('planner_project_slots.order')
            ->orderBy('planner_tasks.project_slot_order')
            ->orderBy('planner_tasks.order')
            ->orderBy('planner_tasks.created_at')
            ->select('planner_tasks.*')
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
                // Kontext-Thread (Rückfragen/Antworten), falls der Worker Mitglied ist.
                'thread' => $this->contextThread($task, (int) $userId),
            ],
        ]);
    }

    /**
     * Pipeline-Leitwarte des Backoffice-Workers: seine zugewiesenen Tasks.
     *
     * GET /api/planner/agent/pipeline → { totals, next_up } (Task-geformt, keine Packages).
     */
    public function pipeline(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $completed = TaskLifecycleState::COMPLETED->value;
        $open = fn () => PlannerTask::query()
            ->where('user_in_charge_id', $userId)
            ->where('lifecycle_state', '!=', $completed);

        $nextUp = PlannerTask::query()
            ->where('user_in_charge_id', $userId)
            ->agentClaimable()
            ->with('project:id,name')
            ->leftJoin('planner_project_slots', 'planner_tasks.project_slot_id', '=', 'planner_project_slots.id')
            ->orderByRaw('planner_project_slots.order IS NULL')
            ->orderBy('planner_project_slots.order')
            ->orderBy('planner_tasks.project_slot_order')
            ->orderBy('planner_tasks.order')
            ->orderBy('planner_tasks.created_at')
            ->select('planner_tasks.*')
            ->limit(12)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'type' => 'task',
                'package' => optional($t->project)->name,
                'story_points' => $t->story_points?->value,
                'created_at' => optional($t->created_at)->toIso8601String(),
            ])->values();

        return response()->json(['data' => [
            'totals' => [
                'tasks' => $open()->count(),
                'ready' => PlannerTask::query()->where('user_in_charge_id', $userId)->agentClaimable()->count(),
                'rueckfragen' => $open()->where('agent_summary', 'like', 'RÜCKFRAGE:%')->count(),
                'oldest' => $open()->min('created_at'),
            ],
            'next_up' => $nextUp,
            'packages' => [], // Backoffice: keine Dev-Packages.
        ]]);
    }

    /**
     * Nachrichten des Context-Threads dieser Task — nur wenn der Worker Mitglied ist.
     * Liefert den bisherigen Rückfrage-/Antwort-Verlauf als Kontext fürs nächste Claimen.
     *
     * @return array<int, array{user_id:int, author:string, body:?string, at:?string}>|null
     */
    protected function contextThread(PlannerTask $task, int $workerId): ?array
    {
        $channel = \Platform\Core\Models\TerminalChannel::forTeam((int) $task->team_id)
            ->forContext(PlannerTask::class, $task->id)
            ->first();
        if (! $channel) {
            return null;
        }

        $isMember = \Platform\Core\Models\TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $workerId)->exists();
        if (! $isMember) {
            return null;
        }

        return $channel->messages()
            ->with('user:id,name')
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn ($m) => [
                'user_id' => (int) $m->user_id,
                'author' => $m->user?->name ?? ('User #'.$m->user_id),
                'body' => $m->body_plain,
                'at' => optional($m->created_at)->toIso8601String(),
            ])->values()->all();
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

        // Kurze Erledigt-Meldung in den Context-Thread (Thread anlegen, falls keiner) —
        // der Ersteller wird erwähnt und weiß Bescheid, auch ohne vorherige Rückfrage.
        $summary = trim((string) ($data['summary'] ?? ''));
        $this->postToContextThread($task, (int) $request->user()->id, '✅ Erledigt'.($summary !== '' ? ': '.$summary : '.'));

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

    /**
     * Rückfrage stellen: die Frage in den Context-Thread der Task posten (Thread
     * anlegen, falls keiner da) — Absender = Worker, IMMER den Ersteller (user_id)
     * erwähnen und als Mitglied sicherstellen. Danach die Task parken (agentDefer),
     * sie wartet damit auf die Antwort des Erstellers.
     *
     * POST /api/planner/agent/tasks/{id}/ask  { question }
     */
    public function ask(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate(['question' => 'required|string|max:5000']);

        $this->postToContextThread($task, (int) $request->user()->id, $data['question']);

        // Task parken (agentDefer setzt user_in_charge_id zurück auf den Ersteller,
        // bleibt aber ACTIVE). Rückweg: der Ersteller setzt den Verantwortlichen
        // wieder auf den Worker → next-task zieht sie samt Thread erneut.
        $task->agentDefer($data['question']);

        Log::info('[Planner Agent] Rückfrage in Context-Thread', ['task_id' => $task->id, 'creator_id' => (int) $task->user_id]);

        return response()->json(['message' => 'Question posted to context thread', 'data' => ['id' => $task->id]]);
    }

    /**
     * Postet eine Nachricht in den Context-Thread der Task (Thread anlegen, falls
     * keiner da) — Absender = Worker, IMMER den Ersteller (user_id) erwähnen und
     * als Mitglied sicherstellen.
     */
    protected function postToContextThread(PlannerTask $task, int $senderId, string $body): void
    {
        $recipients = array_values(array_filter([(int) $task->user_id]));  // Ersteller = Empfänger

        app(\Platform\Core\Services\PostContextMessage::class)->post(
            teamId: (int) $task->team_id,
            contextType: PlannerTask::class,
            contextId: $task->id,
            contextName: $task->title ?: 'Aufgabe',
            senderId: $senderId,
            body: $body,
            memberIds: $recipients,
            mentionUserIds: $recipients,   // immer erwähnen
        );
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
