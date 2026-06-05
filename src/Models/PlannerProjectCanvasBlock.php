<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class PlannerProjectCanvasBlock extends Model
{
    protected $table = 'planner_project_canvas_blocks';

    protected $fillable = [
        'uuid',
        'canvas_id',
        'block_type',
        'label',
        'position',
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
    }

    // Relationships

    public function canvas(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectCanvas::class, 'canvas_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PlannerProjectCanvasEntry::class, 'block_id')->orderBy('position');
    }

    // Scopes

    public function scopeByType($query, string $type)
    {
        return $query->where('block_type', $type);
    }

    /**
     * Get guiding questions for this block type from config.
     */
    public function getGuidingQuestions(): array
    {
        return config("planner.canvas_block_types.{$this->block_type}.guiding_questions", []);
    }
}
