<?php

namespace Platform\Planner\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Planner\Enums\ProjectKind;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerProject;
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

        // Resume-First: hat eine wartende Task dieses Workers eine Antwort im Thread
        // bekommen? Dann DIESE zuerst — die geparkte Session wird fortgesetzt (die Antwort
        // steckt im mitgelieferten Thread), statt neue Arbeit zu ziehen. Die Triage-Pflicht
        // ist eine Eigenschaft des Projekts (siehe requiresTriageDone).
        if (($resume = $this->resumableTask((int) $userId))) {
            $resume->update([
                'agent_waiting_at' => null,
                'agent_locked_at' => now(),
                'agent_locked_by' => 'worker:' . $userId,
            ]);

            return response()->json(['data' => $this->taskPayload($resume, (int) $userId, true)]);
        }

        $query = PlannerTask::query()
            ->assignedTo($userId)
            ->agentClaimable()
            ->where(fn ($q) => $this->requiresTriageDone($q));

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

        return response()->json(['data' => $this->taskPayload($task, (int) $userId)]);
    }

    /**
     * Triage-Claim: nächste NOCH UNGEPRÜFTE Task (triage_done_at NULL) des Workers — für die
     * Reife-Prüfung (Story-Points + Inhalt) vor der Ausführung. Reihenfolge/Lock wie nextTask,
     * nur auf ungeprüft gefiltert. Resume-First holt eine beantwortete Triage-Rückfrage zuerst.
     *
     * POST /api/planner/agent/next-untriaged-task
     */
    public function nextUntriagedTask(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Resume-First: eine beantwortete Triage-Rückfrage (noch ungeprüft) zuerst.
        if (($resume = $this->resumableUntriagedTask((int) $userId))) {
            $resume->update([
                'agent_waiting_at' => null,
                'agent_locked_at' => now(),
                'agent_locked_by' => 'triage:' . $userId,
            ]);

            return response()->json(['data' => $this->taskPayload($resume, (int) $userId, true)]);
        }

        $query = PlannerTask::query()
            ->assignedTo($userId)
            ->agentClaimable()
            ->untriaged()
            ->whereHas('project', fn ($p) => $p->where('require_triage', true));

        // KEIN Story-Points-Filter: die Triage SCHÄTZT die Größe — sie darf große Tasks nicht
        // überspringen. Das max_story_points-Limit ist ein Execute-Konzept, kein Triage-Konzept.

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
            'agent_locked_by' => 'triage:' . $userId,
        ]);

        return response()->json(['data' => $this->taskPayload($task, (int) $userId)]);
    }

    /**
     * Eine noch UNGEPRÜFTE (triage_done_at NULL) wartende Task dieses Workers, die seit dem
     * Warten eine Antwort im Kontext-Thread bekommen hat — fürs Fortsetzen des Reife-Checks.
     */
    protected function resumableUntriagedTask(int $userId): ?PlannerTask
    {
        return PlannerTask::query()
            ->assignedTo($userId)
            ->where('lifecycle_state', \Platform\Planner\Enums\TaskLifecycleState::ACTIVE->value)
            ->untriaged()
            ->whereHas('project', fn ($p) => $p->where('require_triage', true))
            ->whereNotNull('agent_waiting_at')
            ->whereExists(function ($q) use ($userId) {
                $q->select(DB::raw(1))
                    ->from('terminal_messages as tm')
                    ->join('terminal_channels as tc', 'tm.channel_id', '=', 'tc.id')
                    ->whereColumn('tc.context_id', 'planner_tasks.id')
                    ->where('tc.context_type', PlannerTask::class)
                    ->where('tm.user_id', '!=', $userId)
                    ->whereColumn('tm.created_at', '>', 'planner_tasks.agent_waiting_at');
            })
            ->leftJoin('planner_project_slots', 'planner_tasks.project_slot_id', '=', 'planner_project_slots.id')
            ->orderByRaw('planner_project_slots.order IS NULL')
            ->orderBy('planner_project_slots.order')
            ->orderBy('planner_tasks.agent_waiting_at')
            ->select('planner_tasks.*')
            ->first();
    }

    /**
     * Triage-Entscheidung committen. ready=true → Task für die Ausführung freigeben
     * (triage_done_at setzen, optional Story-Points korrigieren, Lock lösen). ready=false →
     * Rückfrage in den Context-Thread + Warten-Zustand (wie ask); triage_done_at bleibt leer,
     * bis der Reife-Check nach der Antwort erneut läuft und freigibt.
     *
     * POST /api/planner/agent/tasks/{id}/triage  { ready, story_points?, question?, session_id? }
     */
    public function triageTask(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate([
            'ready' => 'required|boolean',
            'story_points' => 'nullable|string|max:8',
            'question' => 'nullable|string|max:5000',
            'session_id' => 'nullable|string|max:255',
        ]);

        $spValue = strtolower(trim((string) ($data['story_points'] ?? '')));
        $allowedSp = collect(TaskStoryPoints::cases())->map(fn ($s) => $s->value)->all();
        $storyPoints = in_array($spValue, $allowedSp, true) ? $spValue : null;

        if ($data['ready']) {
            $task->update([
                'triage_done_at' => now(),
                'story_points' => $storyPoints ?? $task->story_points?->value,
                'agent_waiting_at' => null,
                'agent_session_id' => null,
                'agent_locked_at' => null,
                'agent_locked_by' => null,
            ]);
            Log::info('[Planner Agent] Task triaged (ready)', ['task_id' => $task->id]);

            return response()->json(['message' => 'Task triaged', 'data' => [
                'id' => $task->id,
                'triage_done_at' => $task->triage_done_at?->toIso8601String(),
                'story_points' => $task->story_points?->value,
            ]]);
        }

        // Nicht reif → Rückfrage stellen (wie ask): Thread + Warten-Zustand, ungeprüft lassen.
        $question = trim((string) ($data['question'] ?? ''));
        if ($question === '') {
            return response()->json(['message' => 'Question required when not ready'], 422);
        }
        $this->postToContextThread($task, (int) $request->user()->id, $question);
        $task->agentWait($question, $data['session_id'] ?? null);
        Log::info('[Planner Agent] Task triage question', ['task_id' => $task->id]);

        return response()->json(['message' => 'Triage question posted', 'data' => ['id' => $task->id]]);
    }

    /**
     * Einheitliches Task-Payload für Claim + Resume. Bei $resume=true weiß der Worker,
     * dass er die gemerkte Claude-Session fortsetzt (die Antwort steckt im `thread`).
     * agent_branch bleibt null — der Backoffice-Worker hat keinen Git-Branch.
     *
     * @return array<string, mixed>
     */
    protected function taskPayload(PlannerTask $task, int $userId, bool $resume = false): array
    {
        return [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'title' => $task->title,
            'description' => $task->description,   // Encryptable entschlüsselt beim Zugriff
            'dod' => $task->dod,
            'due_date' => optional($task->due_date)->toIso8601String(),
            'story_points' => $task->story_points?->value,
            'project_id' => $task->project_id,
            // Projekt-Gedächtnis: wiederverwendbare Lektionen aus früheren Tasks dieses Projekts.
            'project_lessons' => $task->project_id ? optional($task->project)->agent_lessons : null,
            'type' => 'task',
            // Kontext-Thread (Rückfragen/Antworten), falls der Worker Mitglied ist.
            'thread' => $this->contextThread($task, $userId),
            // Resume-Signal: gemerkte Session → Worker setzt fort statt neu.
            'resume' => $resume,
            'agent_session_id' => $resume ? $task->agent_session_id : null,
            'agent_branch' => null,
        ];
    }

    /**
     * Eine wartende (agent_waiting_at) Task dieses Workers, die seit dem Warten eine
     * Antwort im Kontext-Thread von jemand anderem bekommen hat — in Board-Reihenfolge.
     */
    protected function resumableTask(int $userId): ?PlannerTask
    {
        return PlannerTask::query()
            ->assignedTo($userId)
            ->where('lifecycle_state', \Platform\Planner\Enums\TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('agent_waiting_at')
            // Projekte mit Triage-Pflicht: offene Triage-Rückfragen (noch nicht triagiert) sind
            // Sache des Triage-Claims — die Ausführung setzt erst nach der Freigabe fort.
            ->where(fn ($q) => $this->requiresTriageDone($q))
            ->whereExists(function ($q) use ($userId) {
                $q->select(DB::raw(1))
                    ->from('terminal_messages as tm')
                    ->join('terminal_channels as tc', 'tm.channel_id', '=', 'tc.id')
                    ->whereColumn('tc.context_id', 'planner_tasks.id')
                    ->where('tc.context_type', PlannerTask::class)
                    ->where('tm.user_id', '!=', $userId)
                    ->whereColumn('tm.created_at', '>', 'planner_tasks.agent_waiting_at');
            })
            ->leftJoin('planner_project_slots', 'planner_tasks.project_slot_id', '=', 'planner_project_slots.id')
            ->orderByRaw('planner_project_slots.order IS NULL')
            ->orderBy('planner_project_slots.order')
            ->orderBy('planner_tasks.project_slot_order')
            ->orderBy('planner_tasks.order')
            ->orderBy('planner_tasks.agent_waiting_at')
            ->select('planner_tasks.*')
            ->first();
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
                'rueckfragen' => $open()->whereNotNull('agent_waiting_at')->count(),
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

    /**
     * Self-Assigned Task aus einer kontextlosen Nachricht anlegen (Gate: new_task).
     * Der Worker macht sich selbst zum Verantwortlichen und dokumentiert die Herkunft —
     * damit ist die Arbeit auditierbar (Zeit bucht später auf DIESEN Task). Kein Lauf
     * abseits eines dokumentierten Tasks.
     *
     * POST /api/planner/agent/tasks  { title, description?, origin? }
     */
    public function createTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'origin' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $teamId = (int) ($user->current_team_id ?? 0);
        if ($teamId < 1) {
            return response()->json(['message' => 'No team context for worker'], 422);
        }

        // Herkunft in die Beschreibung — nichts lebt nur im Chat.
        $description = trim((string) ($data['description'] ?? ''));
        if (! empty($data['origin'])) {
            $description = trim($description."\n\nHerkunft: ".$data['origin']);
        }

        // Persönlicher Task (kein Projekt), oben in der eigenen Liste.
        $order = (PlannerTask::where('user_in_charge_id', $user->id)
            ->whereNull('project_id')->min('order') ?? 0) - 1;

        $task = PlannerTask::create([
            'title' => $data['title'],
            'description' => $description !== '' ? $description : null,
            'user_id' => $user->id,
            'user_in_charge_id' => $user->id,   // self-assigned
            'team_id' => $teamId,
            'project_id' => null,               // persönlich
            'lifecycle_state' => TaskLifecycleState::ACTIVE->value,
            'order' => $order,
            'agent_summary' => 'Vom Worker aus einer Nachricht angelegt.',
        ]);

        $task->logActivity('Worker hat diese Aufgabe aus einer eingehenden Nachricht angelegt.'
            .(! empty($data['origin']) ? "\n\n".$data['origin'] : ''), [
                'source' => 'agent', 'status' => 'created',
            ]);

        Log::info('[Planner Agent] Self-Task aus Nachricht angelegt', ['task_id' => $task->id, 'user_id' => $user->id]);

        return response()->json(['data' => ['id' => $task->id, 'uuid' => $task->uuid]], 201);
    }

    /** Task als erledigt melden + Notiz des Workers. */
    public function complete(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate([
            'summary' => 'nullable|string|max:10000',
            'lesson' => 'nullable|string|max:2000',
        ]);
        $task->agentComplete($data['summary'] ?? null);

        // Projekt-Gedächtnis: eine wiederverwendbare Lektion ans Projekt anhängen (nur Projekt-
        // Tasks). Kappen, damit es nicht wuchert (jüngstes behalten).
        $lesson = trim((string) ($data['lesson'] ?? ''));
        if ($lesson !== '' && $task->project_id && ($project = $task->project)) {
            $merged = trim((string) $project->agent_lessons."\n- ".$lesson);
            if (mb_strlen($merged) > 8000) {
                $merged = '…'.mb_substr($merged, -7999);
            }
            $project->update(['agent_lessons' => $merged]);
        }

        // Erledigt-Meldung: existiert schon ein Rückfrage-Thread → dort (Kreis schließen),
        // sonst DM an den Ersteller. Für „fertig" lohnt kein neuer Thread.
        $summary = trim((string) ($data['summary'] ?? ''));
        $body = '✅ Erledigt: '.($task->title ?: 'Aufgabe').($summary !== '' ? "\n\n".$summary : '');
        $this->announceCompletion($task, (int) $request->user()->id, $body);

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
        $data = $request->validate([
            'question' => 'required|string|max:5000',
            'branch' => 'nullable|string|max:255',
            'session_id' => 'nullable|string|max:255',
        ]);

        $this->postToContextThread($task, (int) $request->user()->id, $data['question']);

        // Auf Antwort warten statt zurück-delegieren: Owner bleibt der Worker, Warten-
        // Zustand + Session merken. Der Resume-First-Pass in next-task holt die Task
        // erneut (samt Thread), sobald der Ersteller antwortet.
        $task->agentWait($data['question'], $data['session_id'] ?? null);

        Log::info('[Planner Agent] Rückfrage in Context-Thread', ['task_id' => $task->id, 'creator_id' => (int) $task->user_id]);

        return response()->json(['message' => 'Question posted to context thread', 'data' => ['id' => $task->id]]);
    }

    /**
     * Erfolgsmeldung zustellen: existiert schon ein Kontext-Thread (es lief eine
     * Rückfrage) → dort rein, um den Kreis zu schließen. Sonst als DM an den
     * Ersteller — für ein einmaliges „fertig" lohnt kein neuer Thread.
     */
    protected function announceCompletion(PlannerTask $task, int $senderId, string $body): void
    {
        $hasThread = \Platform\Core\Models\TerminalChannel::forTeam((int) $task->team_id)
            ->forContext(PlannerTask::class, $task->id)
            ->exists();

        if ($hasThread) {
            $this->postToContextThread($task, $senderId, $body);

            return;
        }

        app(\Platform\Core\Services\PostDirectMessage::class)
            ->post((int) $task->team_id, $senderId, (int) $task->user_id, $body);
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

    /** Rückfrage: auf Antwort warten (Owner bleibt Worker, Session gemerkt). */
    public function defer(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $data = $request->validate([
            'question' => 'required|string|max:10000',
            'branch' => 'nullable|string|max:255',
            'session_id' => 'nullable|string|max:255',
        ]);
        $task->agentWait($data['question'], $data['session_id'] ?? null);
        Log::info('[Planner Agent] Task waiting for answer (Rückfrage)', ['task_id' => $task->id]);

        return response()->json(['message' => 'Task waiting for answer', 'data' => ['id' => $task->id]]);
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

    /**
     * Ausführbarkeits-Gate: eine Task darf ausgeführt werden, wenn ihr Projekt KEINE Triage
     * verlangt (oder keins hat) ODER sie bereits triagiert ist (triage_done_at). Die Pflicht
     * ist eine Eigenschaft der Quelle (Projekt), nicht des Workers. Als verschachtelte
     * where-Gruppe gedacht (`->where(fn($q) => $this->requiresTriageDone($q))`).
     */
    protected function requiresTriageDone($query)
    {
        return $query
            ->whereDoesntHave('project', fn ($p) => $p->where('require_triage', true))
            ->orWhereNotNull('planner_tasks.triage_done_at');
    }

    /**
     * Assistenz-Behälter (find-or-create): ein RUN-Projekt „Assistenz · <Chef>", das dem WORKER
     * gehört (der Ausführende = VSM System 1; der Chef ist die Dimension, nicht der Eigentümer).
     * Team = ein von Worker + Chef geteiltes Team (bevorzugt das aktuelle Team des Workers), damit
     * es sauber aufrollt. Der Assistent stempelt seine Laufzeit auf dieses Projekt.
     *
     * POST /api/planner/agent/assistant-project  { served_user_id }
     */
    public function assistantProject(Request $request): JsonResponse
    {
        $worker = $request->user();
        if (! $worker) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $data = $request->validate(['served_user_id' => 'required|integer|min:1']);
        $chef = \Platform\Core\Models\User::find((int) $data['served_user_id']);
        if (! $chef) {
            return response()->json(['message' => 'Chef not found'], 404);
        }

        // Geteiltes Team (Worker ∩ Chef), bevorzugt das aktuelle Team des Workers.
        $workerTeams = $worker->teams()->pluck('teams.id')->all();
        $chefTeams = $chef->teams()->pluck('teams.id')->all();
        $shared = array_values(array_intersect($workerTeams, $chefTeams));
        $teamId = in_array($worker->current_team_id, $shared, true)
            ? (int) $worker->current_team_id
            : (int) ($shared[0] ?? $worker->current_team_id);
        if (! $teamId) {
            return response()->json(['message' => 'No shared team for worker and chef'], 422);
        }

        $name = 'Assistenz · '.($chef->name ?: ('User #'.$chef->id));

        // Idempotent: dem Worker gehörendes RUN-Projekt gleichen Namens im Team wiederverwenden.
        $project = PlannerProject::query()
            ->where('user_id', $worker->id)
            ->where('team_id', $teamId)
            ->where('kind', ProjectKind::RUN->value)
            ->where('name', $name)
            ->first();

        if (! $project) {
            $project = new PlannerProject();
            $project->name = $name;
            $project->user_id = (int) $worker->id;        // Eigentümer = der Worker (S1)
            $project->team_id = $teamId;
            $project->kind = ProjectKind::RUN;
            $project->lifecycle_state = ProjectLifecycleState::ACTIVE;
            $project->order = (int) PlannerProject::where('team_id', $teamId)->max('order') + 1;
            $project->save();
            Log::info('[Planner Agent] Assistenz-RUN-Projekt angelegt', ['project_id' => $project->id, 'chef_id' => $chef->id]);
        }

        return response()->json(['data' => ['id' => $project->id, 'name' => $project->name, 'team_id' => $teamId]]);
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
