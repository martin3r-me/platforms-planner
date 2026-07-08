<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ActivityClock;
use Platform\Planner\Services\LifecycleService;

/**
 * Nightly lifecycle tick.
 *
 * For every project currently in state `active`, this checks the objective
 * ActivityClock signal. If nothing happened on the project or any of its
 * tasks/canvases/time-entries within the threshold window, the project is
 * flipped to `dormant` via LifecycleService::autoDormant.
 *
 * The reverse transition (dormant -> active on new activity) is NOT handled
 * here. It fires event-based, so a dormant project wakes up immediately
 * when someone books time, edits a task, or opens the project. This command
 * only handles the "went quiet" direction.
 *
 * Idempotent: running twice in a row produces no additional flips, because
 * projects flipped in the first run are no longer in state `active` when
 * the second run queries.
 *
 * The threshold defaults to 45 days (product decision). Override via
 * --threshold for backfill or experiments.
 */
class LifecycleTickCommand extends Command
{
    protected $signature = 'planner:lifecycle:tick
                            {--threshold=45 : Days of inactivity before an active project flips to dormant}
                            {--team= : Restrict to a single team_id (optional)}
                            {--project= : Restrict to a single project_id (dry-runnable spot check)}
                            {--dry-run : Print what would flip but do not persist}';

    protected $description = 'Flips active projects to dormant when ActivityClock signal is older than the threshold.';

    public function handle(ActivityClock $clock, LifecycleService $lifecycle): int
    {
        $threshold = max(1, (int) $this->option('threshold'));
        $dryRun = (bool) $this->option('dry-run');

        $query = PlannerProject::query()
            ->where('lifecycle_state', ProjectLifecycleState::ACTIVE->value);

        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }
        if ($projectId = $this->option('project')) {
            $query->where('id', $projectId);
        }

        $projects = $query->get(['id', 'team_id', 'name', 'lifecycle_state', 'created_at']);
        $total = $projects->count();

        if ($total === 0) {
            $this->info('No active projects in scope.');
            return self::SUCCESS;
        }

        $mode = $dryRun ? 'DRY-RUN' : 'LIVE';
        $this->info("[{$mode}] Checking {$total} active project(s), threshold={$threshold}d");

        $projectIds = $projects->pluck('id')->all();
        $activity = $clock->lastActivityForProjects($projectIds);

        $now = now();
        $flipped = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($projects as $project) {
            // Fallback to created_at: freshly created projects with no
            // interactions yet must not be flipped on day 46.
            $lastActivity = $activity[$project->id] ?? $project->created_at;
            $daysSince = $lastActivity ? (int) $now->diffInDays($lastActivity, absolute: true) : null;

            if ($daysSince === null || $daysSince < $threshold) {
                $skipped++;
                continue;
            }

            $label = sprintf('#%d %s (%dd)', $project->id, mb_substr((string) ($project->name ?? '—'), 0, 60), $daysSince);

            if ($dryRun) {
                $this->line("  → would flip {$label}");
                $flipped++;
                continue;
            }

            try {
                $lifecycle->autoDormant($project);
                $flipped++;
                $this->line("  ✓ flipped {$label}");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  ✗ {$label} — {$e->getMessage()}");
                Log::warning('planner.lifecycle.tick failed for project', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            "[%s] Done — flipped=%d skipped=%d errors=%d",
            $mode, $flipped, $skipped, $errors
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
