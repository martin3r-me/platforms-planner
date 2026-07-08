<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schritt 2b (Projects): drop legacy state columns — `status`, `done`, `done_at`.
 *
 * All code paths now read `lifecycle_state` (introduced in
 * 2026_07_08_000001). The Schritt-2a migration backfilled the new field
 * from these legacy signals, so dropping them here loses no truth.
 *
 * Index on `status` from the original migration goes away with the column.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            // Drop the status index first — MySQL doesn't auto-drop composite
            // indexes when only one column is removed, and the original
            // migration created it standalone.
            if (Schema::hasColumn('planner_projects', 'status')) {
                try {
                    $table->dropIndex(['status']);
                } catch (\Throwable $e) {
                    // Index name might differ across hosts — swallow.
                }
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('planner_projects', 'done')) {
                $table->dropColumn('done');
            }
            if (Schema::hasColumn('planner_projects', 'done_at')) {
                $table->dropColumn('done_at');
            }
        });
    }

    public function down(): void
    {
        // Re-add columns as they were before, but do not attempt to backfill:
        // the truthful state now lives only in lifecycle_state.
        Schema::table('planner_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('planner_projects', 'status')) {
                $table->string('status', 20)->default('aktiv')->after('kind');
                $table->index('status');
            }
            if (! Schema::hasColumn('planner_projects', 'done')) {
                $table->boolean('done')->default(false);
            }
            if (! Schema::hasColumn('planner_projects', 'done_at')) {
                $table->timestamp('done_at')->nullable();
            }
        });
    }
};
