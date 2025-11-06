<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\UuidV7;

class PlannerTimeEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'project_id',
        'task_id',
        'work_date',
        'minutes',
        'rate_cents',
        'amount_cents',
        'is_billed',
        'currency_code',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'minutes' => 'integer',
        'rate_cents' => 'integer',
        'amount_cents' => 'integer',
        'is_billed' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $entry->uuid = $uuid;

            if (! $entry->team_id && Auth::user()?->currentTeam) {
                $entry->team_id = Auth::user()->currentTeam->id;
            }

            if (! $entry->currency_code) {
                $entry->currency_code = 'EUR';
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlannerProject::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PlannerTask::class);
    }

    public function getHoursAttribute(): float
    {
        return round(($this->minutes ?? 0) / 60, 2);
    }
}


