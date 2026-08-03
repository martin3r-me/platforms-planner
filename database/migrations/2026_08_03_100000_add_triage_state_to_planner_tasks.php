<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reife-Zustand (Triage-Stufe): triage_done_at gesetzt = ein Triage-Worker hat die Task auf
 * Story-Points + Inhalt geprüft und für die Bearbeitung freigegeben. NULL = noch ungeprüft.
 * Der Backoffice-Claim gated darauf NUR, wenn der Worker require_triage sendet — sonst egal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('planner_tasks', 'triage_done_at')) {
                $table->timestamp('triage_done_at')->nullable()->after('agent_session_id');
                $table->index('triage_done_at', 'planner_tasks_triage_done_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropIndex('planner_tasks_triage_done_idx');
            $table->dropColumn('triage_done_at');
        });
    }
};
