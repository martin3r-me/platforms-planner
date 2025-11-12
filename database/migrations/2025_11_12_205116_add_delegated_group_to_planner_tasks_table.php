<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->foreignId('delegated_group_id')->nullable()->after('task_group_id')->constrained('planner_delegated_task_groups')->nullOnDelete();
            $table->integer('delegated_group_order')->default(0)->after('delegated_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropForeign(['delegated_group_id']);
            $table->dropColumn(['delegated_group_id', 'delegated_group_order']);
        });
    }
};

