<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;
use Platform\Organization\Traits\HasTimeEntries;
use Platform\Organization\Traits\HasOrganizationContexts;
use Platform\Core\Contracts\HasTimeAncestors;
use Platform\Core\Contracts\HasDisplayName;

/**
 * @ai.description Projekt bündelt Aufgaben (Tasks) und Sprints. Dient als Container für Planung, Ressourcen und Fortschritt eines Vorhabens im Team.
 */
class PlannerProject extends Model implements HasTimeAncestors, HasDisplayName
{
    use HasTimeEntries, HasOrganizationContexts;

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

    /**
     * Gibt alle Vorfahren-Kontexte für die Zeitkaskade zurück.
     * Project → Project selbst (als Root)
     * 
     * Wenn direkt auf Project-Level Zeit erfasst wird, ist das Project selbst der Root-Kontext.
     */
    public function timeAncestors(): array
    {
        // Bei Projects ist das Project selbst der Root-Kontext
        // Wir geben ein leeres Array zurück, da das Project selbst bereits als context_type/context_id gesetzt ist
        // und in StoreTimeEntry wird das Project dann als root_context gesetzt, wenn keine Ancestors vorhanden sind
        return [];
    }

    /**
     * Gibt den anzeigbaren Namen des Projects zurück.
     */
    public function getDisplayName(): ?string
    {
        return $this->name;
    }
}