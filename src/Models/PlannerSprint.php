<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class PlannerSprint extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'start_date',
        'end_date',
        'order',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }

    public function sprintSlots(): HasMany
    {
        return $this->hasMany(PlannerSprintSlot::class, 'sprint_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }
}