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
        Schema::create('planner_recurring_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Basis-Felder (wie bei Tasks)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('user_in_charge_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('story_points')->nullable();
            $table->string('priority')->default('normal');
            $table->integer('planned_minutes')->nullable();
            
            // Projektbezug (optional)
            $table->foreignId('project_id')->nullable()->constrained('planner_projects')->nullOnDelete();
            $table->foreignId('project_slot_id')->nullable()->constrained('planner_project_slots')->nullOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained('planner_sprints')->nullOnDelete();
            $table->foreignId('task_group_id')->nullable()->constrained('planner_task_groups')->nullOnDelete();
            
            // Wiederholungslogik
            $table->string('recurrence_type')->default('weekly'); // daily, weekly, monthly, yearly
            $table->integer('recurrence_interval')->default(1); // z.B. 1 = jede Woche, 2 = alle 2 Wochen
            $table->datetime('recurrence_end_date')->nullable(); // optional, wann die Wiederholung endet
            $table->datetime('next_due_date')->nullable(); // wann die nächste Aufgabe erstellt werden soll
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planner_recurring_tasks');
    }
};

