<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Platform\Planner\Models\PlannerTask;

class AssignDueDatesForUndatedTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'planner:assign-due-dates-missing {--dry-run : Zeigt nur an, was geändert würde}';

    /**
     * The console command description.
     */
    protected $description = 'Vergibt Fälligkeitsdaten für Tasks ohne due_date (mit Slot oder persönliche Aufgaben) gemäß Monatsregel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now();

        $targetDate = $this->calculateTargetDate($now);

        $query = PlannerTask::query()
            ->whereNull('due_date')
            ->where('is_done', false)
            ->where(function ($q) {
                // Projekt-Tasks nur, wenn sie einen Slot haben (kein Backlog)
                $q->whereNull('project_id')
                  ->orWhereNotNull('project_slot_id');
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('✅ Keine passenden Tasks ohne Fälligkeit gefunden.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info('🔍 DRY-RUN – es werden keine Daten geändert.');
        }

        $this->info("📋 Setze Fälligkeitsdatum auf {$targetDate->format('d.m.Y H:i')} für {$total} Task(s)...");

        $updated = 0;
        $query->orderBy('id')->chunkById(200, function ($tasks) use ($targetDate, $dryRun, &$updated) {
            foreach ($tasks as $task) {
                if (! $dryRun) {
                    $task->due_date = $targetDate;
                    $task->save();
                }

                $updated++;

                $this->line(sprintf(
                    '  • Task #%d (%s): due -> %s',
                    $task->id,
                    $task->title,
                    $targetDate->format('d.m.Y H:i')
                ));
            }
        });

        if ($dryRun) {
            $this->warn("🔍 DRY-RUN: {$updated} Task(s) würden aktualisiert.");
        } else {
            $this->info("✅ {$updated} Task(s) aktualisiert.");
        }

        return Command::SUCCESS;
    }

    private function calculateTargetDate(Carbon $now): Carbon
    {
        // 5 Werktage (Mo–Fr) in die Zukunft, 12:00 Uhr
        return $now->copy()->addWeekdays(5)->setTime(12, 0, 0);
    }
}

