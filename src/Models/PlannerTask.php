<?php

namespace Platform\Planner\Models;

use Platform\Planner\Enums\TaskPriority;
use Platform\Planner\Enums\TaskStoryPoints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Organization\Traits\HasTimeEntries;
use Platform\Core\Traits\HasTags;
use Platform\Core\Traits\HasColors;
use Platform\Core\Traits\Encryptable;
use Platform\Core\Contracts\HasTimeAncestors;
use Platform\Core\Contracts\HasKeyResultAncestors;
use Platform\Core\Contracts\HasDisplayName;

/**
 * @ai.description Aufgaben können optional einem Projekt zugeordnet sein (über ProjectSlot). Ohne Projekt sind es persönliche Aufgaben des Nutzers. TaskGroups und Slots dienen der Planung und Strukturierung der Arbeit.
 */
class PlannerTask extends Model implements HasTimeAncestors, HasKeyResultAncestors, HasDisplayName
{
    use HasFactory, SoftDeletes, LogsActivity, HasTimeEntries, HasTags, HasColors, Encryptable;

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
        'planned_minutes',
        'status',
        'is_done',
        'done_at',
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
        'done_at' => 'datetime',
        'is_forced_frog' => 'boolean',
        // Verschlüsselte Felder werden automatisch vom Encryptable Trait hinzugefügt
        // aber wir setzen sie hier explizit, um sicherzustellen, dass sie funktionieren
        'description' => \Platform\Core\Casts\EncryptedString::class,
        'dod' => \Platform\Core\Casts\EncryptedString::class,
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
     * Gibt alle Vorfahren-Kontexte für die Zeitkaskade zurück.
     * Task → Project (als Root)
     */
    public function timeAncestors(): array
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
}