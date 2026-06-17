<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->string('kind', 20)->default('project')->after('project_type');
            $table->string('status', 20)->default('aktiv')->after('kind');

            $table->index('kind');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropIndex(['status']);
            $table->dropColumn(['kind', 'status']);
        });
    }
};
