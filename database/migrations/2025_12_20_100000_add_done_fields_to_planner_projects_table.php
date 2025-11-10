<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->boolean('done')->default(false)->after('project_type');
            $table->timestamp('done_at')->nullable()->after('done');
        });
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->dropColumn(['done', 'done_at']);
        });
    }
};

