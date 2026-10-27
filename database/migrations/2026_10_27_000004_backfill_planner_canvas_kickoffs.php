<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ProjectCanvasAnalysisService;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Services\ProjectCanvasVersioning;

/**
 * Bestandsprojekte, deren Canvas bei Einfuehrung des Kickoffs schon vollstaendig
 * war, bekommen den Kickoff nachgetragen: Kickoff-Version (Vergleichsbasis) +
 * Meilenstein im Logbuch, im Namen des Projekt-Owners. Unvollstaendige Canvases
 * bleiben offen ("Kickoff nachholen").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('planner_project_canvases', 'kickoff_completed_at')
            || ! Schema::hasTable('planner_project_logbook_entries')) {
            return;
        }

        $canvasService = app(ProjectCanvasService::class);
        $analysis = new ProjectCanvasAnalysisService();
        $versioning = app(ProjectCanvasVersioning::class);
        $note = 'Nachgetragen: Das Canvas war bei Einführung des Kickoffs bereits vollständig — dieser Stand gilt als Kickoff.';

        PlannerProject::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('lifecycle_state', ['aktiv', 'ruhend'])
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($projects) use ($canvasService, $analysis, $versioning, $note) {
                foreach ($projects as $project) {
                    try {
                        $canvas = $canvasService->forProject($project);
                        if (! $canvas || $canvas->kickoff_completed_at || ! $project->user) {
                            continue;
                        }
                        if ((float) ($analysis->analyze($canvas)['completeness_percent'] ?? 0) < 100) {
                            continue;
                        }
                        $versioning->completeKickoff($canvas, $project->user, $note);
                    } catch (\Throwable $e) {
                        Log::warning('Kickoff-Backfill fehlgeschlagen', ['project_id' => $project->id, 'error' => $e->getMessage()]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Bewusst nicht umkehrbar (Logbuch ist append-only).
    }
};
