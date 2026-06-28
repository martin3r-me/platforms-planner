<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannerProjectSnapshotPerson extends Model
{
    protected $table = 'planner_project_snapshot_people';

    protected $fillable = [
        'snapshot_id',
        'user_id',
        'user_name',
        'open_tasks',
        'done_tasks',
        'sp_open',
        'sp_done',
        'overdue_tasks',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PlannerProjectSnapshot::class, 'snapshot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }
}
