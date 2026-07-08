<?php

namespace Platform\Planner\Services;

use Illuminate\Support\Facades\Log;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\DimensionLinkService;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasBlock;
use Platform\Planner\Models\PlannerTask;

/**
 * Wakes dormant projects up on incoming activity signals.
 *
 * The daily LifecycleTickCommand handles the "going quiet" direction.
 * This reactivator handles the reverse: as soon as work happens on a
 * project (a task edit, a time booking, a canvas edit, a view), a
 * dormant project flips back to active immediately.
 *
 * Wired up via model events in PlannerServiceProvider::boot().
 * Silently ignores non-dormant projects — no guard-exception noise.
 */
class LifecycleReactivator
{
    public function __construct(
        protected LifecycleService $lifecycle,
    ) {}

    // ── Entry points, called from model event hooks ──────────────

    public function onProjectUpdated(PlannerProject $project): void
    {
        // Only react when a meaningful field changed. If our own lifecycle
        // machinery caused the update, or the update was only lifecycle-related,
        // do nothing — otherwise we get a self-triggering loop.
        if ($project->wasChanged('lifecycle_state')
            || $project->wasChanged('lifecycle_state_changed_at')
            || $project->wasChanged('lifecycle_state_reason')
        ) {
            // If the ONLY changed columns are lifecycle-* columns, skip.
            $changed = array_keys($project->getChanges());
            $lifecycleCols = ['lifecycle_state', 'lifecycle_state_changed_at', 'lifecycle_state_reason'];
            if (empty(array_diff($changed, $lifecycleCols))) {
                return;
            }
        }
        $this->reactivate($project);
    }

    public function onTaskSaved(PlannerTask $task): void
    {
        if (! $task->project_id) {
            return; // Standalone task — nothing to reactivate.
        }
        $project = PlannerProject::find($task->project_id);
        if ($project) {
            $this->reactivate($project);
        }
    }

    public function onCanvasBlockSaved(PlannerProjectCanvasBlock $block): void
    {
        $canvas = PlannerProjectCanvas::find($block->canvas_id);
        if (! $canvas || ! $canvas->project_id) {
            return;
        }
        $project = PlannerProject::find($canvas->project_id);
        if ($project) {
            $this->reactivate($project);
        }
    }

    public function onTimeEntryCreated(OrganizationTimeEntry $entry): void
    {
        $project = $this->resolveProjectFromContext($entry->context_type, (int) $entry->context_id);
        if ($project) {
            $this->reactivate($project);
        }
    }

    // ── Internals ────────────────────────────────────────────────

    protected function reactivate(PlannerProject $project): void
    {
        if ($project->lifecycle_state !== ProjectLifecycleState::DORMANT) {
            return;
        }
        try {
            $this->lifecycle->autoReactivate($project);
        } catch (\Throwable $e) {
            // Do not break the originating save if reactivation fails —
            // just log and move on.
            Log::warning('planner.lifecycle.reactivate failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the parent project of a time entry, whether it was booked
     * directly on a project or on one of its tasks. Handles both morph
     * alias and full class name variants.
     */
    protected function resolveProjectFromContext(?string $contextType, int $contextId): ?PlannerProject
    {
        if (! $contextType || ! $contextId) {
            return null;
        }

        $projectAlias = DimensionLinkService::resolveContextType(PlannerProject::class);
        $taskAlias = DimensionLinkService::resolveContextType(PlannerTask::class);

        if ($contextType === $projectAlias || $contextType === PlannerProject::class) {
            return PlannerProject::find($contextId);
        }
        if ($contextType === $taskAlias || $contextType === PlannerTask::class) {
            $task = PlannerTask::find($contextId);
            if ($task && $task->project_id) {
                return PlannerProject::find($task->project_id);
            }
        }
        return null;
    }
}
