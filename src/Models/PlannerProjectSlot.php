<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description ProjectSlot verortet Aufgaben zeitlich/strukturell in einem Projekt (z. B. Sprint/Phase/Swimlane) und ermöglicht klare Zuordnung.
 */
class PlannerProjectSlot extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'color',
        'order',
        'blocked_until_previous_done',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'blocked_until_previous_done' => 'boolean',
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

    /**
     * Gate offen? Ein gegateter Slot hält seine Tasks zurück, solange ein
     * früherer Slot (kleinere `order`, selbes Projekt) noch offene Tasks hat.
     * Für UI-Badges gedacht; die Claim-Sperre lebt in PlannerTask::notBlocked.
     */
    public function isGateBlocked(): bool
    {
        if (! $this->blocked_until_previous_done) {
            return false;
        }

        $terminal = [
            \Platform\Planner\Enums\TaskLifecycleState::COMPLETED->value,
            \Platform\Planner\Enums\TaskLifecycleState::DISCARDED->value,
        ];

        return PlannerTask::query()
            ->where('project_id', $this->project_id)
            ->whereNotIn('lifecycle_state', $terminal)
            ->whereHas('projectSlot', fn ($s) => $s->where('order', '<', $this->order))
            ->exists();
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
