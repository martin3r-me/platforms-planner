<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canvas-Status vereinheitlichen: Tools/Uebersicht schrieben draft/active/archived,
 * Modell und Detailseite kennen nur open/completed/discarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planner_project_canvases')) {
            return;
        }

        DB::table('planner_project_canvases')->whereIn('status', ['draft', 'active'])->update(['status' => 'open']);
        DB::table('planner_project_canvases')->where('status', 'archived')->update(['status' => 'completed']);
        DB::table('planner_project_canvases')
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['open', 'completed', 'discarded']))
            ->update(['status' => 'open']);
    }

    public function down(): void
    {
        // Nicht umkehrbar — die Alt-Werte waren nie gueltig.
    }
};
