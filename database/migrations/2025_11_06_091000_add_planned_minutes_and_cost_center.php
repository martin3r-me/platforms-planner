<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->unsignedInteger('planned_minutes')->nullable()->after('order');
            $table->string('customer_cost_center', 64)->nullable()->after('planned_minutes');
        });

        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->unsignedInteger('planned_minutes')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropColumn('planned_minutes');
        });

        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropColumn(['planned_minutes', 'customer_cost_center']);
        });
    }
};


