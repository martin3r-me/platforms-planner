<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannerProjectSnapshotFrog extends Model
{
    protected $table = 'planner_project_snapshot_frogs';

    protected $fillable = [
        'snapshot_id',
        'task_id',
        'task_uuid',
        'task_title',
        'due_date',
        'is_overdue',
        'postpone_count',
        'story_points',
        'rank',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'is_overdue' => 'boolean',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectSnapshot::class, 'snapshot_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PlannerTask::class, 'task_id');
    }
}
