<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schritt 1 der Lifecycle-Konsolidierung: neues Feld `lifecycle_state`
 * einfuehren und aus bestehenden Signalen (status, done, done_at) backfillen.
 *
 * Die Altfelder bleiben zunaechst bestehen — sie werden in einem zweiten
 * Schritt entfernt, sobald der gesamte Code auf lifecycle_state umgestellt
 * ist. Dadurch koennen wir stufenweise migrieren, ohne dass eine Zwischen-
 * Deployment-Version das falsche Feld liest.
 *
 * Backfill-Regel:
 *   done = true                    -> abgeschlossen
 *   status = passiv oder inaktiv   -> ruhend
 *   sonst                          -> aktiv
 *
 * 'verworfen' entsteht in dieser Migration NIE, weil es heute keinen Weg
 * gibt, ein Projekt bewusst "ohne Ergebnis" zu beenden — der Zustand wird
 * erst nutzbar, wenn Owner die neue Transition manuell nutzen.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->string('lifecycle_state', 20)->default('aktiv')->after('status');
            $table->timestamp('lifecycle_state_changed_at')->nullable()->after('lifecycle_state');
            $table->string('lifecycle_state_reason', 60)->nullable()->after('lifecycle_state_changed_at');

            $table->index('lifecycle_state');
        });

        // Backfill in einer Batch-Query — schneller als Model::chunk() und
        // vermeidet Event-Feuer (BroadcastsEvents/observers), was hier weder
        // gewuenscht noch semantisch korrekt waere (Migration != Owner-Aktion).
        DB::statement("
            UPDATE planner_projects
            SET lifecycle_state = CASE
                WHEN done = 1 THEN 'abgeschlossen'
                WHEN status IN ('passiv', 'inaktiv') THEN 'ruhend'
                ELSE 'aktiv'
            END,
            lifecycle_state_changed_at = COALESCE(done_at, updated_at, created_at),
            lifecycle_state_reason = CASE
                WHEN done = 1 THEN 'migration:done'
                WHEN status = 'passiv' THEN 'migration:passiv'
                WHEN status = 'inaktiv' THEN 'migration:inaktiv'
                ELSE 'migration:aktiv'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropIndex(['lifecycle_state']);
            $table->dropColumn([
                'lifecycle_state',
                'lifecycle_state_changed_at',
                'lifecycle_state_reason',
            ]);
        });
    }
};
