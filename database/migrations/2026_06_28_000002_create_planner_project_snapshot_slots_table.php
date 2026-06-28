<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planner_project_snapshot_slots')) {
            return;
        }

        Schema::create('planner_project_snapshot_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('planner_project_snapshots', 'id', 'ppss_slot_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('slot_id')->nullable()
                ->constrained('planner_project_slots', 'id', 'ppss_slot_slot_fk')
                ->nullOnDelete();

            // Denorm — bleiben erhalten auch wenn Slot spaeter geloescht wird
            $table->string('slot_name', 255);
            $table->integer('slot_order')->default(0);

            $table->unsignedInteger('open_tasks')->default(0);
            $table->unsignedInteger('done_tasks')->default(0);
            $table->unsignedInteger('total_tasks')->default(0);

            $table->timestamps();

            $table->index(['slot_id', 'snapshot_id'], 'ppss_slot_id_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_project_snapshot_slots');
    }
};
