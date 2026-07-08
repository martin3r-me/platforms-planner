<?php

namespace Platform\Planner\Services;

use Illuminate\Support\Facades\DB;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Exceptions\InvalidLifecycleTransitionException;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

/**
 * Applies lifecycle transitions on projects and tasks.
 *
 * Responsibilities:
 *   - Enforce which transitions are valid from the current state.
 *   - Update `lifecycle_state`, `lifecycle_state_changed_at`, and
 *     `lifecycle_state_reason` atomically.
 *   - Cascade project discard onto still-active tasks.
 *
 * Explicit non-responsibilities:
 *   - Authorization. Callers (Livewire, controllers, MCP endpoints) decide
 *     who is allowed to invoke complete/discard/reopen/revive. The service
 *     will happily transition anything valid — think of it as the "engine",
 *     not the "policy".
 *   - Events / notifications. Callers can fan out after a successful call.
 *
 * Reason codes follow the pattern `<origin>:<verb>`:
 *   manual:complete, manual:discard, manual:reopen, manual:revive
 *   auto:inactivity, auto:activity
 *   cascade:project_discarded
 */
class LifecycleService
{
    // ── Project transitions ──────────────────────────────────────

    /**
     * Mark project as completed (goal reached).
     * Allowed from: active, dormant.
     */
    public function complete(PlannerProject $project): void
    {
        $this->guardProjectTransition($project, ProjectLifecycleState::COMPLETED, [
            ProjectLifecycleState::ACTIVE,
            ProjectLifecycleState::DORMANT,
        ]);
        $this->transitionProject($project, ProjectLifecycleState::COMPLETED, 'manual:complete');
    }

    /**
     * Discard project (ended without result). Cascades onto still-active tasks.
     * Allowed from: active, dormant.
     */
    public function discard(PlannerProject $project): void
    {
        $this->guardProjectTransition($project, ProjectLifecycleState::DISCARDED, [
            ProjectLifecycleState::ACTIVE,
            ProjectLifecycleState::DORMANT,
        ]);

        DB::transaction(function () use ($project) {
            $this->transitionProject($project, ProjectLifecycleState::DISCARDED, 'manual:discard');
            $this->cascadeDiscardActiveTasks($project);
        });
    }

    /**
     * Reopen a completed project back to active.
     * Owner-level operation — caller must have authorized.
     * Allowed from: completed.
     */
    public function reopen(PlannerProject $project): void
    {
        $this->guardProjectTransition($project, ProjectLifecycleState::ACTIVE, [
            ProjectLifecycleState::COMPLETED,
        ]);
        $this->transitionProject($project, ProjectLifecycleState::ACTIVE, 'manual:reopen');
    }

    /**
     * Revive a discarded project back to active.
     * Discarded tasks are NOT auto-revived — that would be a bigger decision.
     * Allowed from: discarded.
     */
    public function revive(PlannerProject $project): void
    {
        $this->guardProjectTransition($project, ProjectLifecycleState::ACTIVE, [
            ProjectLifecycleState::DISCARDED,
        ]);
        $this->transitionProject($project, ProjectLifecycleState::ACTIVE, 'manual:revive');
    }

    /**
     * Auto-transition to dormant due to inactivity.
     * Called by the scheduled lifecycle tick, not by user code directly.
     * Allowed from: active.
     */
    public function autoDormant(PlannerProject $project): void
    {
        $this->guardProjectTransition($project, ProjectLifecycleState::DORMANT, [
            ProjectLifecycleState::ACTIVE,
        ]);
        $this->transitionProject($project, ProjectLifecycleState::DORMANT, 'auto:inactivity');
    }

    /**
     * Auto-reactivate a dormant project because activity occurred.
     * Called by event listeners on time-entries / task-edits / views.
     * Allowed from: dormant.
     */
    public function autoReactivate(PlannerProject $project): void
    {
        $this->guardProjectTransition($project, ProjectLifecycleState::ACTIVE, [
            ProjectLifecycleState::DORMANT,
        ]);
        $this->transitionProject($project, ProjectLifecycleState::ACTIVE, 'auto:activity');
    }

    // ── Task transitions ─────────────────────────────────────────

    /**
     * Complete a task.
     * Allowed from: active.
     */
    public function completeTask(PlannerTask $task): void
    {
        $this->guardTaskTransition($task, TaskLifecycleState::COMPLETED, [
            TaskLifecycleState::ACTIVE,
        ]);
        $this->transitionTask($task, TaskLifecycleState::COMPLETED, 'manual:complete');
    }

    /**
     * Discard a task.
     * Allowed from: active.
     */
    public function discardTask(PlannerTask $task): void
    {
        $this->guardTaskTransition($task, TaskLifecycleState::DISCARDED, [
            TaskLifecycleState::ACTIVE,
        ]);
        $this->transitionTask($task, TaskLifecycleState::DISCARDED, 'manual:discard');
    }

    // ── Query helpers ────────────────────────────────────────────

    public function canProjectTransitionTo(PlannerProject $project, ProjectLifecycleState $target): bool
    {
        return in_array($target, $this->allowedProjectTargetsFrom($project->lifecycle_state), true);
    }

    public function canTaskTransitionTo(PlannerTask $task, TaskLifecycleState $target): bool
    {
        return in_array($target, $this->allowedTaskTargetsFrom($task->lifecycle_state), true);
    }

    /**
     * @return ProjectLifecycleState[]
     */
    protected function allowedProjectTargetsFrom(?ProjectLifecycleState $from): array
    {
        return match ($from) {
            ProjectLifecycleState::ACTIVE => [
                ProjectLifecycleState::DORMANT,       // auto
                ProjectLifecycleState::COMPLETED,     // manual
                ProjectLifecycleState::DISCARDED,     // manual
            ],
            ProjectLifecycleState::DORMANT => [
                ProjectLifecycleState::ACTIVE,        // auto
                ProjectLifecycleState::COMPLETED,     // manual
                ProjectLifecycleState::DISCARDED,     // manual
            ],
            ProjectLifecycleState::COMPLETED => [ProjectLifecycleState::ACTIVE],   // reopen
            ProjectLifecycleState::DISCARDED => [ProjectLifecycleState::ACTIVE],   // revive
            default => [],
        };
    }

    /**
     * @return TaskLifecycleState[]
     */
    protected function allowedTaskTargetsFrom(?TaskLifecycleState $from): array
    {
        return match ($from) {
            TaskLifecycleState::ACTIVE => [
                TaskLifecycleState::COMPLETED,
                TaskLifecycleState::DISCARDED,
            ],
            // Terminal states are terminal — reopening a task means creating a
            // new one. If we want to change that later, this is the one place
            // to widen the allowed set.
            default => [],
        };
    }

    // ── Internals ────────────────────────────────────────────────

    /**
     * @param  ProjectLifecycleState[]  $allowedFrom
     */
    protected function guardProjectTransition(
        PlannerProject $project,
        ProjectLifecycleState $to,
        array $allowedFrom
    ): void {
        $from = $project->lifecycle_state;
        if (! in_array($from, $allowedFrom, true)) {
            throw InvalidLifecycleTransitionException::forProject(
                $from?->value ?? '(null)',
                $to->value
            );
        }
    }

    /**
     * @param  TaskLifecycleState[]  $allowedFrom
     */
    protected function guardTaskTransition(
        PlannerTask $task,
        TaskLifecycleState $to,
        array $allowedFrom
    ): void {
        $from = $task->lifecycle_state;
        if (! in_array($from, $allowedFrom, true)) {
            throw InvalidLifecycleTransitionException::forTask(
                $from?->value ?? '(null)',
                $to->value
            );
        }
    }

    protected function transitionProject(
        PlannerProject $project,
        ProjectLifecycleState $to,
        string $reason
    ): void {
        $project->forceFill([
            'lifecycle_state' => $to,
            'lifecycle_state_changed_at' => now(),
            'lifecycle_state_reason' => $reason,
        ])->save();
    }

    protected function transitionTask(
        PlannerTask $task,
        TaskLifecycleState $to,
        string $reason
    ): void {
        $task->forceFill([
            'lifecycle_state' => $to,
            'lifecycle_state_changed_at' => now(),
            'lifecycle_state_reason' => $reason,
        ])->save();
    }

    /**
     * Cascade a project discard onto all still-active tasks of that project.
     *
     * Uses a raw UPDATE for atomicity and speed — a project with hundreds of
     * active tasks would be slow to iterate through models, and each save
     * would fire observers/events we do not want for a cascade.
     *
     * @return int  number of tasks affected
     */
    protected function cascadeDiscardActiveTasks(PlannerProject $project): int
    {
        return DB::table('planner_tasks')
            ->where('project_id', $project->id)
            ->where('lifecycle_state', ProjectLifecycleState::ACTIVE->value)
            ->whereNull('deleted_at')
            ->update([
                'lifecycle_state' => TaskLifecycleState::DISCARDED->value,
                'lifecycle_state_changed_at' => now(),
                'lifecycle_state_reason' => 'cascade:project_discarded',
                'updated_at' => now(),
            ]);
    }
}
