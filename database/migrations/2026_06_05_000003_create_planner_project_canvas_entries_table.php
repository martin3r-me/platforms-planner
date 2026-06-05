<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_project_canvas_entries', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('block_id')->constrained('planner_project_canvas_blocks')->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('entry_type', 20)->default('text'); // text, date, person, amount
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['block_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_canvas_entries');
    }
};
