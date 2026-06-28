<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planner_project_snapshots')) {
            return;
        }

        Schema::create('planner_project_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Snapshot-Meta
            $table->dateTime('taken_at');
            $table->date('taken_on');
            $table->string('trigger', 32)->default('cron'); // cron|manual|backfill

            // Projekt-FK + frozen context
            $table->foreignId('project_id')
                ->constrained('planner_projects', 'id', 'pps_proj_fk')
                ->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()
                ->constrained('teams', 'id', 'pps_team_fk')
                ->nullOnDelete();
            $table->string('kind', 20)->nullable();
            $table->string('status', 20)->nullable();
            $table->string('color', 32)->nullable();

            // Task-Counts
            $table->unsignedInteger('tasks_total')->default(0);
            $table->unsignedInteger('tasks_open')->default(0);
            $table->unsignedInteger('tasks_done')->default(0);
            $table->unsignedInteger('tasks_overdue')->default(0);
            $table->unsignedInteger('tasks_frog')->default(0);
            $table->unsignedInteger('tasks_postponed')->default(0);

            // Story Points
            $table->unsignedInteger('story_points_total')->default(0);
            $table->unsignedInteger('story_points_open')->default(0);
            $table->unsignedInteger('story_points_done')->default(0);

            // Zeit (Minuten)
            $table->unsignedInteger('minutes_planned')->default(0);
            $table->unsignedInteger('minutes_logged')->default(0);
            $table->unsignedInteger('minutes_billed')->default(0);
            $table->unsignedInteger('minutes_unbilled')->default(0);

            // Budget (frozen)
            $table->decimal('budget_amount', 12, 2)->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('budget_used_euro', 12, 2)->nullable();

            // Termine
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->integer('days_to_planned_end')->nullable(); // negativ = ueberfaellig

            // Canvas
            $table->unsignedTinyInteger('canvas_score')->nullable(); // 0-100
            $table->string('canvas_color', 16)->nullable();           // green|yellow|red
            $table->decimal('canvas_completeness_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('canvas_filled_blocks')->nullable();
            $table->unsignedSmallInteger('canvas_total_blocks')->nullable();
            $table->unsignedSmallInteger('canvas_risk_count')->nullable();
            $table->unsignedSmallInteger('canvas_overdue_milestones')->nullable();

            // Composite Health (vier Achsen → eine Wahrheit)
            $table->unsignedTinyInteger('health_score')->nullable(); // 0-100, gewichtet; null wenn keine Achse berechenbar
            $table->string('health_color', 16)->nullable();           // green|yellow|red, worst-of-4; null = "unbekannt"

            // Confidence — wie belastbar ist der Snapshot?
            $table->unsignedTinyInteger('confidence_score'); // 0-100
            $table->string('confidence_reason', 255)->nullable();

            // Bewegung — Delta zum vorigen Snapshot
            $table->unsignedBigInteger('prev_snapshot_id')->nullable();
            $table->integer('delta_health_score')->nullable();
            $table->integer('delta_canvas_score')->nullable();
            $table->integer('delta_tasks_done')->nullable();
            $table->dateTime('last_movement_at')->nullable();

            $table->timestamps();

            // Unique: max 1 Snapshot pro Projekt pro Tag
            $table->unique(['project_id', 'taken_on'], 'pps_proj_day_uniq');

            // Indizes
            $table->index(['project_id', 'taken_at'], 'pps_proj_taken_idx');
            $table->index(['team_id', 'taken_at'], 'pps_team_taken_idx');
            $table->index('taken_at', 'pps_taken_idx');
        });

        // Self-FK separat (vermeidet Reihenfolge-Probleme im create())
        Schema::table('planner_project_snapshots', function (Blueprint $table) {
            $table->foreign('prev_snapshot_id', 'pps_prev_fk')
                ->references('id')->on('planner_project_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planner_project_snapshots', function (Blueprint $table) {
            $table->dropForeign('pps_prev_fk');
        });
        Schema::dropIfExists('planner_project_snapshots');
    }
};
