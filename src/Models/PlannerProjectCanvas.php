<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class PlannerProjectCanvas extends Model
{
    use LogsActivity, SoftDeletes;

    // Visibility constants
    public const VISIBILITY_TEAM = 'team';
    public const VISIBILITY_PRIVATE = 'private';

    // Status constants (aligned with Canvas module)
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DISCARDED = 'discarded';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_COMPLETED,
        self::STATUS_DISCARDED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
    ];

    public const DONE_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_DISCARDED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Offen',
        self::STATUS_COMPLETED => 'Abgeschlossen',
        self::STATUS_DISCARDED => 'Verworfen',
    ];

    public const STATUS_ICONS = [
        self::STATUS_OPEN => 'heroicon-o-pencil-square',
        self::STATUS_COMPLETED => 'heroicon-o-check-circle',
        self::STATUS_DISCARDED => 'heroicon-o-x-circle',
    ];

    public const STATUS_VARIANTS = [
        self::STATUS_OPEN => 'primary',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_DISCARDED => 'secondary',
    ];

    protected $table = 'planner_project_canvases';

    protected $fillable = [
        'uuid',
        'project_id',
        'team_id',
        'name',
        'description',
        'status',
        'visibility',
        'public_token',
        'is_public',
        'workshop_settings',
        'created_by_user_id',
    ];

    protected $casts = [
        'status' => 'string',
        'is_public' => 'boolean',
        'workshop_settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });

        // Soft-Delete Cascade: Entries mit-soft-deleten
        static::deleting(function (self $canvas) {
            if ($canvas->isForceDeleting()) {
                return;
            }
            $canvas->entries()->each(fn (PlannerProjectCanvasEntry $entry) => $entry->delete());
        });

        static::restoring(function (self $canvas) {
            $canvas->entries()->onlyTrashed()->each(fn (PlannerProjectCanvasEntry $entry) => $entry->restore());
        });
    }

    // Relationships

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PlannerProjectCanvasBlock::class, 'canvas_id')->orderBy('position');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PlannerProjectCanvasSnapshot::class, 'canvas_id')->orderBy('version', 'desc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PlannerProjectCanvasComment::class, 'canvas_id');
    }

    public function workshopNotes(): HasMany
    {
        return $this->hasMany(PlannerProjectCanvasWorkshopNote::class, 'canvas_id');
    }

    /**
     * All entries across all blocks (for soft-delete cascade).
     */
    public function entries(): HasMany
    {
        return $this->hasManyThrough(
            PlannerProjectCanvasEntry::class,
            PlannerProjectCanvasBlock::class,
            'canvas_id',
            'block_id',
        );
    }

    // Scopes

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', self::VISIBILITY_TEAM)
              ->orWhere('created_by_user_id', $user->id);
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->visibility === self::VISIBILITY_TEAM
            || $this->created_by_user_id === $user->id;
    }

    // Public Link

    public function generatePublicToken(): string
    {
        $this->public_token = bin2hex(random_bytes(16));
        $this->is_public = true;
        $this->save();

        return $this->public_token;
    }

    public function getPublicUrl(): ?string
    {
        if (! $this->public_token) {
            return null;
        }

        // Generic URL — public route can be added later
        return url("/planner/projects/{$this->project_id}/canvas/{$this->id}/public/{$this->public_token}");
    }

    // Status helpers

    public function close(?string $reason = 'completed'): void
    {
        $status = $reason === 'discarded' ? self::STATUS_DISCARDED : self::STATUS_COMPLETED;
        $this->update(['status' => $status]);
    }

    public function reopen(): void
    {
        $this->update(['status' => self::STATUS_OPEN]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::DONE_STATUSES);
    }

    /**
     * Initialize the 9 Project Canvas building blocks from config.
     */
    public function initializeBlocks(): void
    {
        $blockTypes = config('planner.canvas_block_types', []);

        foreach ($blockTypes as $type => $definition) {
            $this->blocks()->create([
                'block_type' => $type,
                'label' => $definition['label'],
                'position' => $definition['position'],
            ]);
        }
    }

    /**
     * Export the full canvas data as an array.
     */
    public function toCanvasArray(): array
    {
        $this->loadMissing(['blocks.entries']);

        $blocks = [];
        foreach ($this->blocks as $block) {
            $blocks[$block->block_type] = [
                'id' => $block->id,
                'label' => $block->label,
                'position' => $block->position,
                'entries' => $block->entries->map(fn (PlannerProjectCanvasEntry $e) => [
                    'id' => $e->id,
                    'uuid' => $e->uuid,
                    'title' => $e->title,
                    'content' => $e->content,
                    'entry_type' => $e->entry_type,
                    'position' => $e->position,
                    'metadata' => $e->metadata,
                ])->values()->toArray(),
            ];
        }

        return [
            'canvas' => [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'name' => $this->name,
                'description' => $this->description,
                'status' => $this->status,
                'project_id' => $this->project_id,
                'team_id' => $this->team_id,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ],
            'blocks' => $blocks,
        ];
    }
}
