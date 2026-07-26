<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alt-Struktur endgültig entfernen: Projekt-Mitgliedschaft (planner_project_users)
 * ist abgelöst — Zugriff kommt aus dem Org-Graphen (Ersteller ODER strukturell
 * erreichbar), Kalender-Abo lebt in planner_calendar_exposures.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sicherheitsnetz: CalDAV-Freigaben final übernehmen, falls noch nicht geschehen.
        if (Schema::hasTable('planner_project_users')
            && Schema::hasColumn('planner_project_users', 'expose_in_caldav')
            && Schema::hasTable('planner_calendar_exposures')) {
            $rows = DB::table('planner_project_users')
                ->where('expose_in_caldav', true)
                ->get(['user_id', 'project_id']);

            foreach ($rows as $r) {
                if ($r->user_id === null || $r->project_id === null) {
                    continue;
                }
                DB::table('planner_calendar_exposures')->updateOrInsert(
                    ['user_id' => $r->user_id, 'project_id' => $r->project_id],
                    ['updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        Schema::dropIfExists('planner_project_users');
    }

    public function down(): void
    {
        // Best-effort-Wiederherstellung der Struktur (Daten sind verloren).
        if (! Schema::hasTable('planner_project_users')) {
            Schema::create('planner_project_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role')->nullable();
                $table->boolean('expose_in_caldav')->default(false);
                $table->timestamps();

                $table->index('project_id');
                $table->index('user_id');
            });
        }
    }
};
