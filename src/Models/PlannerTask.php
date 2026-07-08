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
     * Scope: Nur Tasks, die der User laut Policy sehen darf.
     * - User ist Owner (user_id)
     * - User ist Verantwortlicher (user_in_charge_id)
     * - Task gehört zu einem Projekt, in dem der User Mitglied ist
     */
    public function scopeVisibleTo(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        $memberProjectIds = PlannerProjectUser::where('user_id', $user->id)->pluck('project_id');

        return $query->where(function ($q) use ($user, $memberProjectIds) {
            $q->where('user_id', $user->id)
              ->orWhere('user_in_charge_id', $user->id)
              ->orWhereIn('project_id', $memberProjectIds);
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