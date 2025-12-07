<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->boolean('is_forced_frog')->default(false)->after('is_frog');
        });
    }

    public function down(): void
    {
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropColumn('is_forced_frog');
        });
    }
};

