<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;

/**
 * PlannerProject Model
 * 
 * Repräsentiert ein Projekt im Planner-Modul.
 * Projekte haben Project Slots (auch als "slots" bezeichnet), die Tasks enthalten.
 * 
 * @hint Project Slots sind Container für Tasks in einem Projekt
 * @hint Projekte haben Sprints und Project Slots
 * @hint Jedes Projekt gehört zu einem Team und einem User
 * @hint Projekte können Kunden-Projekte sein
 */
class PlannerProject extends Model
{

    protected $fillable = [
        'uuid',
        'name',
        'order',
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
}