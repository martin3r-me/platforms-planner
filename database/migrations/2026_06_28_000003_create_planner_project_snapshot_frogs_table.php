<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planner_project_snapshot_frogs')) {
            return;
        }

        Schema::create('planner_project_snapshot_frogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('planner_project_snapshots', 'id', 'ppss_frog_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()
                ->constrained('planner_tasks', 'id', 'ppss_frog_task_fk')
                ->nullOnDelete();

            // Denorm — bleiben erhalten auch wenn Task spaeter geloescht wird
            $table->uuid('task_uuid')->nullable();
            $table->string('task_title', 500);
            $table->dateTime('due_date')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->unsignedInteger('postpone_count')->default(0);
            $table->string('story_points', 8)->nullable();

            // Rang innerhalb dieses Snapshots (1..5)
            $table->unsignedTinyInteger('rank');

            $table->timestamps();

            $table->index(['snapshot_id', 'rank'], 'ppss_frog_snap_rank_idx');
            $table->index(['task_id', 'snapshot_id'], 'ppss_frog_task_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_snapshot_frogs');
    }
};
