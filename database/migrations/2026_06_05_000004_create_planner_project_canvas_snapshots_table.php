<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_project_canvas_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('canvas_id')->constrained('planner_project_canvases')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot_data');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['canvas_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_canvas_snapshots');
    }
};
