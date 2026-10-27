<?php

namespace Platform\Planner\Support;

use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Services\ProjectCanvasAnalysisService;

/**
 * Kennzahlen fuer die Projekt-Reiter (Board · Canvas · Logbuch · Health).
 * Die drei Achsen sollen auf jeder Projektseite sichtbar sein: Zustand (Canvas),
 * Verlauf (Logbuch), Arbeit (Board) — plus Health als Lagebild.
 */
class ProjectNavSummary
{
    /** Ab so vielen Tagen ohne Logbuch-Eintrag gilt das Logbuch als "ruhig". */
    public const LOGBOOK_STALE_DAYS = 14;

    public static function for(PlannerProject $project): array
    {
        // Canvas: genau einer → direkt dorthin, sonst Uebersicht (dort kann man anlegen/waehlen)
        $canvases = PlannerProjectCanvas::forProject($project->id)
            ->where('status', '!=', PlannerProjectCanvas::STATUS_DISCARDED)
            ->orderBy('id')
            ->get(['id', 'project_id', 'name', 'status']);

        $canvasCompleteness = null;
        if ($canvases->count() === 1) {
            $canvas = $canvases->first();
            try {
                $canvasCompleteness = (new ProjectCanvasAnalysisService())->analyze($canvas->load('blocks.entries'))['completeness_percent'] ?? null;
            } catch (\Throwable) {
                $canvasCompleteness = null;
            }
            $canvasUrl = route('planner.projects.canvas.show', [$project, $canvas]);
        } else {
            $canvasUrl = route('planner.projects.canvas.index', $project);
        }

        $lastEntryAt = PlannerProjectLogbookEntry::forProject($project->id)->max('created_at');
        $openRisks = PlannerProjectLogbookEntry::forProject($project->id)
            ->current()
            ->where('type', LogbookEntryType::RISK->value)
            ->count();
        $daysSinceEntry = $lastEntryAt ? (int) \Illuminate\Support\Carbon::parse($lastEntryAt)->startOfDay()->diffInDays(now()->startOfDay()) : null;

        $snapshot = PlannerProjectSnapshot::where('project_id', $project->id)
            ->orderByDesc('taken_on')
            ->first(['health_score', 'health_color']);

        return [
            'canvas' => [
                'url' => $canvasUrl,
                'count' => $canvases->count(),
                'completeness' => $canvasCompleteness !== null ? (int) round($canvasCompleteness) : null,
            ],
            'logbook' => [
                'url' => route('planner.projects.logbook', $project),
                'has_entries' => $lastEntryAt !== null,
                'days_since_entry' => $daysSinceEntry,
                'stale' => $daysSinceEntry === null || $daysSinceEntry >= self::LOGBOOK_STALE_DAYS,
                'open_risks' => $openRisks,
            ],
            'health' => [
                'url' => route('planner.projects.health', $project),
                'score' => $snapshot?->health_score,
                'color' => $snapshot?->health_color,
            ],
        ];
    }
}
