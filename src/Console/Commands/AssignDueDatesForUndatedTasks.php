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

    /**
     * Berechnet das Ziel-Fälligkeitsdatum:
     * - Wenn aktueller Tag >= 15: 15. des Folgemonats, 12:00
     * - Sonst: Ende des aktuellen Monats, 12:00
     * - Immer mindestens 14 Tage in der Zukunft; sonst auf +14 Tage (12:00) verschieben.
     */
    private function calculateTargetDate(Carbon $now): Carbon
    {
        if ($now->day >= 15) {
            $target = $now->copy()->addMonthNoOverflow()->day(15)->setTime(12, 0, 0);
        } else {
            $target = $now->copy()->endOfMonth()->setTime(12, 0, 0);
        }

        // Sicherstellen, dass mindestens 14 Tage Puffer bleiben
        if ($target->diffInDays($now) < 14) {
            $target = $now->copy()->addDays(14)->setTime(12, 0, 0);
        }

        return $target;
    }
}

