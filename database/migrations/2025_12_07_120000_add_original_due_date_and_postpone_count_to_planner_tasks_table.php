<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dateTime('original_due_date')->nullable()->after('due_date');
            $table->unsignedInteger('postpone_count')->default(0)->after('original_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropColumn(['original_due_date', 'postpone_count']);
        });
    }
};

