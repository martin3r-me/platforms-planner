<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Platform\Planner\Models\PlannerTask;
use Platform\Core\Support\FieldHasher;

class EncryptTaskDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'planner:encrypt-descriptions 
                            {--dry-run : Zeigt nur an, was verschlüsselt würde, ohne Änderungen}';

    /**
     * The console command description.
     */
    protected $description = 'Verschlüsselt alle vorhandenen description und dod Felder in planner_tasks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Daten werden geändert');
        }

        $this->info('🔐 Starte Verschlüsselung von Task-Beschreibungen...');
        $this->newLine();

        // Prüfe ob Hash-Spalten existieren
        if (!Schema::hasColumn('planner_tasks', 'description_hash')) {
            $this->error('❌ Hash-Spalten existieren nicht. Bitte Migration zuerst ausführen: php artisan migrate');
            return Command::FAILURE;
        }

        // Tasks mit nicht-leeren description oder dod finden
        $tasks = PlannerTask::query()
            ->where(function ($q) {
                $q->whereNotNull('description')
                  ->where('description', '!=', '');
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('dod')
                  ->where('dod', '!=', '');
            })
            ->get();

        $total = $tasks->count();

        if ($total === 0) {
            $this->info('✅ Keine Tasks mit Beschreibungen gefunden.');
            return Command::SUCCESS;
        }

        $this->info("📋 {$total} Task(s) gefunden, die verschlüsselt werden müssen.");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $encrypted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tasks as $task) {
            try {
                $needsUpdate = false;
                $plainDescription = null;
                $plainDod = null;

                // Prüfe description - Raw-Wert direkt aus DB lesen
                $rawDescription = DB::table('planner_tasks')
                    ->where('id', $task->id)
                    ->value('description');

                if (!empty($rawDescription)) {
                    $hasHash = !empty($task->description_hash);
                    $isEncrypted = $this->isEncrypted($rawDescription);

                    if (!$hasHash || !$isEncrypted) {
                        // Plain-Text merken für späteres Setzen
                        $plainDescription = $rawDescription;
                        $needsUpdate = true;
                    }
                }

                // Prüfe dod (falls Spalte existiert) - Raw-Wert direkt aus DB lesen
                if (Schema::hasColumn('planner_tasks', 'dod')) {
                    $rawDod = DB::table('planner_tasks')
                        ->where('id', $task->id)
                        ->value('dod');

                    if (!empty($rawDod)) {
                        $hasDodHash = !empty($task->dod_hash);
                        $isDodEncrypted = $this->isEncrypted($rawDod);

                        if (!$hasDodHash || !$isDodEncrypted) {
                            $plainDod = $rawDod;
                            $needsUpdate = true;
                        }
                    }
                }

                if ($needsUpdate) {
                    if (!$isDryRun) {
                        // Verschlüsselung direkt über DB durchführen
                        // Das umgeht Probleme mit dem Cast, der versucht zu entschlüsseln
                        $updates = [];
                        $teamSalt = $task->team_id ? (string) $task->team_id : null;
                        
                        if ($plainDescription !== null) {
                            $encryptedDesc = Crypt::encryptString($plainDescription);
                            $updates['description'] = $encryptedDesc;
                            $updates['description_hash'] = FieldHasher::hmacSha256($plainDescription, $teamSalt);
                        }
                        
                        if ($plainDod !== null) {
                            $encryptedDod = Crypt::encryptString($plainDod);
                            $updates['dod'] = $encryptedDod;
                            $updates['dod_hash'] = FieldHasher::hmacSha256($plainDod, $teamSalt);
                        }
                        
                        if (!empty($updates)) {
                            $updates['updated_at'] = now();
                            DB::table('planner_tasks')
                                ->where('id', $task->id)
                                ->update($updates);
                        }
                    }
                    $encrypted++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("  ❌ Fehler bei Task #{$task->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("🔍 DRY-RUN: {$encrypted} Task(s) würden verschlüsselt werden.");
            $this->info("   {$skipped} Task(s) bereits verschlüsselt oder leer.");
        } else {
            $this->info("✅ {$encrypted} Task(s) erfolgreich verschlüsselt.");
            $this->info("   {$skipped} Task(s) bereits verschlüsselt oder leer.");
            if ($errors > 0) {
                $this->warn("   ⚠️  {$errors} Fehler aufgetreten.");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Prüft ob ein Wert bereits verschlüsselt ist
     * Verschlüsselte Werte sind base64-kodiert und haben eine bestimmte Länge/Struktur
     */
    private function isEncrypted(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Laravel Crypt erzeugt base64-kodierte Strings
        // Verschlüsselte Werte sind typischerweise länger und haben base64-Format
        // Einfache Heuristik: Wenn der Wert base64-decodierbar ist und eine bestimmte Länge hat
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        // Verschlüsselte Werte haben typischerweise eine Mindestlänge
        // und enthalten nicht-printable Zeichen nach Decodierung
        return strlen($decoded) > 16 && !ctype_print($decoded);
    }
}

