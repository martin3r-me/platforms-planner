<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schritt 2b (Tasks): drop legacy state columns — `is_done`, `done_at`.
 *
 * All code paths now read `lifecycle_state`. The Schritt-2a backfill from
 * `is_done` populated the new field.
 *
 * `done_at` is redundant with `lifecycle_state_changed_at` restricted to
 * lifecycle_state = 'erledigt' — consumers now derive done_at from that.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('planner_tasks', 'is_done')) {
                $table->dropColumn('is_done');
            }
            if (Schema::hasColumn('planner_tasks', 'done_at')) {
                $table->dropColumn('done_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('planner_tasks', 'is_done')) {
                $table->boolean('is_done')->default(false);
            }
            if (! Schema::hasColumn('planner_tasks', 'done_at')) {
                $table->timestamp('done_at')->nullable();
            }
        });
    }
};
