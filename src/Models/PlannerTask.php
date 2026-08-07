<?php

namespace Platform\Planner\Models;

use Platform\Planner\Enums\TaskPriority;
use Platform\Planner\Enums\TaskStoryPoints;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Organization\Traits\HasTimeEntries;
use Platform\Organization\Traits\HasPlannedTime;
use Platform\Core\Traits\HasTags;
use Platform\Core\Traits\HasColors;
use Platform\Core\Traits\Encryptable;
use Platform\Core\Traits\HasExtraFields;
use Platform\Core\Traits\TracksLastViewed;
use Platform\Core\Contracts\HasKeyResultAncestors;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Contracts\InheritsExtraFields;
use Platform\Core\Contracts\AgendaRenderable;

/**
 * @ai.description Aufgaben können optional einem Projekt zugeordnet sein (über ProjectSlot). Ohne Projekt sind es persönliche Aufgaben des Nutzers. TaskGroups und Slots dienen der Planung und Strukturierung der Arbeit.
 */
class PlannerTask extends Model implements HasKeyResultAncestors, HasDisplayName, InheritsExtraFields, AgendaRenderable
{
    use HasFactory, SoftDeletes, LogsActivity, HasTimeEntries, HasPlannedTime, HasTags, HasColors, Encryptable, HasExtraFields, TracksLastViewed;

    protected int $stalenessThresholdDays = 120;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'description',
        'dod',
        'due_date',
        'original_due_date',
        'postpone_count',
        'lifecycle_state',
        'lifecycle_state_changed_at',
        'lifecycle_state_reason',
        'is_frog',
        'is_forced_frog',
        'story_points',
        'order',
        'project_slot_order',
        'project_id',
        'project_slot_id',
        'task_group_id',
        'delegated_group_id',
        'delegated_group_order',
        'recurring_task_id',
        'agent_locked_at',
        'agent_locked_by',
        'agent_completed_at',
        'agent_summary',
        'agent_waiting_at',
        'agent_session_id',
        'triage_done_at',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'story_points' => TaskStoryPoints::class,
        'due_date' => 'datetime',
        'original_due_date' => 'datetime',
        'is_forced_frog' => 'boolean',
        'lifecycle_state' => \Platform\Planner\Enums\TaskLifecycleState::class,
        'lifecycle_state_changed_at' => 'datetime',
        'lifecycle_state_reason' => 'string',
        'agent_locked_at' => 'datetime',
        'agent_completed_at' => 'datetime',
        'agent_waiting_at' => 'datetime',
        'triage_done_at' => 'datetime',
        // Verschlüsselte Felder (description, dod) werden automatisch vom Encryptable Trait
        // in initializeEncryptable() hinzugefügt basierend auf $encryptable Array
    ];

    protected array $encryptable = [
        'description' => 'string',
        'dod' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {

            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;

            if (! $model->user_id) {
                $model->user_id = Auth::id();
            }

            if (! $model->team_id) {
                $model->team_id = Auth::user()->currentTeam->id;
            }
        });

        // Chain-on-Complete: wenn die letzte offene Instanz einer wiederkehrenden
        // Aufgabe erledigt wird, sofort die nächste anlegen (statt auf den Cron zu warten).
        static::updated(function (self $model) {
            if (! $model->recurring_task_id) return;
            if (! $model->wasChanged('lifecycle_state')) return;
            if ($model->lifecycle_state !== \Platform\Planner\Enums\TaskLifecycleState::COMPLETED) return;
            self::tryChainRecurring($model);
        });

        // Zuweisung → Eingang: wird die Aufgabe von jemand anderem AN MICH übergeben
        // (Ownership-Wechsel, Actor ≠ neuer Inhaber), landet sie im Posteingang des
        // neuen Inhabers. Jeder Handoff zurück = neues Item (kein dedupe).
        static::created(function (self $model) {
            self::notifyAssignment($model);
        });
        static::updated(function (self $model) {
            if ($model->wasChanged('user_in_charge_id')) {
                self::notifyAssignment($model);
            }
        });

        // Analog beim Löschen — der User räumt die letzte Instanz weg.
        static::deleting(function (self $model) {
            if (! $model->recurring_task_id) return;
            self::tryChainRecurring($model);
        });
    }

    /**
     * Versucht, die nächste Instanz einer wiederkehrenden Aufgabe sofort anzulegen.
     * Bedingungen:
     *   - Die Task ist mit einer aktiven Recurring-Vorlage verbunden.
     *   - Die Vorlage hat chain_on_complete = true.
     *   - End-Bedingungen (recurrence_end_date, max_occurrences) sind noch nicht erreicht.
     *   - Es gibt keine weitere offene Instanz dieser Vorlage (sonst kommt die nächste
     *     erst, wenn die letzte aus dem Weg ist).
     */
    /**
     * Push ein „dir zugewiesen"-Item in den Eingang des neuen Inhabers — nur wenn
     * ein *anderer* Nutzer zugewiesen hat (kein Self-Assign, kein System-Actor).
     * Loose/guarded: fehlt das Inbox-Modul, passiert nichts.
     */
    protected static function notifyAssignment(self $task): void
    {
        $assignee = $task->user_in_charge_id;
        $actor = Auth::id();

        if (! $assignee || ! $actor || (int) $assignee === (int) $actor) {
            return; // keine Zuweisung, kein Actor, oder Selbst-Zuweisung
        }
        if (! class_exists(\Platform\Inbox\Inbox::class)) {
            return; // Inbox nicht installiert → loose
        }

        try {
            $assigner = Auth::user();
            \Platform\Inbox\Inbox::deliver([
                'user_id'           => (int) $assignee,
                'team_id'           => (int) $task->team_id,
                'channel'           => 'task',
                'subject'           => $task->title ?: 'Aufgabe',
                'body'              => $task->description ?? null,
                'source'            => $task,
                'sender_kind'       => 'user',
                'sender_label'      => $assigner?->fullname ?? $assigner?->name ?? 'Jemand',
                'sender_identifier' => (string) $actor,
            ]);
        } catch (\Throwable $e) {
            // Zuweisung darf nie an der Inbox scheitern.
        }
    }

    protected static function tryChainRecurring(self $task): void
    {
        try {
            $recurring = $task->recurringTask()->first();
            if (! $recurring || ! $recurring->is_active || ! $recurring->chain_on_complete) {
                return;
            }

            if ($recurring->recurrence_end_date && now()->greaterThan($recurring->recurrence_end_date)) {
                return;
            }
            if ($recurring->max_occurrences !== null && $recurring->occurrences_count >= $recurring->max_occurrences) {
                return;
            }

            // Andere offene Instanzen? Dann nicht ketten — eine reicht.
            $openSiblings = $recurring->tasks()
                ->where('id', '!=', $task->id)
                ->where('lifecycle_state', \Platform\Planner\Enums\TaskLifecycleState::ACTIVE->value)
                ->whereNull('deleted_at')
                ->count();
            if ($openSiblings > 0) return;

            $recurring->createTask();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Scope: Nur Tasks, die der User sehen darf =
     * - Ersteller (user_id), ODER
     * - Zuständiger (user_in_charge_id), ODER
     * - Task gehört zu einem graph-erreichbaren Projekt (erbt dessen Verortung).
     */
    public function scopeVisibleTo(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        $reachable = app(\Platform\Core\Authz\AuthzResolver::class)->reachableEntityIds($user, 'read');
        $reachableProjectIds = empty($reachable) ? [] : \Illuminate\Support\Facades\DB::table('authz_resource_link')
            ->where('resource_type', \Platform\Planner\Models\PlannerProject::class)
            ->whereIn('scope_id', $reachable)
            ->pluck('resource_id')
            ->all();

        return $query->where(function ($q) use ($user, $reachableProjectIds) {
            $q->where('user_id', $user->id)
              ->orWhere('user_in_charge_id', $user->id)
              ->orWhereIn('project_id', $reachableProjectIds);
        });
    }

    public function setUserInChargeIdAttribute($value)
    {
        $this->attributes['user_in_charge_id'] = empty($value) || $value === 'null' ? null : (int)$value;
    }

    public function setDueDateAttribute($value)
    {
        if (empty($value) || $value === 'null') {
            $this->attributes['due_date'] = null;
            return;
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            $this->attributes['due_date'] = $value;
            return;
        }

        $this->attributes['due_date'] = \Carbon\Carbon::parse($value);
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function project()
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }

    public function taskGroup()
    {
        return $this->belongsTo(PlannerTaskGroup::class, 'task_group_id');
    }

    public function delegatedGroup()
    {
        return $this->belongsTo(PlannerDelegatedTaskGroup::class, 'delegated_group_id');
    }

    public function projectSlot()
    {
        return $this->belongsTo(PlannerProjectSlot::class, 'project_slot_id');
    }

    public function userInCharge()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_in_charge_id');
    }

    // ── Agent (Backoffice-Worker) ───────────────────────────────────────────
    // Ein Worker holt seine EXKLUSIV zugewiesenen (user_in_charge_id) aktiven
    // Tasks, sperrt sie kurz (Lock, 30-Min-Timeout gegen Worker-Crash) und meldet
    // Ergebnis/Rückfrage über agent_summary zurück.

    /** Tasks, die diesem User exklusiv zugewiesen sind. */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('user_in_charge_id', $userId);
    }

    /** Vom Agent holbar: aktiv, nicht (frisch) gesperrt, nicht auf Antwort wartend. */
    public function scopeAgentClaimable(Builder $query): Builder
    {
        return $query
            ->where('lifecycle_state', \Platform\Planner\Enums\TaskLifecycleState::ACTIVE->value)
            ->whereNull('agent_waiting_at')
            ->where(function ($q) {
                $q->whereNull('agent_locked_at')
                  ->orWhere('agent_locked_at', '<', now()->subMinutes(30));
            });
    }

    public function isAgentLocked(): bool
    {
        return $this->agent_locked_at !== null && $this->agent_locked_at >= now()->subMinutes(30);
    }

    /** Noch nicht auf Reife geprüft (Triage-Stufe steht aus). */
    public function scopeUntriaged(Builder $query): Builder
    {
        return $query->whereNull('triage_done_at');
    }

    /** Bereits triagiert = für die Ausführung freigegeben. */
    public function scopeTriaged(Builder $query): Builder
    {
        return $query->whereNotNull('triage_done_at');
    }

    // ── Abhängigkeiten (Finish-to-Start) ────────────────────────────────────
    // Zwei Werkzeuge, zwei Granularitäten: die Task-Kante (blocked_by, quer über
    // Slots/Projekte) und das Slot-Gate (Phasen-Sequenz, blocked_until_previous_done
    // am Slot). Beide sperren NUR die Ausführung (notBlocked) — die Triage darf
    // vorlaufen. Terminal (erledigt/verworfen) gibt einen Blocker frei.

    /** Vorgänger: Tasks, die zuerst terminal sein müssen, bevor dieser läuft. */
    public function blockers()
    {
        return $this->belongsToMany(
            self::class,
            'planner_task_dependencies',
            'blocked_task_id',
            'blocker_task_id'
        )->withTimestamps();
    }

    /** Nachfolger: Tasks, die auf DIESEN warten. */
    public function blocking()
    {
        return $this->belongsToMany(
            self::class,
            'planner_task_dependencies',
            'blocker_task_id',
            'blocked_task_id'
        )->withTimestamps();
    }

    /** Terminal-Zustände, die einen Blocker (Task oder Slot-Gate) freigeben. */
    protected static function terminalStates(): array
    {
        return [
            \Platform\Planner\Enums\TaskLifecycleState::COMPLETED->value,
            \Platform\Planner\Enums\TaskLifecycleState::DISCARDED->value,
        ];
    }

    /** Ausführbar bzgl. Abhängigkeiten: kein offener Task-Blocker UND Slot-Gate erfüllt. */
    public function scopeNotBlocked(Builder $query): Builder
    {
        return $query->notBlockedByTasks()->notBlockedBySlotGate();
    }

    /** Kein als Vorgänger verknüpfter Task ist noch offen (nicht terminal). */
    public function scopeNotBlockedByTasks(Builder $query): Builder
    {
        // Soft-gelöschte Blocker fallen durch den SoftDeletes-Scope der Relation
        // automatisch raus und blockieren damit nicht.
        return $query->whereDoesntHave('blockers', function ($q) {
            $q->whereNotIn('lifecycle_state', self::terminalStates());
        });
    }

    /**
     * Slot-Gate erfüllt: der Task ist in keinem gegateten Slot ODER alle Tasks in
     * Slots mit kleinerer `order` (selbes Projekt) sind terminal. Ohne Slot
     * (project_slot_id NULL) greift das Gate nie.
     */
    public function scopeNotBlockedBySlotGate(Builder $query): Builder
    {
        $terminal = self::terminalStates();

        // Query-Builder statt Raw-SQL: das Grammar quotet `order` je Treiber korrekt
        // (Postgres "order" / MySQL `order`) und bindet den Boolean dialekt-sicher.
        return $query->whereNotExists(function ($gate) use ($terminal) {
            $gate->selectRaw('1')
                ->from('planner_project_slots as gs')
                ->whereColumn('gs.id', 'planner_tasks.project_slot_id')
                ->where('gs.blocked_until_previous_done', true)
                ->whereExists(function ($earlier) use ($terminal) {
                    $earlier->selectRaw('1')
                        ->from('planner_tasks as pt')
                        ->join('planner_project_slots as ps', 'ps.id', '=', 'pt.project_slot_id')
                        ->whereColumn('pt.project_id', 'planner_tasks.project_id')
                        ->whereNull('pt.deleted_at')
                        ->whereNotIn('pt.lifecycle_state', $terminal)
                        ->whereColumn('ps.order', '<', 'gs.order');
                });
        });
    }

    /** Noch offene (nicht terminale) Vorgänger dieses Tasks. */
    public function openBlockers()
    {
        return $this->blockers()->whereNotIn('lifecycle_state', self::terminalStates());
    }

    /** Ist dieser Task aktuell durch eine Task-Kante blockiert? */
    public function isBlockedByTasks(): bool
    {
        return $this->openBlockers()->exists();
    }

    /** Ist dieser Task aktuell durch das Slot-Gate zurückgehalten? */
    public function isSlotGateBlocked(): bool
    {
        return $this->projectSlot?->isGateBlocked() ?? false;
    }

    /** Für UI: blockiert (Task-Kante ODER Slot-Gate)? */
    public function isBlocked(): bool
    {
        return $this->isBlockedByTasks() || $this->isSlotGateBlocked();
    }

    /**
     * Anzahl offener Vorgänger fürs Board-Badge — N+1-sicher: nutzt einen per
     * withCount('openBlockers') vorbereiteten Count bzw. eine bereits geladene
     * blockers-Relation. Ist nichts vorbereitet, wird NICHT nachgeladen (der
     * Board-Render bleibt query-frei) → 0.
     */
    public function getOpenBlockerCountAttribute(): int
    {
        if (array_key_exists('open_blockers_count', $this->attributes)) {
            return (int) $this->attributes['open_blockers_count'];
        }
        if ($this->relationLoaded('blockers')) {
            return $this->getRelation('blockers')
                ->reject(fn ($b) => $b->lifecycle_state?->isTerminal())
                ->count();
        }

        return 0;
    }

    /**
     * Würde eine Kante blocker → blocked einen Zyklus erzeugen? Wahr, wenn der
     * Vorgänger (blocker) bereits transitiv vom abhängigen Task (blocked) abhängt.
     */
    public static function wouldCreateDependencyCycle(int $blockerId, int $blockedId): bool
    {
        if ($blockerId === $blockedId) {
            return true;
        }

        $visited = [];
        $stack = [$blockerId];
        while ($stack) {
            $current = array_pop($stack);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            // Vorgänger von $current = Tasks, von denen $current abhängt.
            $parents = \Illuminate\Support\Facades\DB::table('planner_task_dependencies')
                ->where('blocked_task_id', $current)
                ->pluck('blocker_task_id');
            foreach ($parents as $parent) {
                if ((int) $parent === $blockedId) {
                    return true;
                }
                $stack[] = (int) $parent;
            }
        }

        return false;
    }

    /** Vorgänger verknüpfen (mit Selbst-/Zyklus-Schutz). */
    public function addBlocker(self $blocker): void
    {
        if ($blocker->id === $this->id) {
            throw new \InvalidArgumentException('Eine Aufgabe kann nicht von sich selbst abhängen.');
        }
        if (self::wouldCreateDependencyCycle($blocker->id, $this->id)) {
            throw new \InvalidArgumentException('Diese Abhängigkeit würde einen Zyklus erzeugen.');
        }
        $this->blockers()->syncWithoutDetaching([$blocker->id]);
    }

    /** Vorgänger-Verknüpfung lösen. */
    public function removeBlocker(self $blocker): void
    {
        $this->blockers()->detach($blocker->id);
    }

    /** Task auf erledigt setzen + Notiz des Workers hinterlegen. */
    public function agentComplete(?string $summary = null): void
    {
        $this->update([
            'lifecycle_state' => \Platform\Planner\Enums\TaskLifecycleState::COMPLETED,
            'lifecycle_state_changed_at' => now(),
            'lifecycle_state_reason' => 'Vom Worker erledigt',
            'agent_summary' => $summary,
            'agent_completed_at' => now(),
            'agent_locked_at' => null,
            'agent_locked_by' => null,
            'agent_waiting_at' => null,
            'agent_session_id' => null,
        ]);
        $this->logActivity("Worker hat diese Aufgabe erledigt." . ($summary ? "\n\n{$summary}" : ''), [
            'source' => 'agent', 'status' => 'completed',
        ]);
    }

    /**
     * Rückfrage: auf Antwort warten statt zurück-delegieren. Owner (user_in_charge_id)
     * bleibt der Worker; die Task geht in den Warten-Zustand (agent_waiting_at) und der
     * Claim überspringt sie, bis eine Antwort im Kontext-Thread steht. Die Claude-Session
     * wird gemerkt → die Antwort setzt sie per --resume fort (kein Ping-Pong).
     */
    public function agentWait(string $question, ?string $sessionId = null): void
    {
        $this->update([
            'agent_waiting_at' => now(),
            'agent_session_id' => $sessionId ?? $this->agent_session_id,
            'agent_summary' => 'RÜCKFRAGE: ' . $question,
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ]);
        $this->logActivity("Worker hat eine Rückfrage gestellt und wartet auf Antwort.\n\nFrage: {$question}", [
            'source' => 'agent', 'status' => 'deferred',
        ]);
    }

    /** Fehlgeschlagen: Notiz, Lock lösen — bleibt aktiv (Retry / menschliche Prüfung). */
    public function agentFail(string $error): void
    {
        $this->update([
            'agent_summary' => 'FAILED: ' . $error,
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ]);
        $this->logActivity("Worker konnte diese Aufgabe nicht erledigen.\n\nFehler: {$error}", [
            'source' => 'agent', 'status' => 'failed',
        ]);
    }

    public function agentUnlock(): void
    {
        $this->update(['agent_locked_at' => null, 'agent_locked_by' => null]);
    }

    public function recurringTask()
    {
        return $this->belongsTo(PlannerRecurringTask::class, 'recurring_task_id');
    }

    public function getLoggedMinutesAttribute(): int
    {
        return $this->totalLoggedMinutes();
    }

    /**
     * Gibt alle Vorfahren-Kontexte für die KeyResult-Kaskade zurück.
     * Task → Project (als Root)
     */
    public function keyResultAncestors(): array
    {
        $ancestors = [];

        // Projekt als Root-Kontext (bei Tasks ist das Project immer der Root)
        if ($this->project) {
            $ancestors[] = [
                'type' => get_class($this->project),
                'id' => $this->project->id,
                'is_root' => true, // Project ist Root-Kontext für Tasks
                'label' => $this->project->name,
            ];
        }

        return $ancestors;
    }

    /**
     * Gibt den anzeigbaren Namen/Titel der Task zurück.
     */
    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    /**
     * Prüft ob eine Task ein Backlog-Item ist
     *
     * Backlog-Aufgaben sind:
     * - Aufgaben mit Projekt-Bezug (project_id), aber ohne Slot (project_slot_id = null)
     * - Persönliche Aufgaben (kein project_id), aber ohne Task Group (task_group_id = null)
     *
     * @return bool
     */
    public function getIsBacklogAttribute(): bool
    {
        // Hat Projekt-Bezug, aber keinen Slot = Backlog
        if ($this->project_id && !$this->project_slot_id) {
            return true;
        }

        // Persönliche Aufgabe (kein Projekt), aber keine Task Group = Backlog
        if (!$this->project_id && !$this->task_group_id) {
            return true;
        }

        return false;
    }

    /**
     * Parst DoD-Wert und gibt ein Array von Items zurück.
     * Unterstützt sowohl das neue JSON-Format als auch das alte Plaintext-Format.
     *
     * @return array<int, array{text: string, checked: bool}>
     */
    public function getDodItemsAttribute(): array
    {
        $dod = $this->dod;

        if (empty($dod)) {
            return [];
        }

        // Versuche zuerst als JSON zu parsen (neues Format)
        $decoded = json_decode($dod, true);
        if (is_array($decoded) && !empty($decoded)) {
            $firstItem = reset($decoded);
            if (is_array($firstItem) && array_key_exists('text', $firstItem)) {
                return array_values(array_map(function ($item) {
                    return [
                        'text' => trim($item['text'] ?? ''),
                        'checked' => (bool)($item['checked'] ?? false),
                    ];
                }, $decoded));
            }
        }

        // Altes Format: Plaintext in Zeilen aufteilen
        $lines = preg_split('/\r\n|\r|\n/', $dod);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Prüfe auf Markdown-Checkbox-Format "- [ ] Text" oder "- [x] Text"
            if (preg_match('/^[-*]\s*\[([ xX])\]\s*(.+)$/', $line, $matches)) {
                $items[] = [
                    'text' => trim($matches[2]),
                    'checked' => strtolower($matches[1]) === 'x',
                ];
            }
            // Prüfe auf einfaches Listenformat "- Text" oder "* Text"
            elseif (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $items[] = [
                    'text' => trim($matches[1]),
                    'checked' => false,
                ];
            }
            // Einfacher Text ohne Format
            else {
                $items[] = [
                    'text' => $line,
                    'checked' => false,
                ];
            }
        }

        return $items;
    }

    /**
     * Gibt den DoD-Fortschritt zurück.
     *
     * @return array{total: int, checked: int, percentage: int, isComplete: bool}
     */
    public function getDodProgressAttribute(): array
    {
        $items = $this->dod_items;
        $total = count($items);
        $checked = count(array_filter($items, fn($item) => $item['checked']));

        return [
            'total' => $total,
            'checked' => $checked,
            'percentage' => $total > 0 ? round(($checked / $total) * 100) : 0,
            'isComplete' => $total > 0 && $checked === $total,
        ];
    }

    /**
     * Prüft ob die Task DoD-Items hat.
     */
    public function getHasDodAttribute(): bool
    {
        return !empty($this->dod_items);
    }

    /**
     * Parent-Models von denen Extra-Field-Definitionen geerbt werden.
     * Tasks erben Extra-Felder vom zugeordneten Projekt.
     */
    public function extraFieldParents(): array
    {
        return array_filter([$this->project]);
    }

    // ── AgendaRenderable ──────────────────────────────────────

    public function toAgendaItem(): array
    {
        $isCompleted = $this->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::COMPLETED;
        return [
            'title' => $this->title,
            'description' => $this->description ? \Illuminate\Support\Str::limit($this->description, 120) : null,
            'icon' => '✅',
            'color' => $this->color,
            'status' => $isCompleted ? 'Erledigt' : ($this->lifecycle_state?->label() ?? 'Offen'),
            'status_color' => $isCompleted ? 'green' : 'blue',
            'url' => route('planner.tasks.show', $this),
            'meta' => [
                'due_date' => $this->due_date?->toDateString(),
                'story_points' => $this->story_points?->value,
                'is_frog' => $this->is_frog,
            ],
        ];
    }
}