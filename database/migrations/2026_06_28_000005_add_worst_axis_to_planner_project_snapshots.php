<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_project_snapshots', function (Blueprint $table) {
            // Welche Achse hat heute den niedrigsten Score? (strategy|progress|burn|null)
            $table->string('worst_axis', 16)->nullable()->after('health_color');
            // Pro-Achse Detail-Scores als JSON: {"strategy": 30, "progress": 100, "burn": 90}
            $table->json('axis_scores')->nullable()->after('worst_axis');
        });
    }

    public function down(): void
    {
        Schema::table('planner_project_snapshots', function (Blueprint $table) {
            $table->dropColumn(['worst_axis', 'axis_scores']);
        });
    }
};
