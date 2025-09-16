<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->string('project_type', 20)->default('internal')->after('team_id');
            $table->index('project_type');
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropIndex(['project_type']);
            $table->dropColumn('project_type');
        });
    }
};


