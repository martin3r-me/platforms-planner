<?php

namespace Platform\Planner\Support;

use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Enums\TaskStoryPoints as StoryPoints;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\ProjectCanvasAnalysisService;
use Platform\Planner\Services\ProjectCanvasService;

/**
 * Daten fuer den Projekt-Reiter "Ueberblick" (frueher die linke Sidebar des Boards):
 * Zeit/Budget, Arbeit, Canvas-Briefing, Beteiligte, Aktivitaet, Logbuch.
 */
class ProjectDashboardData
{
    /** @return array{canvasInfo: array, dashboardData: array} */
    public static function build(PlannerProject $project): array
    {
        // === CANVAS-INFO (für Header + Dashboard) ===
        $canvas = app(ProjectCanvasService::class)->forProject($project);
        $canvasInfo = null;
        $canvasAnalysis = null;
        if ($canvas) {
            $canvasAnalysis = (new ProjectCanvasAnalysisService())->analyze($canvas);
            $canvasInfo = [
                'exists' => true,
                'completeness' => $canvasAnalysis['completeness_percent'] ?? null,
                'status' => $canvasAnalysis['status'] ?? 'unknown',
                'warnings_count' => isset($canvasAnalysis['warnings']) ? count($canvasAnalysis['warnings']) : 0,
                'route' => route('planner.projects.canvas.show', [$project, $canvas]),
                'name' => $canvas->name,
                'kickoff_done' => (bool) $canvas->kickoff_completed_at,
            ];
        } else {
            $canvasInfo = [
                'exists' => false,
                'completeness' => null,
                'status' => 'missing',
                'warnings_count' => 0,
                'route' => null,
                'name' => null,
                'kickoff_done' => false,
            ];
        }

        // === DASHBOARD-DATEN (für die linke Sidebar) ===
        // Task-Counts (eine Query)
        $taskCounts = PlannerTask::where('project_id', $project->id)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN lifecycle_state = 'aktiv' THEN 1 ELSE 0 END) as open_count")
                ->selectRaw("SUM(CASE WHEN lifecycle_state = 'erledigt' THEN 1 ELSE 0 END) as done_count")
                ->first();

        $pointsData = PlannerTask::where('project_id', $project->id)
            ->get(['lifecycle_state', 'story_points']);

        $openPoints = $pointsData->filter(fn ($t) => $t->lifecycle_state === TaskLifecycleState::ACTIVE)->sum(
            fn ($t) => $t->story_points instanceof StoryPoints ? $t->story_points->points() : 0
        );
        $donePoints = $pointsData->filter(fn ($t) => $t->lifecycle_state === TaskLifecycleState::COMPLETED)->sum(
            fn ($t) => $t->story_points instanceof StoryPoints ? $t->story_points->points() : 0
        );

        $overdueTasks = PlannerTask::with('userInCharge')
            ->where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_date' => $t->due_date,
                'days_overdue' => (int) now()->diffInDays($t->due_date),
                'assignee' => $t->userInCharge?->name,
            ]);

        $slotsBreakdown = PlannerProjectSlot::where('project_id', $project->id)
            ->withCount([
                'tasks as open_count' => fn ($q) => $q->where('lifecycle_state', TaskLifecycleState::ACTIVE->value),
                'tasks as done_count' => fn ($q) => $q->where('lifecycle_state', TaskLifecycleState::COMPLETED->value),
            ])
            ->orderBy('order')
            ->get();

        $canvasData = $canvas ? [
            'name' => $canvas->name,
            'id' => $canvas->id,
            'analysis' => $canvasAnalysis,
            'route' => $canvasInfo['route'],
        ] : null;

        $canvasBriefing = null;
        if ($canvas) {
            $blockConfig = config('planner.canvas_block_types', []);
            $byType = $canvas->blocks->keyBy('block_type');
            $briefing = [];
            foreach ($blockConfig as $key => $cfg) {
                $block = $byType->get($key);
                $entries = $block ? $block->entries->map(fn ($e) => [
                    'title' => $e->title,
                    'content' => $e->content,
                    'type' => $e->entry_type,
                ])->all() : [];
                $briefing[$key] = [
                    'key' => $key,
                    'label' => $cfg['label'] ?? $key,
                    'description' => $cfg['description'] ?? null,
                    'has_block' => (bool) $block,
                    'entries' => $entries,
                    'count' => count($entries),
                ];
            }
            $canvasBriefing = $briefing;
        }

        // Beteiligte = Aufgaben-Zuständige des Projekts (+ Ersteller). Keine
        // Mitgliedschaft mehr — Zugriff steuert der Graph.
        $participantIds = PlannerTask::where('project_id', $project->id)
            ->whereNotNull('user_in_charge_id')
            ->distinct()
            ->pluck('user_in_charge_id')
            ->push($project->user_id)
            ->filter()
            ->unique()
            ->values();
        $participantUsers = \Platform\Core\Models\User::whereIn('id', $participantIds)->get()->keyBy('id');

        $teamMembers = $participantIds->map(fn ($uid) => [
            'name' => $participantUsers[$uid]?->name ?? 'Unbekannt',
            'role' => (int) $uid === (int) $project->user_id ? 'owner' : 'beteiligt',
            'open_tasks' => PlannerTask::where('project_id', $project->id)
                ->where('user_in_charge_id', $uid)
                ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
                ->count(),
        ]);

        $activities = $project->activities()->with('user')->latest()->limit(10)->get();

        // === LOGBUCH (Verlauf) — juengste Eintraege, offene Risiken, Ruhe-Hinweis ===
        $logbookRecent = \Platform\Planner\Models\PlannerProjectLogbookEntry::forProject($project->id)
            ->current()
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(3)
            ->get(['id', 'project_id', 'type', 'title', 'occurred_on']);
        $logbookRisks = \Platform\Planner\Models\PlannerProjectLogbookEntry::forProject($project->id)
            ->current()
            ->where('type', \Platform\Planner\Enums\LogbookEntryType::RISK->value)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'project_id', 'type', 'title', 'rationale', 'occurred_on']);
        $logbookLastAt = \Platform\Planner\Models\PlannerProjectLogbookEntry::forProject($project->id)->max('created_at');
        $logbookSince = app(\Platform\Planner\Services\ProjectLogbookService::class)->defaultDraftSince($project);
        $logbookDaysSince = $logbookLastAt
            ? (int) \Illuminate\Support\Carbon::parse($logbookLastAt)->startOfDay()->diffInDays(now()->startOfDay())
            : null;
        $logbookData = [
            'recent' => $logbookRecent,
            'risks' => $logbookRisks,
            'days_since' => $logbookDaysSince,
            'stale' => $logbookDaysSince === null || $logbookDaysSince >= \Platform\Planner\Support\ProjectNavSummary::LOGBOOK_STALE_DAYS,
            // Was seit dem letzten Eintrag passiert ist — Anlass fuer einen Entwurf
            'done_since' => PlannerTask::where('project_id', $project->id)
                ->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)
                ->where('lifecycle_state_changed_at', '>=', $logbookSince)
                ->count(),
            'url' => route('planner.projects.logbook', $project),
        ];

        $dashboardData = [
            'planned_hours'  => $project->totalPlannedHours(),
            'logged_minutes' => $project->totalLoggedMinutes(),
            'logged_hours'   => round($project->totalLoggedMinutes() / 60, 2),
            'billed_hours'   => round($project->billedMinutes() / 60, 2),
            'unbilled_hours' => round($project->unbilledMinutes() / 60, 2),
            'planned_start' => $project->plannedStart(),
            'planned_end'   => $project->plannedEnd(),
            'budget_amount'  => $project->budget_amount,
            'hourly_rate'    => $project->hourly_rate,
            'currency'       => $project->currency ?? 'EUR',
            'billing_method' => $project->billing_method,
            'budget_used'    => $project->hourly_rate
                ? round(($project->totalLoggedMinutes() / 60) * (float) $project->hourly_rate, 2)
                : null,
            'open_count'    => (int) $taskCounts->open_count,
            'done_count'    => (int) $taskCounts->done_count,
            'total_count'   => (int) $taskCounts->total,
            'open_points'   => $openPoints,
            'done_points'   => $donePoints,
            'overdue_tasks' => $overdueTasks,
            'slots' => $slotsBreakdown,
            'canvas' => $canvasData,
            'canvas_briefing' => $canvasBriefing,
            'team_members' => $teamMembers,
            'activities' => $activities,
            'logbook' => $logbookData,
        ];


        return ['canvasInfo' => $canvasInfo, 'dashboardData' => $dashboardData];
    }
}
