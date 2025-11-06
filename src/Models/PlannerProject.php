<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;
use Platform\Core\Traits\HasTimeEntries;

/**
 * @ai.description Projekt bündelt Aufgaben (Tasks) und Sprints. Dient als Container für Planung, Ressourcen und Fortschritt eines Vorhabens im Team.
 */
class PlannerProject extends Model
{
    use HasTimeEntries;

    protected $fillable = [
        'uuid',
        'name',
        'order',
        'planned_minutes',
        'customer_cost_center',
        'user_id',
        'team_id',
        'project_type',
    ];

    protected $casts = [
        'uuid' => 'string',
        'project_type' => \Platform\Planner\Enums\ProjectType::class,
    ];

    protected static function booted(): void
    {
        Log::info('PlannerProject Model: booted() called!');
        
        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    /**
     * Project Slots Relation
     * 
     * @hint Alle Project Slots eines Projekts abrufen (enthält Tasks)
     */
    public function projectSlots(): HasMany
    {
        return $this->hasMany(PlannerProjectSlot::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function projectUsers()
    {
        return $this->hasMany(PlannerProjectUser::class, 'project_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function customerProject()
    {
        return $this->hasOne(\Platform\Planner\Models\PlannerCustomerProject::class, 'project_id');
    }

    /**
     * Tasks Relation
     * 
     * @hint Alle Tasks eines Projekts abrufen (direkt über project_id)
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(PlannerTask::class, 'project_id');
    }

    public function getLoggedMinutesAttribute(): int
    {
        return $this->totalLoggedMinutes();
    }
}