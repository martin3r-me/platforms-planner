<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aendert alle team_id FKs im Planner-Modul von nullOnDelete/restrict
 * auf cascadeOnDelete, damit Team-Loeschungen sauber durchkaskadieren.
 */
return new class extends Migration
{
    private array $tables = [
        'planner_projects',
        'planner_sprints',
        'planner_sprint_slots',
        'planner_delegated_task_groups',
        'planner_task_groups',
        'planner_tasks',
        'planner_project_slots',
        'planner_recurring_tasks',
        'planner_project_billing_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign($this->fkName($table));
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreign('team_id', $this->fkName($table))
                  ->references('id')
                  ->on('teams')
                  ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign($this->fkName($table));
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreign('team_id', $this->fkName($table))
                  ->references('id')
                  ->on('teams')
                  ->nullOnDelete();
            });
        }
    }

    private function fkName(string $table): string
    {
        return $table . '_team_id_foreign';
    }
};
