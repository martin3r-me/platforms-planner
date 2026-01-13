<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Auth\Authenticatable;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Planner\Models\PlannerTask;

class ProcessAiAssignedTasks extends Command
{
    protected $signature = 'planner:process-ai-tasks
        {--limit=5 : Maximale Anzahl Tasks pro Run (0 = ohne Limit)}
        {--max-runtime-seconds=1200 : Maximale Laufzeit pro Run (Sekunden); danach beendet sich der Command und macht im nächsten Scheduler-Run weiter}
        {--task-id= : Optional: nur eine konkrete Task-ID bearbeiten}
        {--dry-run : Zeigt nur, was bearbeitet würde}
        {--max-iterations=40 : Max. Tool-Loop Iterationen pro Task}
        {--max-output-tokens=2000 : Max. Output Tokens pro LLM Call}
        {--no-web-search : Deaktiviert OpenAI web_search Tool}';

    protected $description = 'Sucht offene Planner-Tasks, die AI-Usern zugewiesen sind, und lässt sie autonom per LLM+Tools bearbeiten (Done oder Handoff).';

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $limit = (int)$this->option('limit');
        if ($limit < 0) { $limit = 1; }
        if ($limit === 0) {
            // unlimited (still bounded by max-runtime-seconds; we just keep picking next tasks)
            $limit = 1000000;
        } else {
            // keep a safety cap for accidental extremes
            if ($limit < 1) { $limit = 1; }
            if ($limit > 100) { $limit = 100; }
        }

        $maxRuntimeSeconds = (int)$this->option('max-runtime-seconds');
        if ($maxRuntimeSeconds < 10) { $maxRuntimeSeconds = 10; }
        if ($maxRuntimeSeconds > 12 * 60 * 60) { $maxRuntimeSeconds = 12 * 60 * 60; } // hard cap 12h
        $deadline = Carbon::now()->addSeconds($maxRuntimeSeconds);

        $taskId = $this->option('task-id');
        $taskId = is_numeric($taskId) ? (int)$taskId : null;

        $maxIterations = (int)$this->option('max-iterations');
        if ($maxIterations < 1) { $maxIterations = 1; }
        if ($maxIterations > 200) { $maxIterations = 200; }

        $maxOutputTokens = (int)$this->option('max-output-tokens');
        if ($maxOutputTokens < 64) { $maxOutputTokens = 64; }
        if ($maxOutputTokens > 200000) { $maxOutputTokens = 200000; }

        $includeWebSearch = !$this->option('no-web-search');

        // Global lock: prevent overlapping schedule runs.
        // Wichtig: wir brechen NICHT mitten in einer Task ab → der Run kann länger als max-runtime dauern.
        // Daher ein großzügiger TTL, damit der Scheduler nicht parallel startet.
        $lockTtlSeconds = max(6 * 60 * 60, $maxRuntimeSeconds + 60 * 60); // min 6h
        $lock = Cache::lock('planner:process-ai-tasks', $lockTtlSeconds);
        if (!$lock->get()) {
            $this->warn('⏳ Läuft bereits (Lock aktiv).');
            return Command::SUCCESS;
        }

        try {
            if ($dryRun) {
                $this->warn('🔍 DRY-RUN – es werden keine Daten geändert.');
            }

            $runner = AiToolLoopRunner::make();

            $processed = 0;
            $seenTaskIds = [];
            $originalAuthUser = Auth::user();

            while ($processed < $limit) {
                if (Carbon::now()->greaterThanOrEqualTo($deadline)) {
                    $this->warn("⏱️ Zeitbudget erreicht ({$maxRuntimeSeconds}s). Rest macht der nächste Scheduler-Run.");
                    break;
                }

                $task = $this->nextAiTask($taskId, $seenTaskIds);
                if (!$task) {
                    if ($processed === 0) {
                        $this->info('✅ Keine offenen AI-Tasks gefunden.');
                    }
                    break;
                }

                $seenTaskIds[] = (int)$task->id;
                $processed++;
                $aiUser = $task->userInCharge;
                if (!$aiUser || !$aiUser->isAiUser()) {
                    $this->line("• Task #{$task->id}: übersprungen (kein AI-User als Verantwortlicher).");
                    continue;
                }

                // Determine the "responsible human" fallback for handoff.
                $fallbackUserId = $this->determineFallbackUserId($task);
                if (!$fallbackUserId) {
                    // Fallback to task creator if possible; otherwise skip (we can't fulfill the handoff contract).
                    $fallbackUserId = $task->user_id ?: null;
                }

                $model = $this->determineModelForAiUser($aiUser);

                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🤖 Task #{$task->id} → AI-User {$aiUser->id} ({$aiUser->name})");
                $this->line("Titel: {$task->title}");
                $this->line("Team: " . ($task->team?->name ?? '—') . " | Projekt: " . ($task->project?->name ?? '—'));
                $this->line("Model: {$model}");
                $this->line("Fallback user_id (Handoff): " . ($fallbackUserId ?: '—'));

                if ($dryRun) {
                    continue;
                }

                // Impersonate AI user AND set current team context matching the task.
                $contextTeam = $this->determineContextTeamForTask($task, $aiUser);
                $this->impersonateForTask($aiUser, $contextTeam);
                $toolContext = new ToolContext($aiUser, $contextTeam);

                $messages = $this->buildAgentMessages($task, $aiUser, $fallbackUserId);

                $result = $runner->run(
                    $messages,
                    $model,
                    $toolContext,
                    [
                        'max_iterations' => $maxIterations,
                        'max_output_tokens' => $maxOutputTokens,
                        'include_web_search' => $includeWebSearch,
                        // Force route/module context off in CLI to avoid leaking or mis-scoping.
                        'reasoning' => ['effort' => 'medium'],
                    ]
                );

                // Reload and verify end state.
                $task->refresh();
                $task->loadMissing(['userInCharge']);

                if ($task->is_done) {
                    $this->info("✅ Task #{$task->id}: erledigt (is_done=true).");
                    continue;
                }

                if ($task->user_in_charge_id !== $aiUser->id) {
                    $this->warn("↪️ Task #{$task->id}: an user_id={$task->user_in_charge_id} übergeben.");
                    continue;
                }

                // Safety net: if the model didn't finalize, force a handoff + notes.
                $notes = trim((string)($result['assistant'] ?? ''));
                $this->warn("⚠️ Task #{$task->id}: keine finale Statusänderung – erzwinge Handoff.");

                if ($fallbackUserId) {
                    $task->user_in_charge_id = $fallbackUserId;
                }

                $task->description = $this->appendAiNotesToDescription(
                    (string)($task->description ?? ''),
                    $aiUser,
                    $model,
                    $notes !== '' ? $notes : 'Keine Ausgabe / leerer Agent-Output.'
                );
                $task->save();

                $this->warn("↪️ Task #{$task->id}: Handoff gespeichert (user_in_charge_id={$task->user_in_charge_id}).");
            }

            // Restore auth user for safety in long-running processes.
            if ($originalAuthUser instanceof Authenticatable) {
                Auth::setUser($originalAuthUser);
            } else {
                // In CLI there might be no authenticated user – don't call setUser(null) (SessionGuard disallows it).
                try { Auth::guard()->logout(); } catch (\Throwable $e) {}
            }

            $this->newLine();
            $this->info("✅ Fertig. Bearbeitet: {$processed} Task(s).");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Fehler: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            // Do not call setUser(null) – SessionGuard requires Authenticatable.
            // Best effort: logout if supported; otherwise leave as-is (process ends anyway).
            try { Auth::guard()->logout(); } catch (\Throwable $e) {}
            try { $lock->release(); } catch (\Throwable $e) {}
        }
    }

    private function nextAiTask(?int $taskId, array $excludeIds = []): ?PlannerTask
    {
        $query = PlannerTask::query()
            ->with(['user', 'userInCharge', 'team', 'project'])
            ->where('is_done', false)
            ->whereNotNull('user_in_charge_id')
            ->whereHas('userInCharge', fn ($q) => $q->where('type', 'ai_user'));

        if ($taskId) {
            $query->where('id', $taskId);
        }

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', array_map('intval', $excludeIds));
        }

        return $query
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id')
            ->first();
    }

    private function determineContextTeamForTask(PlannerTask $task, User $aiUser): ?Team
    {
        // Prefer the task team if present; otherwise project team; otherwise AI home team.
        if ($task->team) { return $task->team; }
        if ($task->project && $task->project->team) { return $task->project->team; }
        if ($aiUser->team) { return $aiUser->team; }
        return null;
    }

    private function impersonateForTask(User $aiUser, ?Team $team): void
    {
        Auth::setUser($aiUser);

        // Ensure currentTeamRelation works in CLI (request()->segment(1) is empty, so currentTeam returns base team).
        if ($team) {
            // Do NOT persist to DB; keep it in-memory only for this run.
            $aiUser->current_team_id = (int)$team->id;
            $aiUser->setRelation('currentTeamRelation', $team);
        }
    }

    private function determineModelForAiUser(User $aiUser): string
    {
        // 1) explicit model on AI user
        if ($aiUser->coreAiModel && is_string($aiUser->coreAiModel->model_id) && $aiUser->coreAiModel->model_id !== '') {
            return $aiUser->coreAiModel->model_id;
        }

        // 2) provider default model (openai)
        try {
            $provider = CoreAiProvider::where('key', 'openai')->where('is_active', true)->with('defaultModel')->first();
            $fallback = $provider?->defaultModel?->model_id;
            if (is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 3) hard fallback
        return 'gpt-5.2';
    }

    private function determineFallbackUserId(PlannerTask $task): ?int
    {
        // Prefer explicit creator if it's a real (non-ai) user.
        if ($task->user_id) {
            try {
                $u = User::find($task->user_id);
                if ($u && !$u->isAiUser()) {
                    return (int)$u->id;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Else: team owner if available
        if ($task->team_id) {
            try {
                $team = Team::find($task->team_id);
                if ($team && isset($team->user_id) && is_numeric($team->user_id)) {
                    return (int)$team->user_id;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return null;
    }

    /**
     * @return array<int, array{role:string, content:string}>
     */
    private function buildAgentMessages(PlannerTask $task, User $aiUser, ?int $fallbackUserId): array
    {
        $dod = trim((string)($task->dod ?? ''));
        $desc = trim((string)($task->description ?? ''));

        $system = "Du bist ein AI-User innerhalb einer Plattform.\n"
            . "Du arbeitest vollständig autonom (kein Rückfragen-Dialog mit einem Menschen).\n"
            . "Du darfst Tools verwenden (Function Calling). Nutze Tools, um Informationen zu sammeln und Aktionen auszuführen.\n"
            . "Antworte und schreibe Notizen immer auf Deutsch.\n\n"
            . "WICHTIG: Es gibt genau zwei Endzustände:\n"
            . "1) GELÖST: Setze die Aufgabe auf erledigt (is_done=true) über planner.tasks.PUT.\n"
            . "   Beispiel: planner.tasks.PUT {\"task_id\": {$task->id}, \"is_done\": true}\n"
            . "2) NICHT LÖSBAR: Übergib die Aufgabe an einen Menschen (user_in_charge_id={$fallbackUserId}) über planner.tasks.PUT\n"
            . "   Beispiel: planner.tasks.PUT {\"task_id\": {$task->id}, \"user_in_charge_id\": {$fallbackUserId}}\n"
            . "   und hänge deine Anmerkungen in die Anmerkung (description) an (mit Absatz, klarer Struktur).\n\n"
            . "Achte besonders auf die Definition of Done (DoD). Wenn DoD fehlt, definiere sinnvolle DoD-Kriterien oder handle konservativ.\n"
            . "Führe nur Änderungen aus, die du begründen kannst. Wenn dir Kontext/Rechte fehlen, wähle Endzustand 2.\n\n"
            . "Deine Identität: {$aiUser->name} (user_id={$aiUser->id}).";

        if (is_string($aiUser->instruction) && trim($aiUser->instruction) !== '') {
            $system .= "\n\nSpezielle Rolle/Instruktion:\n" . trim($aiUser->instruction);
        }

        $taskDump = [
            'task_id' => $task->id,
            'title' => $task->title,
            'team_id' => $task->team_id,
            'team' => $task->team?->name,
            'project_id' => $task->project_id,
            'project' => $task->project?->name,
            'due_date' => $task->due_date?->toIso8601String(),
            'created_by_user_id' => $task->user_id,
            'responsible_user_in_charge_id' => $task->user_in_charge_id,
            'definition_of_done' => $dod,
            'anmerkung' => $desc,
        ];

        $user = "Hallo.\n"
            . "Bitte löse die folgende Aufgabe selbstständig. Nutze alle Tools, die dir zur Verfügung stehen.\n"
            . "Wenn du fertig bist: setze is_done=true.\n"
            . "Wenn du es nicht lösen kannst: übergib an user_in_charge_id={$fallbackUserId} und schreibe deine Anmerkungen in die Anmerkung.\n\n"
            . "Aufgabe (JSON):\n" . json_encode($taskDump, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function appendAiNotesToDescription(string $existing, User $aiUser, string $model, string $notes): string
    {
        $existing = rtrim($existing);
        $stamp = Carbon::now()->format('Y-m-d H:i');

        $block = "— — —\n"
            . "AI-Worker Handoff ({$stamp})\n"
            . "AI-User: {$aiUser->name} (user_id={$aiUser->id})\n"
            . "Model: {$model}\n\n"
            . trim($notes) . "\n";

        if ($existing === '') {
            return $block;
        }

        return $existing . "\n\n" . $block;
    }
}

