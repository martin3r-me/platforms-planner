<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt die legacy-Spalten planned_minutes und estimated_hours,
 * nachdem die Daten in organization_time_planned migriert wurden.
 *
 * Deploy-Strategie: Erst nach Validierungsphase (Phase 7) ausführen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropColumn('planned_minutes');
        });

        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropColumn(['planned_minutes', 'estimated_hours']);
        });
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->integer('planned_minutes')->nullable()->after('postpone_count');
        });

        Schema::table('planner_projects', function (Blueprint $table) {
            $table->integer('planned_minutes')->nullable()->after('order');
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('planned_minutes');
        });
    }
};
