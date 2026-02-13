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
        if (!Schema::hasTable('planner_projects')) {
            Schema::create('planner_projects', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->unsignedInteger('planned_minutes')->nullable();
                $table->string('customer_cost_center', 64)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->string('project_type', 20)->default('internal');
                $table->boolean('done')->default(false);
                $table->timestamp('done_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('project_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planner_projects');
    }
};
