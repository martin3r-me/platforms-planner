<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kalender-Abo entkoppelt von Authz: welche Projekte ein User im CalDAV-Feed
 * sehen will, ist eine bewusste per-User-Wahl — KEIN Zugriff (der kommt aus dem
 * Graphen). Löst expose_in_caldav aus planner_project_users ab.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planner_calendar_exposures')) {
            Schema::create('planner_calendar_exposures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('project_id');
                $table->timestamps();

                $table->unique(['user_id', 'project_id']);
                $table->index('project_id');
            });
        }

        // Backfill: bisherige CalDAV-Freigaben übernehmen.
        if (Schema::hasTable('planner_project_users')
            && Schema::hasColumn('planner_project_users', 'expose_in_caldav')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_calendar_exposures');
    }
};
