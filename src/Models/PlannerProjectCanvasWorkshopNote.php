<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

class PlannerProjectCanvasWorkshopNote extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'planner_project_canvas_workshop_notes';

    protected $fillable = [
        'uuid',
        'canvas_id',
        'building_block_id',
        'title',
        'content',
        'color',
        'type',
        'position_x',
        'position_y',
        'width',
        'height',
        'metadata',
        'created_by_user_id',
    ];

    protected $casts = [
        'position_x' => 'float',
        'position_y' => 'float',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
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

    public function canvas(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectCanvas::class, 'canvas_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectCanvasBlock::class, 'building_block_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public static function allowedColors(): array
    {
        return ['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'teal', 'red'];
    }

    public static function allowedTypes(): array
    {
        return ['note', 'text', 'section', 'shape', 'connector', 'kanban', 'image', 'image_grid', 'video'];
    }

    public static function allowedShapes(): array
    {
        return ['rect', 'circle', 'diamond'];
    }
}
