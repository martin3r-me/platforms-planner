<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schritt 1b: `lifecycle_state` an Tasks — analog zu Projects, aber ohne
 * 'ruhend' (Tasks sind feingranular genug, dass sie manuell abgeschlossen
 * oder verworfen werden — kein Aktivitaets-Auto-Flip).
 *
 * Backfill:
 *   is_done = true  -> erledigt
 *   sonst          -> aktiv
 *
 * 'verworfen' entsteht spaeter durch die Projekt-verworfen-Kaskade oder
 * durch manuelle Owner-Aktion — nicht in dieser Migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->string('lifecycle_state', 20)->default('aktiv')->after('is_done');
            $table->timestamp('lifecycle_state_changed_at')->nullable()->after('lifecycle_state');
            $table->string('lifecycle_state_reason', 60)->nullable()->after('lifecycle_state_changed_at');

            $table->index('lifecycle_state');
        });

        DB::statement("
            UPDATE planner_tasks
            SET lifecycle_state = CASE
                WHEN is_done = 1 THEN 'erledigt'
                ELSE 'aktiv'
            END,
            lifecycle_state_changed_at = COALESCE(done_at, updated_at, created_at)
        ");
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropIndex(['lifecycle_state']);
            $table->dropColumn([
                'lifecycle_state',
                'lifecycle_state_changed_at',
                'lifecycle_state_reason',
            ]);
        });
    }
};
