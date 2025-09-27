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
            // Add project_slot_id after project_id
            $table->foreignId('project_slot_id')->nullable()->constrained('planner_project_slots')->nullOnDelete()->after('project_id');
            
            // Add project_slot_order after sprint_slot_order
            $table->integer('project_slot_order')->default(0)->after('sprint_slot_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            // Remove project_slot_id
            $table->dropForeign(['project_slot_id']);
            $table->dropColumn('project_slot_id');
            
            // Remove project_slot_order
            $table->dropColumn('project_slot_order');
        });
    }
};
