<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_project_canvas_workshop_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('canvas_id')->constrained('planner_project_canvases')->cascadeOnDelete();
            $table->foreignId('building_block_id')->nullable()->constrained('planner_project_canvas_blocks')->cascadeOnDelete();
            $table->string('title')->default('');
            $table->text('content')->nullable();
            $table->string('color', 20)->default('yellow');
            $table->string('type', 20)->default('note');
            $table->float('position_x');
            $table->float('position_y');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_canvas_workshop_notes');
    }
};
