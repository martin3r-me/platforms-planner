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
        Schema::table('planner_recurring_tasks', function (Blueprint $table) {
            $table->boolean('auto_delete_old_tasks')->default(false)->after('is_active');
            $table->boolean('auto_mark_as_done')->default(false)->after('auto_delete_old_tasks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planner_recurring_tasks', function (Blueprint $table) {
            $table->dropColumn(['auto_delete_old_tasks', 'auto_mark_as_done']);
        });
    }
};

