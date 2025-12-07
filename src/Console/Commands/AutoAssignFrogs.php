<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\TaskStoryPoints;

class AutoAssignFrogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'planner:auto-assign-frogs {--dry-run : Zeigt nur an, was geändert würde}';

    /**
     * The console command description.
     */
    protected $description = 'Markiert Tasks automatisch als Frosch/Zwangs-Frosch basierend auf Verschiebungen und Story Points';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $query = PlannerTask::query()
            ->where('is_done', false);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('✅ Keine offenen Tasks gefunden.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info('🔍 DRY-RUN – es werden keine Daten geändert.');
        }

        $this->info("📋 Prüfe {$total} offene Task(s) auf Frosch-Kandidaten...");

        $updated = 0;
        $forced = 0;

        $query->orderBy('id')->chunkById(200, function ($tasks) use ($dryRun, &$updated, &$forced) {
            foreach ($tasks as $task) {
                $postpones = (int)($task->postpone_count ?? 0);
                $points = $this->storyPointValue($task->story_points);

                $score = ($postpones * 2) + $points;

                $shouldForce = $score >= 14
                    || $postpones >= 4
                    || ($points >= 13 && $postpones >= 2);

                $shouldFrog = $shouldForce
                    || $score >= 8
                    || ($postpones >= 2 && $points >= 5)
                    || $postpones >= 3;

                if (! $shouldFrog) {
                    continue;
                }

                if (! $dryRun) {
                    $task->is_frog = true;
                    if ($shouldForce) {
                        $task->is_forced_frog = true;
                    }
                    $task->save();
                }

                $updated++;
                if ($shouldForce) {
                    $forced++;
                }

                $this->line(sprintf(
                    '  • Task #%d (%s): score=%d, postpones=%d, points=%d -> %s',
                    $task->id,
                    $task->title,
                    $score,
                    $postpones,
                    $points,
                    $shouldForce ? 'Zwangs-Frosch' : 'Frosch'
                ));
            }
        });

        if ($dryRun) {
            $this->warn("🔍 DRY-RUN: {$updated} Task(s) würden auf Frosch gestellt, davon {$forced} als Zwangs-Frosch.");
        } else {
            $this->info("✅ {$updated} Task(s) aktualisiert, davon {$forced} als Zwangs-Frosch.");
        }

        return Command::SUCCESS;
    }

    private function storyPointValue(mixed $value): int
    {
        if ($value instanceof TaskStoryPoints) {
            return $value->points();
        }

        // Strings wie 'l', 'xl' etc.
        if (is_string($value)) {
            return TaskStoryPoints::tryFrom($value)?->points() ?? 0;
        }

        return 0;
    }
}

