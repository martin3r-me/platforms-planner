<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ProjectCanvasService;

/**
 * Ein Projekt ohne Canvas ist keine Option: Bestandsprojekte ohne Canvas bekommen
 * ein leeres Canvas (Kickoff offen). Der Kickoff selbst wird nicht nachtraeglich
 * als erledigt markiert — das Board zeigt "Kickoff nachholen".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planner_projects') || ! Schema::hasTable('planner_project_canvases')) {
            return;
        }

        $service = app(ProjectCanvasService::class);

        PlannerProject::withoutGlobalScopes()
            ->whereNotNull('team_id')
            ->whereNull('deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('planner_project_canvases')
                ->whereColumn('planner_project_canvases.project_id', 'planner_projects.id')
                ->whereNull('planner_project_canvases.deleted_at'))
            ->orderBy('id')
            ->chunkById(100, function ($projects) use ($service) {
                foreach ($projects as $project) {
                    try {
                        $service->createForProject($project, $project->user_id);
                    } catch (\Throwable $e) {
                        Log::warning('Canvas-Backfill fehlgeschlagen', ['project_id' => $project->id, 'error' => $e->getMessage()]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Bewusst nicht umkehrbar.
    }
};
