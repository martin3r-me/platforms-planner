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
        // 1. Fix planner_tasks.project_id: von nullOnDelete auf cascade
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->foreign('project_id')
                ->references('id')
                ->on('planner_projects')
                ->onDelete('cascade');
        });

        // 2. Fix planner_project_users.project_id: Foreign Key mit cascade hinzufügen
        Schema::table('planner_project_users', function (Blueprint $table) {
            // Foreign Key entfernen falls vorhanden
            try {
                $table->dropForeign(['project_id']);
            } catch (\Exception $e) {
                // Foreign Key existiert nicht, das ist ok
            }
            
            // Foreign Key mit cascade hinzufügen
            $table->foreign('project_id')
                ->references('id')
                ->on('planner_projects')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback planner_tasks.project_id: zurück zu nullOnDelete
        Schema::table('planner_tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->foreign('project_id')
                ->references('id')
                ->on('planner_projects')
                ->nullOnDelete();
        });

        // Rollback planner_project_users.project_id: Foreign Key entfernen (wenn in up() hinzugefügt)
        Schema::table('planner_project_users', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
    }
};

