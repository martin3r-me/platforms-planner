<?php

namespace Platform\Planner\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Services\ProjectSnapshotService;

/**
 * GET den letzten Snapshot eines Projekts inkl. Sub-Daten (slots/frogs/people).
 * Optional taken_on=YYYY-MM-DD um einen historischen Snapshot zu holen.
 * Optional fresh=true erzwingt einen neuen Snapshot (manueller Trigger).
 */
class GetProjectSnapshotTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project_snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /project-snapshots - Holt den juengsten Snapshot eines Projekts (oder einen historischen via taken_on) inkl. Health-Ampel, Score, Confidence, Sub-Daten (Slot-Breakdown, Top-5-Froesche, Person-Workload). Optional fresh=true erzwingt einen neuen Snapshot (manueller Trigger).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Projekt-ID (ERFORDERLICH). Nutze planner.projects.GET um Projekte zu finden.',
                ],
                'taken_on' => [
                    'type' => 'string',
                    'description' => 'Optional: historischer Stichtag im Format YYYY-MM-DD. Default: juengster Snapshot.',
                ],
                'fresh' => [
                    'type' => 'boolean',
                    'description' => 'Optional: wenn true wird ein neuer Snapshot erstellt (Trigger=manual) und zurueckgegeben.',
                ],
            ],
            'required' => ['project_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            if (empty($arguments['project_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'project_id ist erforderlich.');
            }

            $project = PlannerProject::find($arguments['project_id']);
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('view', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Lesezugriff auf das Projekt.');
            }

            if (!empty($arguments['fresh'])) {
                $snapshot = app(ProjectSnapshotService::class)->snapshot($project, 'manual');
            } else {
                $query = PlannerProjectSnapshot::where('project_id', $project->id);
                if (!empty($arguments['taken_on'])) {
                    $query->whereDate('taken_on', $arguments['taken_on']);
                }
                $snapshot = $query->orderByDesc('taken_on')->first();
            }

            if (!$snapshot) {
                return ToolResult::success([
                    'project_id' => $project->id,
                    'snapshot' => null,
                    'message' => 'Noch kein Snapshot vorhanden. Setze fresh=true um den ersten zu erstellen.',
                ]);
            }

            $snapshot->load(['slots', 'frogs', 'people']);

            return ToolResult::success([
                'project_id' => $project->id,
                'project_title' => $project->title,
                'snapshot' => $this->serializeSnapshot($snapshot),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public static function serializeSnapshot(PlannerProjectSnapshot $s): array
    {
        return [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'taken_at' => $s->taken_at?->toIso8601String(),
            'taken_on' => $s->taken_on?->toDateString(),
            'trigger' => $s->trigger,
            'frozen_context' => [
                'team_id' => $s->team_id,
                'kind' => $s->kind,
                'status' => $s->status,
                'color' => $s->color,
            ],
            'tasks' => [
                'total' => $s->tasks_total,
                'open' => $s->tasks_open,
                'done' => $s->tasks_done,
                'overdue' => $s->tasks_overdue,
                'frog' => $s->tasks_frog,
                'postponed' => $s->tasks_postponed,
            ],
            'story_points' => [
                'total' => $s->story_points_total,
                'open' => $s->story_points_open,
                'done' => $s->story_points_done,
            ],
            'minutes' => [
                'planned' => $s->minutes_planned,
                'logged' => $s->minutes_logged,
                'billed' => $s->minutes_billed,
                'unbilled' => $s->minutes_unbilled,
            ],
            'budget' => [
                'amount' => $s->budget_amount ? (float) $s->budget_amount : null,
                'hourly_rate' => $s->hourly_rate ? (float) $s->hourly_rate : null,
                'currency' => $s->currency,
                'used_euro' => $s->budget_used_euro ? (float) $s->budget_used_euro : null,
            ],
            'schedule' => [
                'planned_start' => $s->planned_start?->toDateString(),
                'planned_end' => $s->planned_end?->toDateString(),
                'days_to_planned_end' => $s->days_to_planned_end,
            ],
            'canvas' => [
                'score' => $s->canvas_score,
                'color' => $s->canvas_color,
                'completeness_percent' => $s->canvas_completeness_percent ? (float) $s->canvas_completeness_percent : null,
                'filled_blocks' => $s->canvas_filled_blocks,
                'total_blocks' => $s->canvas_total_blocks,
                'risk_count' => $s->canvas_risk_count,
                'overdue_milestones' => $s->canvas_overdue_milestones,
            ],
            'health' => [
                'score' => $s->health_score,
                'color' => $s->health_color,
            ],
            'confidence' => [
                'score' => $s->confidence_score,
                'reason' => $s->confidence_reason,
            ],
            'movement' => [
                'prev_snapshot_id' => $s->prev_snapshot_id,
                'delta_health_score' => $s->delta_health_score,
                'delta_canvas_score' => $s->delta_canvas_score,
                'delta_tasks_done' => $s->delta_tasks_done,
                'last_movement_at' => $s->last_movement_at?->toIso8601String(),
            ],
            'slots' => $s->slots->map(fn ($x) => [
                'slot_id' => $x->slot_id,
                'name' => $x->slot_name,
                'order' => $x->slot_order,
                'open' => $x->open_tasks,
                'done' => $x->done_tasks,
                'total' => $x->total_tasks,
            ])->all(),
            'frogs' => $s->frogs->map(fn ($x) => [
                'task_id' => $x->task_id,
                'task_uuid' => $x->task_uuid,
                'title' => $x->task_title,
                'due_date' => $x->due_date?->toIso8601String(),
                'is_overdue' => $x->is_overdue,
                'postpone_count' => $x->postpone_count,
                'story_points' => $x->story_points,
                'rank' => $x->rank,
            ])->all(),
            'people' => $s->people->map(fn ($x) => [
                'user_id' => $x->user_id,
                'name' => $x->user_name,
                'open_tasks' => $x->open_tasks,
                'done_tasks' => $x->done_tasks,
                'sp_open' => $x->sp_open,
                'sp_done' => $x->sp_done,
                'overdue_tasks' => $x->overdue_tasks,
            ])->all(),
        ];
    }
}
