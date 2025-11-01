<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        // Zuerst: Verwaiste Einträge löschen (project_id verweist auf nicht-existierende Projekte)
        DB::statement("
            DELETE ppu FROM planner_project_users ppu
            LEFT JOIN planner_projects pp ON ppu.project_id = pp.id
            WHERE ppu.project_id IS NOT NULL 
            AND pp.id IS NULL
        ");
        
        // Prüfe ob Foreign Key existiert und entferne ihn falls vorhanden
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'planner_project_users'
            AND COLUMN_NAME = 'project_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        foreach ($foreignKeys as $foreignKey) {
            DB::statement("ALTER TABLE `planner_project_users` DROP FOREIGN KEY `{$foreignKey->CONSTRAINT_NAME}`");
        }
        
        // Foreign Key mit cascade hinzufügen
        Schema::table('planner_project_users', function (Blueprint $table) {
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

