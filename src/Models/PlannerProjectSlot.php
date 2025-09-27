<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

/**
 * PlannerProjectSlot Model
 * 
 * Repräsentiert einen Project Slot (auch als "slot" bezeichnet) in einem Projekt.
 * Project Slots sind Container für Tasks und organisieren die Arbeit in einem Projekt.
 * 
 * @hint Project Slots sind Container für Tasks in einem Projekt
 * @hint Slots organisieren die Arbeit in einem Projekt
 * @hint Jeder Slot gehört zu einem Projekt und kann Tasks enthalten
 * @hint Slots können Benutzern und Teams zugewiesen werden
 */
class PlannerProjectSlot extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'order',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
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

    /**
     * Tasks Relation
     * 
     * @hint Alle Tasks in einem Project Slot abrufen
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(PlannerTask::class, 'project_slot_id');
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
