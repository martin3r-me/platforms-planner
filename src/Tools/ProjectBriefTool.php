<?php

namespace Platform\Planner\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\ProjectCanvasAnalysisService;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Services\ProjectCanvasVersioning;
use Platform\Planner\Services\ProjectLogbookService;
use Platform\Planner\Support\ProjectNavSummary;

/**
 * Einstieg fuer Agenten: ein Projekt auf allen drei Achsen in einem Call —
 * Zustand (Canvas), Verlauf (Logbuch), Arbeit (Aufgaben) + Health + naechste Schritte.
 */
class ProjectBriefTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project.brief.GET';
    }

    public function getDescription(): string
    {
        return 'EINSTIEG für ein Projekt: liefert in einem Call die drei Achsen — Zustand (Canvas: Kickoff, Ziel, Kern-Einträge, Vollständigkeit, Änderungen seit dem letzten Logbuch-Eintrag), '
            . 'Verlauf (Logbuch: aktuelle Entscheidungen/Meilensteine/offene Risiken) und Arbeit (offene/überfällige/seitdem erledigte Aufgaben, nächste Fälligkeiten) — plus Health und konkrete nächste Schritte. '
            . 'Pflicht: project_id. Danach gezielt: planner.canvas.*, planner.project.logbook.*, planner.tasks.*.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'ID des Projekts (ERFORDERLICH). Projekte finden: planner.projects.GET.'],
            ],
            'required' => ['project_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (! $context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }
            $project = PlannerProject::find((int) ($arguments['project_id'] ?? 0));
            if (! $project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden. Nutze planner.projects.GET.');
            }
            if (! Gate::forUser($context->user)->allows('view', $project)) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Projekt.');
            }

            $logbookService = app(ProjectLogbookService::class);
            $since = $logbookService->defaultDraftSince($project);
            $lastEntryAt = PlannerProjectLogbookEntry::forProject($project->id)->max('created_at');
            $nav = ProjectNavSummary::for($project);

            // ── Zustand: Canvas ──
            $canvas = app(ProjectCanvasService::class)->forProject($project);
            $canvasData = null;
            if ($canvas) {
                $arr = $canvas->toCanvasArray();
                $labels = collect(config('planner.canvas_block_types', []))->map(fn ($c) => $c['label'] ?? null);
                $blocks = [];
                foreach ($arr['blocks'] as $type => $block) {
                    $entries = collect($block['entries'] ?? []);
                    $blocks[$type] = [
                        'label' => $labels[$type] ?? $block['label'],
                        'count' => $entries->count(),
                        'top' => $entries->take(3)->map(fn ($e) => trim(($e['title'] ?? '') . (! empty($e['content']) ? ': ' . mb_substr($e['content'], 0, 140) : '')))->all(),
                    ];
                }
                $versioning = app(ProjectCanvasVersioning::class);
                $changes = $canvas->snapshots()->exists() ? $versioning->changesSince($canvas, $since) : null;
                $analysis = (new ProjectCanvasAnalysisService())->analyze($canvas);

                $canvasData = [
                    'canvas_id' => $canvas->id,
                    'kickoff_completed' => (bool) $canvas->kickoff_completed_at,
                    'kickoff_completed_at' => $canvas->kickoff_completed_at?->toDateString(),
                    'completeness_percent' => $analysis['completeness_percent'] ?? null,
                    'warnings' => array_slice($analysis['warnings'] ?? [], 0, 5),
                    'blocks' => $blocks,
                    'changes_since_last_logbook_entry' => $changes ? ['summary' => $changes['summary'], 'has_changes' => $changes['has_changes']] : null,
                ];
            }

            // ── Verlauf: Logbuch ──
            $current = PlannerProjectLogbookEntry::forProject($project->id)->current();
            $recent = (clone $current)->orderByDesc('occurred_on')->orderByDesc('id')->limit(5)->get();
            $risks = (clone $current)->where('type', LogbookEntryType::RISK->value)->orderByDesc('occurred_on')->limit(10)->get();
            $entry = fn ($e) => ['id' => $e->id, 'type' => $e->type->value, 'title' => $e->title, 'rationale' => $e->rationale ? mb_substr($e->rationale, 0, 280) : null, 'occurred_on' => $e->occurred_on->toDateString()];

            // ── Arbeit: Aufgaben ──
            $tasks = PlannerTask::withStale()->where('project_id', $project->id);
            $open = (clone $tasks)->where('lifecycle_state', TaskLifecycleState::ACTIVE->value);
            $doneSince = (clone $tasks)->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)->where('lifecycle_state_changed_at', '>=', $since);
            $overdue = (clone $open)->whereNotNull('due_date')->where('due_date', '<', now()->startOfDay());

            // ── Health ──
            $snapshot = PlannerProjectSnapshot::where('project_id', $project->id)->orderByDesc('taken_on')->first();

            // ── Naechste Schritte ──
            $next = [];
            if ($canvasData && ! $canvasData['kickoff_completed']) {
                $next[] = 'Kickoff offen: Canvas mit Ziel, Beteiligten, Nutzen, Risiken füllen (planner.canvas.entries.*), dann planner.canvas.kickoff.POST.';
            }
            $doneCount = (clone $doneSince)->count();
            if ($nav['logbook']['stale'] && ($doneCount > 0 || ($canvasData['changes_since_last_logbook_entry']['has_changes'] ?? false))) {
                $next[] = "Seit dem letzten Logbuch-Eintrag ist etwas passiert ({$doneCount} Aufgaben erledigt" . (($canvasData['changes_since_last_logbook_entry']['has_changes'] ?? false) ? ', Canvas geändert' : '') . ') — Entwurf mit planner.project.logbook.draft.GET erzeugen und mit dem Nutzer kuratieren.';
            }
            if ($overdue->count() > 0) {
                $next[] = 'Überfällige Aufgaben klären (neu terminieren, erledigen oder verwerfen) — planner.tasks.PUT.';
            }
            if ($risks->isNotEmpty() && ! $canvasData) {
                $next[] = 'Offene Risiken prüfen.';
            }

            return ToolResult::success([
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description ? mb_substr($project->description, 0, 500) : null,
                    'lifecycle_state' => $project->lifecycle_state?->value,
                    'project_type' => $project->project_type?->value,
                    'planned_start' => $project->plannedStart()?->toDateString(),
                    'planned_end' => $project->plannedEnd()?->toDateString(),
                ],
                'state_canvas' => $canvasData,
                'history_logbook' => [
                    'last_entry_at' => $lastEntryAt ? \Illuminate\Support\Carbon::parse($lastEntryAt)->toDateString() : null,
                    'days_since_last_entry' => $nav['logbook']['days_since_entry'],
                    'recent' => $recent->map($entry)->all(),
                    'open_risks' => $risks->map($entry)->all(),
                ],
                'work_tasks' => [
                    'open' => (clone $open)->count(),
                    'overdue' => (clone $overdue)->count(),
                    'done_since_last_logbook_entry' => $doneCount,
                    'next_due' => (clone $open)->whereNotNull('due_date')->orderBy('due_date')->limit(5)->get(['id', 'title', 'due_date', 'user_in_charge_id'])
                        ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'due_date' => $t->due_date?->toDateString()])->all(),
                ],
                'health' => $snapshot ? [
                    'score' => $snapshot->health_score,
                    'color' => $snapshot->health_color,
                    'delta' => $snapshot->delta_health_score,
                    'taken_on' => $snapshot->taken_on ? \Illuminate\Support\Carbon::parse($snapshot->taken_on)->toDateString() : null,
                ] : null,
                'since' => $since->toDateString(),
                'next_steps' => $next,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Projekt-Brief: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'project', 'brief', 'canvas', 'logbook', 'overview'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
