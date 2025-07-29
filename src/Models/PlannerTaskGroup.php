<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;

class PlannerTaskGroup extends Model
{
    protected $fillable = ['label', 'order', 'user_id', 'team_id'];

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

    public function tasks()
    {
        return $this->hasMany(PlannerTask::class, 'task_group_id');
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }
}
