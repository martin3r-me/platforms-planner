<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent-Felder für PlannerTasks — analog zu dev_issues. Ein Backoffice-Worker
 * holt sich seine exklusiv zugewiesenen Tasks (user_in_charge_id) über die
 * Agent-API, sperrt sie (Lock) und meldet Ergebnis/Rückfrage zurück (agent_summary).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->timestamp('agent_locked_at')->nullable()->after('lifecycle_state_reason');
            $table->string('agent_locked_by')->nullable()->after('agent_locked_at');
            $table->timestamp('agent_completed_at')->nullable()->after('agent_locked_by');
            $table->longText('agent_summary')->nullable()->after('agent_completed_at');
            $table->index('user_in_charge_id', 'planner_tasks_user_in_charge_idx');
            $table->index('agent_locked_at', 'planner_tasks_agent_locked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropIndex('planner_tasks_user_in_charge_idx');
            $table->dropIndex('planner_tasks_agent_locked_idx');
            $table->dropColumn(['agent_locked_at', 'agent_locked_by', 'agent_completed_at', 'agent_summary']);
        });
    }
};
