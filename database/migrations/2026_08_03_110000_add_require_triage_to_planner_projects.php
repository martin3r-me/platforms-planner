<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Triage-Pflicht als Eigenschaft der QUELLE (Projekt), nicht des Workers: verlangt ein
 * Projekt Triage, führt der Backoffice-Worker nur triagierte Tasks (triage_done_at) aus.
 * Default false → strukturierter interner Eingang braucht standardmäßig keine Triage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('planner_projects', 'require_triage')) {
                $table->boolean('require_triage')->default(false)->after('agent_lessons');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropColumn('require_triage');
        });
    }
};
