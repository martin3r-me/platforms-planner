<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Setzt last_viewed_at fuer alle bestehenden Records auf now(),
 * damit die Staleness-Uhr ab heute fuer alle tickt.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('planner_projects')
            ->whereNull('last_viewed_at')
            ->update(['last_viewed_at' => $now]);

        DB::table('planner_tasks')
            ->whereNull('last_viewed_at')
            ->update(['last_viewed_at' => $now]);
    }

    public function down(): void
    {
        DB::table('planner_projects')->update(['last_viewed_at' => null]);
        DB::table('planner_tasks')->update(['last_viewed_at' => null]);
    }
};
