<?php

namespace Platform\Planner\Console\Commands;

use Platform\Planner\Enums\TaskLifecycleState;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Platform\Planner\Models\PlannerTask;

class PostponeOverdueTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'planner:postpone-overdue-tasks {--dry-run : Zeigt nur an, was geändert würde}';

    /**
     * The console command description.
     */
    protected $description = 'Sichert ursprüngliche Fälligkeitsdaten und verschiebt überfällige Tasks auf morgen 12:00';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now();
        $nextNoon = $now->copy()->addDay()->setTime(12, 0, 0);

        $query = PlannerTask::withStale()
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $now);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('✅ Keine überfälligen Tasks gefunden.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info('🔍 DRY-RUN – es werden keine Daten geändert.');
        }

        $this->info("📋 Bearbeite {$total} überfällige Task(s)...");

        $processed = 0;

        $query->orderBy('id')->chunkById(200, function ($tasks) use ($dryRun, $nextNoon, &$processed) {
            foreach ($tasks as $task) {
                $currentDue = $task->due_date;
                $originalDue = $task->original_due_date ?? $currentDue;
                $newDueDate = $nextNoon->copy();
                $newPostponeCount = (int)($task->postpone_count ?? 0) + 1;

                if (! $dryRun) {
                    $task->original_due_date = $originalDue;
                    $task->postpone_count = $newPostponeCount;
                    $task->due_date = $newDueDate;
                    $task->save();
                }

                $processed++;

                $this->line(sprintf(
                    '  • Task #%d: due %s -> %s | original: %s | postpones: %d',
                    $task->id,
                    optional($currentDue)->format('d.m.Y H:i'),
                    $newDueDate->format('d.m.Y H:i'),
                    optional($originalDue)->format('d.m.Y H:i'),
                    $newPostponeCount
                ));
            }
        });

        if ($dryRun) {
            $this->warn("🔍 DRY-RUN: {$processed} Task(s) würden verschoben.");
        } else {
            $this->info("✅ {$processed} Task(s) aktualisiert.");
        }

        return Command::SUCCESS;
    }
}

