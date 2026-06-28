<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannerProjectSnapshotSlot extends Model
{
    protected $table = 'planner_project_snapshot_slots';

    protected $fillable = [
        'snapshot_id',
        'slot_id',
        'slot_name',
        'slot_order',
        'open_tasks',
        'done_tasks',
        'total_tasks',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectSnapshot::class, 'snapshot_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectSlot::class, 'slot_id');
    }
}
