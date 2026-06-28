<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Health\Traits\HasHealthSnapshotData;
use Symfony\Component\Uid\UuidV7;

class PlannerProjectSnapshot extends Model
{
    use HasHealthSnapshotData;

    protected $table = 'planner_project_snapshots';

    // Standard-Snapshot-Felder (uuid, taken_at/taken_on/trigger, team_id, health_*,
    // worst_axis, axis_scores, confidence_*, frozen_context, prev_snapshot_id,
    // delta_health_score, last_movement_at) kommen ueber HasHealthSnapshotData.
    protected $fillable = [
        'project_id',
        // Frozen Container-Context (Planner-spezifisch, NICHT im generischen frozen_context-JSON)
        'kind',
        'status',
        'color',
        // Tasks
        'tasks_total',
        'tasks_open',
        'tasks_done',
        'tasks_overdue',
        'tasks_frog',
        'tasks_postponed',
        // Story Points
        'story_points_total',
        'story_points_open',
        'story_points_done',
        // Zeit
        'minutes_planned',
        'minutes_logged',
        'minutes_billed',
        'minutes_unbilled',
        // Budget
        'budget_amount',
        'hourly_rate',
        'currency',
        'budget_used_euro',
        // Termine
        'planned_start',
        'planned_end',
        'days_to_planned_end',
        // Canvas
        'canvas_score',
        'canvas_color',
        'canvas_completeness_percent',
        'canvas_filled_blocks',
        'canvas_total_blocks',
        'canvas_risk_count',
        'canvas_overdue_milestones',
        // Planner-spezifische Deltas (delta_health_score kommt aus dem Trait)
        'delta_canvas_score',
        'delta_tasks_done',
    ];

    // Standard-Casts (taken_at, taken_on, last_movement_at, axis_scores, frozen_context)
    // kommen ueber HasHealthSnapshotData. Hier nur Planner-spezifische.
    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
        'budget_amount' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'budget_used_euro' => 'decimal:2',
        'canvas_completeness_percent' => 'decimal:2',
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

    // ── Relations ───────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prev_snapshot_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PlannerProjectSnapshotSlot::class, 'snapshot_id')
            ->orderBy('slot_order');
    }

    public function frogs(): HasMany
    {
        return $this->hasMany(PlannerProjectSnapshotFrog::class, 'snapshot_id')
            ->orderBy('rank');
    }

    public function people(): HasMany
    {
        return $this->hasMany(PlannerProjectSnapshotPerson::class, 'snapshot_id')
            ->orderByDesc('sp_open');
    }
}
