<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task-zu-Task-Abhängigkeiten (Finish-to-Start): eine Kante sagt
 * „blocked_task hängt an blocker_task" — der abhängige Task wird erst
 * ausführbar, wenn der Vorgänger terminal ist (erledigt/verworfen).
 *
 * n:m (ein Task kann an mehreren hängen und mehrere blockieren). Die
 * Ausführungs-Sperre lebt im Claim (PlannerTask::notBlocked), analog zum
 * bestehenden agent_waiting_at-Skip. Zyklen werden beim Anlegen verhindert
 * (PlannerTask::wouldCreateDependencyCycle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_task_dependencies', function (Blueprint $table) {
            $table->id();
            // Der wartende Task.
            $table->foreignId('blocked_task_id')->constrained('planner_tasks')->cascadeOnDelete();
            // Der Vorgänger, der zuerst fertig sein muss.
            $table->foreignId('blocker_task_id')->constrained('planner_tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocked_task_id', 'blocker_task_id'], 'planner_task_dep_unique');
            $table->index('blocker_task_id', 'planner_task_dep_blocker_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_task_dependencies');
    }
};
