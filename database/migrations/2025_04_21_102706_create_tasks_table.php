<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('planner_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_group_id')->nullable()->constrained('planner_task_groups')->nullOnDelete();
            $table->foreignId('recurring_task_id')->nullable()->constrained('planner_recurring_tasks')->nullOnDelete();
            $table->foreignId('delegated_group_id')->nullable()->constrained('planner_delegated_task_groups')->nullOnDelete();
            $table->integer('delegated_group_order')->default(0);
            $table->foreignId('project_id')->nullable()->constrained('planner_projects')->onDelete('cascade');
            $table->foreignId('project_slot_id')->nullable()->constrained('planner_project_slots')->nullOnDelete();
            $table->integer('project_slot_order')->default(0);
            $table->foreignId('sprint_slot_id')->nullable()->constrained('planner_sprint_slots')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->integer('sprint_slot_order')->nullable();
            
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('user_in_charge_id')->nullable();
            $table->boolean('is_personal')->default(true);
            $table->string('title');
            $table->text('description')->nullable();
            $table->char('description_hash', 64)->nullable();
            $table->text('dod')->nullable();
            $table->char('dod_hash', 64)->nullable();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('original_due_date')->nullable();
            $table->unsignedInteger('postpone_count')->default(0);
            $table->unsignedInteger('planned_minutes')->nullable();
            $table->string('priority')->default('normal');
            $table->boolean('is_frog')->default(false);
            $table->boolean('is_forced_frog')->default(false);
            $table->string('story_points')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('description_hash', 'idx_description_hash');
            $table->index('dod_hash', 'idx_dod_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planner_tasks');
    }
};
