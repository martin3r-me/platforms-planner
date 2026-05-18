<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->timestamp('last_viewed_at')->nullable()->after('updated_at');
            $table->index(['team_id', 'last_viewed_at'], 'planner_projects_team_last_viewed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropIndex('planner_projects_team_last_viewed_idx');
            $table->dropColumn('last_viewed_at');
        });
    }
};
