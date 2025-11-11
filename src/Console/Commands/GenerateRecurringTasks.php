<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Platform\Planner\Models\PlannerRecurringTask;
use Platform\Planner\Models\PlannerTask;

class GenerateRecurringTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'planner:generate-recurring-tasks 
                            {--dry-run : Zeige nur was passieren würde, ohne Tasks zu erstellen}';

    /**
     * The console command description.
     */
    protected $description = 'Erstellt Tasks aus aktiven wiederkehrenden Aufgaben, deren next_due_date erreicht wurde';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Tasks werden erstellt');
        }

        $this->info('🔄 Suche nach wiederkehrenden Aufgaben...');
        $this->newLine();

        // Alle aktiven wiederkehrenden Aufgaben finden, die eine Task erstellen sollten
        $recurringTasks = PlannerRecurringTask::where('is_active', true)
            ->whereNotNull('next_due_date')
            ->get()
            ->filter(fn($rt) => $rt->shouldCreateTask());

        $count = $recurringTasks->count();

        if ($count === 0) {
            $this->info('✅ Keine wiederkehrenden Aufgaben gefunden, die Tasks erstellen müssen.');
            return Command::SUCCESS;
        }

        $this->info("📋 {$count} wiederkehrende Aufgabe(n) gefunden, die Task(s) erstellen müssen:");
        $this->newLine();

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($recurringTasks as $recurringTask) {
            $this->info("  📝 Verarbeite: '{$recurringTask->title}' (Fällig: {$recurringTask->next_due_date->format('d.m.Y')})");

            // Prüfe, ob bereits eine Task in der aktuellen Frequenzperiode existiert (nur wenn auto_delete_old_tasks nicht aktiv ist)
            if (!$recurringTask->auto_delete_old_tasks) {
                $periodStart = $this->getPeriodStart($recurringTask->recurrence_type, $recurringTask->next_due_date);
                
                $existingTask = PlannerTask::where('recurring_task_id', $recurringTask->id)
                    ->where('created_at', '>=', $periodStart)
                    ->first();

                if ($existingTask) {
                    $this->warn("     ⚠️  Übersprungen: Task wurde bereits in dieser Periode erstellt (am {$existingTask->created_at->format('d.m.Y H:i')})");
                    $skippedCount++;
                    
                    // Trotzdem nächsten Termin berechnen
                    if (!$isDryRun) {
                        $recurringTask->calculateNextDueDate();
                        $recurringTask->save();
                    }
                    continue;
                }
            }

            // Zeige Optionen an
            $options = [];
            if ($recurringTask->auto_delete_old_tasks) {
                $oldTasksCount = $recurringTask->tasks()->count();
                $options[] = "Löscht {$oldTasksCount} alte Task(s)";
            }
            if ($recurringTask->auto_mark_as_done) {
                $options[] = "Markiert als erledigt";
            }
            if (!empty($options)) {
                $this->info("     ⚙️  Optionen: " . implode(', ', $options));
            }

            if (!$isDryRun) {
                try {
                    $task = $recurringTask->createTask();
                    $status = $task->is_done ? ' (erledigt)' : '';
                    $this->info("     ✅ Task erstellt (ID: {$task->id}){$status}");
                    $createdCount++;
                } catch (\Exception $e) {
                    $this->error("     ❌ Fehler beim Erstellen: {$e->getMessage()}");
                    $skippedCount++;
                }
            } else {
                $this->info("     🔍 Würde Task erstellen");
                $createdCount++;
            }
        }

        $this->newLine();
        
        if ($isDryRun) {
            $this->warn("🔍 DRY-RUN: {$createdCount} Task(s) würden erstellt, {$skippedCount} übersprungen");
            $this->warn('Führe den Command ohne --dry-run aus, um die Tasks zu erstellen.');
        } else {
            $this->info("✅ {$createdCount} Task(s) erfolgreich erstellt, {$skippedCount} übersprungen");
        }

        return Command::SUCCESS;
    }

    /**
     * Berechnet den Start der aktuellen Frequenzperiode basierend auf dem Wiederholungstyp
     */
    private function getPeriodStart(string $recurrenceType, \Carbon\Carbon $date): \Carbon\Carbon
    {
        return match($recurrenceType) {
            'daily' => $date->copy()->startOfDay(),
            'weekly' => $date->copy()->startOfWeek(),
            'monthly' => $date->copy()->startOfMonth(),
            'yearly' => $date->copy()->startOfYear(),
            default => $date->copy()->startOfDay(),
        };
    }
}

