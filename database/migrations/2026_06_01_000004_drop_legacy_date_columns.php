<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('planner_projects', 'planned_end')) {
            Schema::table('planner_projects', function (Blueprint $table) {
                $table->dropColumn('planned_end');
            });
        }

        if (Schema::hasTable('planner_sprints')) {
            $columns = [];
            if (Schema::hasColumn('planner_sprints', 'start_date')) {
                $columns[] = 'start_date';
            }
            if (Schema::hasColumn('planner_sprints', 'end_date')) {
                $columns[] = 'end_date';
            }
            if (!empty($columns)) {
                Schema::table('planner_sprints', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('change_projects') && Schema::hasColumn('change_projects', 'target_date')) {
            Schema::table('change_projects', function (Blueprint $table) {
                $table->dropColumn('target_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('planner_projects', function (Blueprint $table) {
            $table->date('planned_end')->nullable();
        });

        if (Schema::hasTable('planner_sprints')) {
            Schema::table('planner_sprints', function (Blueprint $table) {
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
            });
        }

        if (Schema::hasTable('change_projects')) {
            Schema::table('change_projects', function (Blueprint $table) {
                $table->date('target_date')->nullable();
            });
        }
    }
};
