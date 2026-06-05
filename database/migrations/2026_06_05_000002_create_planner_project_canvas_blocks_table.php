<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_project_canvas_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('canvas_id')->constrained('planner_project_canvases')->cascadeOnDelete();
            $table->string('block_type', 50);
            $table->string('label');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['canvas_id', 'block_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_canvas_blocks');
    }
};
