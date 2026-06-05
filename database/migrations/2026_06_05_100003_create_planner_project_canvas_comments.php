<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planner_project_canvas_comments')) {
            Schema::create('planner_project_canvas_comments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('canvas_id')->constrained('planner_project_canvases')->cascadeOnDelete();
                $table->foreignId('building_block_id')->nullable()->constrained('planner_project_canvas_blocks')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('planner_project_canvas_comments')->cascadeOnDelete();
                $table->text('content');
                $table->timestamps();

                $table->index(['canvas_id', 'building_block_id'], 'ppc_comments_canvas_block_index');
            });
        } else {
            // Table exists but index may be missing (failed on first run)
            try {
                Schema::table('planner_project_canvas_comments', function (Blueprint $table) {
                    $table->index(['canvas_id', 'building_block_id'], 'ppc_comments_canvas_block_index');
                });
            } catch (\Throwable) {
                // Index already exists
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_canvas_comments');
    }
};
