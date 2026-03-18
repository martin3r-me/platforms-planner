<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Log;
use Platform\Organization\Traits\HasTimeEntries;
use Platform\Organization\Traits\HasOrganizationContexts;
use Platform\Core\Traits\HasColors;
use Platform\Core\Traits\HasTags;
use Platform\Core\Traits\HasExtraFields;
use Platform\Core\Contracts\HasTimeAncestors;
use Platform\Core\Contracts\HasKeyResultAncestors;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Planner\Enums\CustomerBillingMethod;

/**
 * @ai.description Projekt bündelt Aufgaben (Tasks) und Sprints. Dient als Container für Planung, Ressourcen und Fortschritt eines Vorhabens im Team.
 */
class PlannerProject extends Model implements HasTimeAncestors, HasKeyResultAncestors, HasDisplayName
{
    use HasTimeEntries, HasOrganizationContexts, HasColors, HasTags, HasExtraFields;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'order',
        'planned_minutes',
        'planned_end',
        'estimated_hours',
        'customer_cost_center',
        'user_id',
        'team_id',
        'project_type',
        'billing_method',
        'hourly_rate',
        'budget_amount',
        'currency',
        'done',
        'done_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'project_type' => \Platform\Planner\Enums\ProjectType::class,
        'billing_method' => CustomerBillingMethod::class,
        'hourly_rate' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'planned_end' => 'date',
        'estimated_hours' => 'decimal:2',
        'done' => 'boolean',
        'done_at' => 'datetime',
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

    /**
     * @deprecated Verwende billing-Felder direkt am PlannerProject + entityLinks() statt CRM-Verknüpfung.
     */
    public function customerProject(): HasOne
    {
        return $this->hasOne(\Platform\Planner\Models\PlannerCustomerProject::class, 'project_id');
    }

    /**
     * Billing Items direkt am Projekt
     */
    public function billingItems(): HasMany
    {
        return $this->hasMany(PlannerProjectBillingItem::class, 'project_id');
    }

    /**
     * Entity-Links (OrganizationEntityLink) über morphMany
     */
    public function entityLinks(): MorphMany
    {
        return $this->morphMany(\Platform\Organization\Models\OrganizationEntityLink::class, 'linkable');
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
     * Gibt alle Vorfahren-Kontexte für die KeyResult-Kaskade zurück.
     * Project → Project selbst (als Root)
     * 
     * Wenn direkt auf Project-Level ein KeyResult verknüpft wird, ist das Project selbst der Root-Kontext.
     */
    public function keyResultAncestors(): array
    {
        // Bei Projects ist das Project selbst der Root-Kontext
        // Wir geben ein leeres Array zurück, da das Project selbst bereits als context_type/context_id gesetzt ist
        // und in StoreKeyResultContext wird das Project dann als root_context gesetzt, wenn keine Ancestors vorhanden sind
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