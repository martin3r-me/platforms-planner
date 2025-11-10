<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerCustomerProject;
use Platform\Core\Models\Team;

class MoveProjectToTeam extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'planner:move-project-to-team 
                            {project : Die ID des Projekts}
                            {team : Die ID des Ziel-Teams}
                            {--dry-run : Zeige nur was passieren würde, ohne Änderungen}
                            {--force : Bestätigung überspringen}';

    /**
     * The console command description.
     */
    protected $description = 'Verschiebt ein Projekt in ein anderes Team und aktualisiert alle zugehörigen Slots und Tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projectId = $this->argument('project');
        $teamId = $this->argument('team');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Projekt laden
        $project = PlannerProject::with(['projectSlots', 'tasks', 'customerProject'])->find($projectId);
        
        if (!$project) {
            $this->error("❌ Projekt mit ID {$projectId} nicht gefunden!");
            return Command::FAILURE;
        }

        // Team prüfen
        $team = Team::find($teamId);
        if (!$team) {
            $this->error("❌ Team mit ID {$teamId} nicht gefunden!");
            return Command::FAILURE;
        }

        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Änderungen werden vorgenommen');
        }

        $this->info("📋 Projekt: {$project->name} (ID: {$project->id})");
        $this->info("📊 Aktuelles Team: {$project->team_id}");
        $this->info("🎯 Ziel-Team: {$team->name} (ID: {$team->id})");
        $this->newLine();

        // Statistiken sammeln
        $projectSlotsCount = $project->projectSlots->count();
        $tasksCount = $project->tasks->count();
        $hasCustomerProject = $project->customerProject !== null;

        $this->info("📈 Zu aktualisierende Datensätze:");
        $this->info("  - 1 Projekt");
        $this->info("  - {$projectSlotsCount} Project Slots");
        $this->info("  - {$tasksCount} Tasks");
        if ($hasCustomerProject) {
            $this->info("  - 1 Customer Project");
        }
        $this->newLine();

        // Bestätigung
        if (!$isDryRun && !$force) {
            if (!$this->confirm('Möchten Sie fortfahren?', true)) {
                $this->info('Abgebrochen.');
                return Command::SUCCESS;
            }
        }

        $this->info('🚀 Starte Verschiebung...');
        $this->newLine();

        // 1. Projekt aktualisieren
        $this->info("📁 Aktualisiere Projekt...");
        if (!$isDryRun) {
            $project->team_id = $teamId;
            $project->save();
            $this->info("  ✅ Projekt team_id aktualisiert");
        } else {
            $this->info("  🔍 Würde Projekt team_id auf {$teamId} setzen");
        }

        // 2. Project Slots aktualisieren
        if ($projectSlotsCount > 0) {
            $this->info("📋 Aktualisiere {$projectSlotsCount} Project Slots...");
            $bar = $this->output->createProgressBar($projectSlotsCount);
            $bar->start();

            foreach ($project->projectSlots as $slot) {
                if (!$isDryRun) {
                    $slot->team_id = $teamId;
                    $slot->save();
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            if (!$isDryRun) {
                $this->info("  ✅ {$projectSlotsCount} Project Slots aktualisiert");
            } else {
                $this->info("  🔍 Würde {$projectSlotsCount} Project Slots aktualisieren");
            }
        }

        // 3. Tasks aktualisieren
        if ($tasksCount > 0) {
            $this->info("✅ Aktualisiere {$tasksCount} Tasks...");
            $bar = $this->output->createProgressBar($tasksCount);
            $bar->start();

            foreach ($project->tasks as $task) {
                if (!$isDryRun) {
                    $task->team_id = $teamId;
                    $task->save();
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            if (!$isDryRun) {
                $this->info("  ✅ {$tasksCount} Tasks aktualisiert");
            } else {
                $this->info("  🔍 Würde {$tasksCount} Tasks aktualisieren");
            }
        }

        // 4. Customer Project aktualisieren (falls vorhanden)
        if ($hasCustomerProject) {
            $this->info("💼 Aktualisiere Customer Project...");
            if (!$isDryRun) {
                $project->customerProject->team_id = $teamId;
                $project->customerProject->save();
                $this->info("  ✅ Customer Project team_id aktualisiert");
            } else {
                $this->info("  🔍 Würde Customer Project team_id auf {$teamId} setzen");
            }
        }

        $this->newLine();
        
        if ($isDryRun) {
            $this->warn('🔍 Dies war ein DRY-RUN. Führe den Command ohne --dry-run aus, um die Änderungen zu übernehmen.');
        } else {
            $this->info('✅ Verschiebung erfolgreich abgeschlossen!');
            $this->info("🎉 Projekt '{$project->name}' wurde erfolgreich in Team '{$team->name}' verschoben.");
        }

        return Command::SUCCESS;
    }
}

