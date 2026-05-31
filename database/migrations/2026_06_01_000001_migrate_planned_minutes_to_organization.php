<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migriert planned_minutes von planner_tasks und planner_projects
 * in die zentrale organization_time_planned Tabelle.
 *
 * Idempotent: Prüft via note-Marker ob bereits migriert.
 * estimated_hours wird NICHT separat migriert (ist redundant = planned_minutes/60).
 */
return new class extends Migration
{
    public function up(): void
    {
        $marker = 'migrated_from_planner';

        // --- Tasks ---
        DB::table('planner_tasks')
            ->whereNotNull('planned_minutes')
            ->where('planned_minutes', '>', 0)
            ->orderBy('id')
            ->chunk(200, function ($tasks) use ($marker) {
                foreach ($tasks as $task) {
                    $exists = DB::table('organization_time_planned')
                        ->where('context_type', 'Platform\\Planner\\Models\\PlannerTask')
                        ->where('context_id', $task->id)
                        ->where('note', $marker)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('organization_time_planned')->insert([
                        'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                        'team_id' => $task->team_id,
                        'user_id' => $task->user_id,
                        'context_type' => 'Platform\\Planner\\Models\\PlannerTask',
                        'context_id' => $task->id,
                        'planned_minutes' => $task->planned_minutes,
                        'note' => $marker,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // --- Projects ---
        DB::table('planner_projects')
            ->whereNotNull('planned_minutes')
            ->where('planned_minutes', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(200, function ($projects) use ($marker) {
                foreach ($projects as $project) {
                    $exists = DB::table('organization_time_planned')
                        ->where('context_type', 'Platform\\Planner\\Models\\PlannerProject')
                        ->where('context_id', $project->id)
                        ->where('note', $marker)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('organization_time_planned')->insert([
                        'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                        'team_id' => $project->team_id,
                        'user_id' => $project->user_id,
                        'context_type' => 'Platform\\Planner\\Models\\PlannerProject',
                        'context_id' => $project->id,
                        'planned_minutes' => $project->planned_minutes,
                        'note' => $marker,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('organization_time_planned')
            ->where('note', 'migrated_from_planner')
            ->delete();
    }
};
