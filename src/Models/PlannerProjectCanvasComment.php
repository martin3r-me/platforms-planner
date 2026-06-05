<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class PlannerProjectCanvasComment extends Model
{
    protected $table = 'planner_project_canvas_comments';

    protected $fillable = [
        'uuid',
        'canvas_id',
        'building_block_id',
        'parent_id',
        'content',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function canvas(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectCanvas::class, 'canvas_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectCanvasBlock::class, 'building_block_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function scopeRootComments(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
