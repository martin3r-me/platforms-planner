<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\UuidV7;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organization_time_periods')) {
            return;
        }

        // PlannerProject.planned_end → organization_time_periods
        if (Schema::hasColumn('planner_projects', 'planned_end')) {
            DB::table('planner_projects')
                ->whereNotNull('planned_end')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('organization_time_periods')
                        ->whereColumn('context_id', 'planner_projects.id')
                        ->where('context_type', 'Platform\\Planner\\Models\\PlannerProject')
                        ->where('note', 'migrated_from_module');
                })
                ->orderBy('id')
                ->chunk(200, function ($projects) {
                    $rows = [];
                    foreach ($projects as $project) {
                        $rows[] = [
                            'uuid' => UuidV7::generate(),
                            'team_id' => $project->team_id,
                            'user_id' => $project->user_id,
                            'context_type' => 'Platform\\Planner\\Models\\PlannerProject',
                            'context_id' => $project->id,
                            'planned_start' => null,
                            'planned_end' => $project->planned_end,
                            'note' => 'migrated_from_module',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (!empty($rows)) {
                        DB::table('organization_time_periods')->insert($rows);
                    }
                });
        }

        // PlannerSprint.start_date + end_date → organization_time_periods
        if (Schema::hasTable('planner_sprints') && Schema::hasColumn('planner_sprints', 'start_date')) {
            DB::table('planner_sprints')
                ->where(function ($q) {
                    $q->whereNotNull('start_date')->orWhereNotNull('end_date');
                })
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('organization_time_periods')
                        ->whereColumn('context_id', 'planner_sprints.id')
                        ->where('context_type', 'Platform\\Planner\\Models\\PlannerSprint')
                        ->where('note', 'migrated_from_module');
                })
                ->orderBy('id')
                ->chunk(200, function ($sprints) {
                    $rows = [];
                    foreach ($sprints as $sprint) {
                        $rows[] = [
                            'uuid' => UuidV7::generate(),
                            'team_id' => $sprint->team_id,
                            'user_id' => $sprint->user_id,
                            'context_type' => 'Platform\\Planner\\Models\\PlannerSprint',
                            'context_id' => $sprint->id,
                            'planned_start' => $sprint->start_date,
                            'planned_end' => $sprint->end_date,
                            'note' => 'migrated_from_module',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (!empty($rows)) {
                        DB::table('organization_time_periods')->insert($rows);
                    }
                });
        }

        // ChangeProject.target_date → organization_time_periods
        if (Schema::hasTable('change_projects') && Schema::hasColumn('change_projects', 'target_date')) {
            DB::table('change_projects')
                ->whereNotNull('target_date')
                ->whereNull('deleted_at')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('organization_time_periods')
                        ->whereColumn('context_id', 'change_projects.id')
                        ->where('context_type', 'Platform\\Change\\Models\\ChangeProject')
                        ->where('note', 'migrated_from_module');
                })
                ->orderBy('id')
                ->chunk(200, function ($projects) {
                    $rows = [];
                    foreach ($projects as $project) {
                        $rows[] = [
                            'uuid' => UuidV7::generate(),
                            'team_id' => $project->team_id,
                            'user_id' => $project->user_id,
                            'context_type' => 'Platform\\Change\\Models\\ChangeProject',
                            'context_id' => $project->id,
                            'planned_start' => null,
                            'planned_end' => $project->target_date,
                            'note' => 'migrated_from_module',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (!empty($rows)) {
                        DB::table('organization_time_periods')->insert($rows);
                    }
                });
        }
    }

    public function down(): void
    {
        DB::table('organization_time_periods')
            ->where('note', 'migrated_from_module')
            ->delete();
    }
};
