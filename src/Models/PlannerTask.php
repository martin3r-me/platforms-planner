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
use Platform\Media\Traits\HasMedia;
use Platform\Organization\Traits\HasTimeEntries;
use Platform\Core\Traits\HasTags;
use Platform\Core\Traits\HasColors;
use Platform\Core\Contracts\HasTimeAncestors;
use Platform\Core\Contracts\HasKeyResultAncestors;
use Platform\Core\Contracts\HasDisplayName;

/**
 * @ai.description Aufgaben können optional einem Projekt zugeordnet sein (über ProjectSlot). Ohne Projekt sind es persönliche Aufgaben des Nutzers. TaskGroups und Slots dienen der Planung und Strukturierung der Arbeit.
 */
class PlannerTask extends Model implements HasTimeAncestors, HasKeyResultAncestors, HasDisplayName
{
    use HasFactory, SoftDeletes, LogsActivity, HasMedia, HasTimeEntries, HasTags, HasColors;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'description',
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
}