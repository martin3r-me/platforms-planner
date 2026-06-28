<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planner_project_snapshot_people')) {
            return;
        }

        Schema::create('planner_project_snapshot_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('planner_project_snapshots', 'id', 'ppss_ppl_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users', 'id', 'ppss_ppl_user_fk')
                ->nullOnDelete();

            // Denorm
            $table->string('user_name', 255);

            $table->unsignedInteger('open_tasks')->default(0);
            $table->unsignedInteger('done_tasks')->default(0);
            $table->unsignedInteger('sp_open')->default(0);
            $table->unsignedInteger('sp_done')->default(0);
            $table->unsignedInteger('overdue_tasks')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'snapshot_id'], 'ppss_ppl_user_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_snapshot_people');
    }
};
