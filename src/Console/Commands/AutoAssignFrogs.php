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

        $forced = 0;

        $query->orderBy('id')->chunkById(200, function ($tasks) use ($dryRun, &$forced) {
            foreach ($tasks as $task) {
                $postpones = (int)($task->postpone_count ?? 0);
                $points = $this->storyPointValue($task->story_points);

                // Nur Zwangs-Frosch setzen, keine "normalen" Frösche.
                // Kriterien:
                //   - >=4 Verschiebungen
                //   - oder >=2 Verschiebungen UND Punkte >=13
                $shouldForce = ($postpones >= 4)
                    || ($postpones >= 2 && $points >= 13);

                if (! $shouldForce) {
                    continue;
                }

                if (! $dryRun) {
                    $task->is_frog = true;
                    $task->is_forced_frog = true;
                    $task->save();
                }

                $forced++;

                $this->line(sprintf(
                    '  • Task #%d (%s): postpones=%d, points=%d -> Zwangs-Frosch',
                    $task->id,
                    $task->title,
                    $postpones,
                    $points
                ));
            }
        });

        if ($dryRun) {
            $this->warn("🔍 DRY-RUN: {$forced} Task(s) würden als Zwangs-Frosch gesetzt.");
        } else {
            $this->info("✅ {$forced} Task(s) als Zwangs-Frosch gesetzt.");
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

