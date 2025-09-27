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

/**
 * PlannerTask Model
 * 
 * Repräsentiert eine Task (Aufgabe) im Planner-Modul.
 * Tasks können in Project Slots, Sprints oder Task Groups organisiert werden.
 * 
 * @hint Tasks sind Aufgaben, die in Project Slots organisiert werden
 * @hint Tasks haben Prioritäten, Story Points und Due Dates
 * @hint Tasks können Benutzern und Teams zugewiesen werden
 * @hint Tasks können in Sprints oder Task Groups organisiert werden
 */
class PlannerTask extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, HasMedia;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'description',
        'due_date',
        'status',
        'is_done',
        'is_frog',
        'story_points',
        'order',
        'project_slot_order',
        'project_id',
        'project_slot_id',
        'task_group_id',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'story_points' => TaskStoryPoints::class,
        'due_date' => 'date'
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
        $this->attributes['due_date'] = empty($value) || $value === 'null' ? null : (int)$value;
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
}