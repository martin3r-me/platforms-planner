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
            $table->foreignId('project_id')->nullable()->constrained('planner_projects')->nullOnDelete();
            $table->foreignId('sprint_slot_id')->nullable()->constrained('planner_sprint_slots')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->integer('sprint_slot_order')->default(0);
            
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('user_in_charge_id')->nullable();
            $table->boolean('is_personal')->default(true);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority')->default('normal');
            $table->boolean('is_frog')->default(false);
            $table->string('story_points')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
