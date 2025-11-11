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
            // Prüfe, ob bereits eine Task für dieses Datum existiert (verhindert Duplikate)
            $existingTask = PlannerTask::where('project_id', $recurringTask->project_id)
                ->where('project_slot_id', $recurringTask->project_slot_id)
                ->where('title', $recurringTask->title)
                ->whereDate('due_date', $recurringTask->next_due_date->toDateString())
                ->first();

            if ($existingTask) {
                $this->warn("  ⚠️  Übersprungen: Task '{$recurringTask->title}' existiert bereits für {$recurringTask->next_due_date->format('d.m.Y')}");
                $skippedCount++;
                
                // Trotzdem nächsten Termin berechnen
                if (!$isDryRun) {
                    $recurringTask->calculateNextDueDate();
                    $recurringTask->save();
                }
                continue;
            }

            $this->info("  📝 Erstelle Task: '{$recurringTask->title}' (Fällig: {$recurringTask->next_due_date->format('d.m.Y')})");

            if (!$isDryRun) {
                try {
                    $task = $recurringTask->createTask();
                    $this->info("     ✅ Task erstellt (ID: {$task->id})");
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
}

