<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canvas-Versionierung + Kickoff.
 *  - Snapshots tragen Herkunft (source), Bezeichnung (label) und einen Inhalts-Hash,
 *    damit automatische Staende keine Duplikate erzeugen.
 *  - Der Canvas merkt sich, wann der Kickoff abgeschlossen wurde (v1 = Kickoff-Stand).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planner_project_canvas_snapshots')) {
            Schema::table('planner_project_canvas_snapshots', function (Blueprint $table) {
                if (! Schema::hasColumn('planner_project_canvas_snapshots', 'source')) {
                    $table->string('source', 16)->default('manual')->after('version');
                }
                if (! Schema::hasColumn('planner_project_canvas_snapshots', 'label')) {
                    $table->string('label')->nullable()->after('source');
                }
                if (! Schema::hasColumn('planner_project_canvas_snapshots', 'content_hash')) {
                    $table->string('content_hash', 64)->nullable()->after('label');
                }
            });
        }

        if (Schema::hasTable('planner_project_canvases')) {
            Schema::table('planner_project_canvases', function (Blueprint $table) {
                if (! Schema::hasColumn('planner_project_canvases', 'kickoff_completed_at')) {
                    $table->timestamp('kickoff_completed_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('planner_project_canvases', 'kickoff_completed_by_user_id')) {
                    $table->foreignId('kickoff_completed_by_user_id')->nullable()->after('kickoff_completed_at')
                        ->constrained('users', 'id', 'ppc_kickoff_user_fk')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('planner_project_canvases', 'kickoff_completed_by_user_id')) {
            Schema::table('planner_project_canvases', function (Blueprint $table) {
                $table->dropForeign('ppc_kickoff_user_fk');
                $table->dropColumn(['kickoff_completed_at', 'kickoff_completed_by_user_id']);
            });
        }
        if (Schema::hasColumn('planner_project_canvas_snapshots', 'source')) {
            Schema::table('planner_project_canvas_snapshots', function (Blueprint $table) {
                $table->dropColumn(['source', 'label', 'content_hash']);
            });
        }
    }
};
