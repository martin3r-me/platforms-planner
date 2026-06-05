<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_project_canvases', function (Blueprint $table) {
            $table->string('visibility', 10)->default('team')->after('status');
            $table->string('public_token', 64)->nullable()->unique()->after('visibility');
            $table->boolean('is_public')->default(false)->after('public_token');
            $table->json('workshop_settings')->nullable()->after('is_public');

            $table->index(['project_id', 'visibility']);
        });

        // Migrate old status values to new ones
        DB::table('planner_project_canvases')
            ->where('status', 'draft')
            ->update(['status' => 'open']);

        DB::table('planner_project_canvases')
            ->where('status', 'active')
            ->update(['status' => 'open']);

        DB::table('planner_project_canvases')
            ->where('status', 'archived')
            ->update(['status' => 'discarded']);
    }

    public function down(): void
    {
        // Reverse status migration
        DB::table('planner_project_canvases')
            ->where('status', 'open')
            ->update(['status' => 'active']);

        DB::table('planner_project_canvases')
            ->where('status', 'discarded')
            ->update(['status' => 'archived']);

        Schema::table('planner_project_canvases', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'visibility']);
            $table->dropColumn(['visibility', 'public_token', 'is_public', 'workshop_settings']);
        });
    }
};
