<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Platform\Planner\Models\PlannerSprintSlot;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerSprint;
use Platform\Planner\Models\PlannerProject;

class MigrateSprintSlotsToProjectSlots extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'planner:migrate-sprint-slots-to-project-slots {--dry-run : Zeige nur was passieren würde, ohne Änderungen}';

    /**
     * The console command description.
     */
    protected $description = 'Konvertiert alle Sprint-Slots zu Project-Slots und migriert die Tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Änderungen werden vorgenommen');
        }

        $this->info('🚀 Starte Migration von Sprint-Slots zu Project-Slots...');

        // 1. Alle Sprints mit ihren Slots holen
        $sprints = PlannerSprint::with(['sprintSlots.tasks'])->get();
        
        $totalSprints = $sprints->count();
        $totalSlots = 0;
        $totalTasks = 0;
        
        $this->info("📊 Gefunden: {$totalSprints} Sprints");

        foreach ($sprints as $sprint) {
            $this->info("📁 Verarbeite Sprint: {$sprint->name} (Projekt: {$sprint->project->name})");
            
            foreach ($sprint->sprintSlots as $sprintSlot) {
                $totalSlots++;
                $this->info("  📋 Slot: {$sprintSlot->name}");
                
                // 2. Project-Slot erstellen
                if (!$isDryRun) {
                    $projectSlot = PlannerProjectSlot::create([
                        'project_id' => $sprint->project_id,
                        'name' => $sprintSlot->name,
                        'order' => $sprintSlot->order,
                        'user_id' => $sprintSlot->user_id,
                        'team_id' => $sprintSlot->team_id,
                    ]);
                    
                    $this->info("    ✅ Project-Slot erstellt: {$projectSlot->id}");
                } else {
                    $this->info("    🔍 Würde Project-Slot erstellen für Projekt: {$sprint->project_id}");
                }
                
                // 3. Tasks migrieren
                $tasks = $sprintSlot->tasks;
                foreach ($tasks as $task) {
                    $totalTasks++;
                    
                    if (!$isDryRun) {
                        $task->project_slot_id = $projectSlot->id;
                        $task->project_slot_order = $task->sprint_slot_order;
                        $task->sprint_slot_id = null;
                        $task->sprint_slot_order = null;
                        $task->save();
                        
                        $this->info("      ✅ Task migriert: {$task->title}");
                    } else {
                        $this->info("      🔍 Würde Task migrieren: {$task->title}");
                    }
                }
            }
        }

        $this->info("📈 Zusammenfassung:");
        $this->info("  - Sprints verarbeitet: {$totalSprints}");
        $this->info("  - Slots konvertiert: {$totalSlots}");
        $this->info("  - Tasks migriert: {$totalTasks}");

        if ($isDryRun) {
            $this->warn('🔍 Dies war ein DRY-RUN. Führe den Command ohne --dry-run aus, um die Änderungen zu übernehmen.');
        } else {
            $this->info('✅ Migration erfolgreich abgeschlossen!');
            $this->warn('⚠️  Alte Sprint-Slots sind noch vorhanden. Du kannst sie nach dem Test löschen.');
        }

        return Command::SUCCESS;
    }
}
