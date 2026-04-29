<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Builder;
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
use Platform\Core\Models\Concerns\HasEntityLinks;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Contracts\HasKeyResultAncestors;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Contracts\AgendaRenderable;
use Platform\Planner\Enums\CustomerBillingMethod;

/**
 * @ai.description Projekt bündelt Aufgaben (Tasks) und Sprints. Dient als Container für Planung, Ressourcen und Fortschritt eines Vorhabens im Team.
 */
class PlannerProject extends Model implements HasKeyResultAncestors, HasDisplayName, AgendaRenderable
{
    use HasTimeEntries, HasOrganizationContexts, HasColors, HasTags, HasExtraFields, HasEntityLinks, LogsActivity;

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
        'public_token',
        'is_public',
        'public_token_expires_at',
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
        'is_public' => 'boolean',
        'public_token_expires_at' => 'datetime',
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

    /**
     * Scope: Nur Projekte, die der User laut Policy sehen darf.
     * - User ist Owner des Projekts
     * - User ist Mitglied des Projekts (project_users)
     */
    public function scopeVisibleTo(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        $memberProjectIds = PlannerProjectUser::where('user_id', $user->id)->pluck('project_id');

        return $query->where(function ($q) use ($user, $memberProjectIds) {
            $q->where('user_id', $user->id)
              ->orWhereIn('id', $memberProjectIds);
        });
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

    // ── AgendaRenderable ──────────────────────────────────────

    // ── Public Sharing ─────────────────────────────────────

    public function generatePublicToken(): void
    {
        $this->public_token = bin2hex(random_bytes(16));
        $this->is_public = true;
        $this->save();
    }

    public function revokePublicToken(): void
    {
        $this->public_token = null;
        $this->is_public = false;
        $this->public_token_expires_at = null;
        $this->save();
    }

    public function isPublicAccessible(): bool
    {
        if (!$this->is_public || !$this->public_token) {
            return false;
        }

        if ($this->public_token_expires_at && $this->public_token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getPublicUrl(): ?string
    {
        if (!$this->public_token) {
            return null;
        }

        return route('planner.public.show', $this->public_token);
    }

    // ── AgendaRenderable ──────────────────────────────────────

    public function toAgendaItem(): array
    {
        return [
            'title' => $this->name,
            'description' => $this->description ? \Illuminate\Support\Str::limit($this->description, 120) : null,
            'icon' => '📁',
            'color' => $this->color,
            'status' => $this->done ? 'Erledigt' : 'Offen',
            'status_color' => $this->done ? 'green' : 'blue',
            'url' => route('planner.projects.show', $this),
            'meta' => ['project_type' => $this->project_type?->value],
        ];
    }
}