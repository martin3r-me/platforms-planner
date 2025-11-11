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
use Platform\Core\Contracts\HasTimeAncestors;
use Platform\Core\Contracts\HasDisplayName;

/**
 * @ai.description Aufgaben können optional einem Projekt zugeordnet sein (über ProjectSlot). Ohne Projekt sind es persönliche Aufgaben des Nutzers. TaskGroups und Slots dienen der Planung und Strukturierung der Arbeit.
 */
class PlannerTask extends Model implements HasTimeAncestors, HasDisplayName
{
    use HasFactory, SoftDeletes, LogsActivity, HasMedia, HasTimeEntries;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'description',
        'due_date',
        'planned_minutes',
        'status',
        'is_done',
        'is_frog',
        'story_points',
        'order',
        'project_slot_order',
        'project_id',
        'project_slot_id',
        'task_group_id',
        'recurring_task_id',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'story_points' => TaskStoryPoints::class,
        'due_date' => 'datetime'
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
     * Gibt den anzeigbaren Namen/Titel der Task zurück.
     */
    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}