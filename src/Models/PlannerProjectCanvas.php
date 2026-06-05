<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

class PlannerProjectCanvas extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'planner_project_canvases';

    protected $fillable = [
        'uuid',
        'project_id',
        'team_id',
        'name',
        'description',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'status' => 'string',
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
