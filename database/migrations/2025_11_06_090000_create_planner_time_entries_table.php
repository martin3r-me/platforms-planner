<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_time_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('planner_projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('planner_tasks')->cascadeOnDelete();

            $table->date('work_date');
            $table->unsignedSmallInteger('minutes');
            $table->unsignedInteger('rate_cents')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency_code', 3)->default('EUR');
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'task_id']);
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_time_entries');
    }
};


