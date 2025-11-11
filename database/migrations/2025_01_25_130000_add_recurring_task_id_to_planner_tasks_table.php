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
            $table->foreignId('recurring_task_id')
                ->nullable()
                ->after('task_group_id')
                ->constrained('planner_recurring_tasks')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropForeign(['recurring_task_id']);
            $table->dropColumn('recurring_task_id');
        });
    }
};

