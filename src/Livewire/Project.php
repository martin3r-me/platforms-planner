<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\StoryPoints;
use Platform\Planner\Livewire\Concerns\QuickTogglesDone;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Services\ProjectCanvasAnalysisService;

class Project extends Component
{
    use QuickTogglesDone;

    public PlannerProject $project;
    public $sprint; // Aktueller Sprint des Projekts
    public bool $showDoneColumn = false; // Erledigt-Spalte ein/ausblenden

    public string $activeTab = 'dashboard';

    protected $queryString = [
        'activeTab' => ['except' => 'dashboard', 'as' => 'tab'],
    ];

    // Filter
    public array $filterTagIds = []; // Tag-IDs zum Filtern
    public ?string $filterColor = null; // Farbe zum Filtern

    #[On('updateProject')]
    public function updateProject()
    {

    }

    #[On('projectSlotUpdated')]
    public function projectSlotUpdated()
    {
        // Board neu laden nach Slot-Änderungen
        $this->mount($this->project);
    }


    public function mount(PlannerProject $plannerProject)
    {
        $this->project = $plannerProject;

        // Berechtigung prüfen - User muss Projekt-Mitglied sein
        $this->authorize('view', $this->project);

        // Staleness-Tracking
        $this->project->recordView();

        // Sprints werden nicht mehr geladen - nur Project-Slots
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->project),
            'modelId' => $this->project->id,
            'subject' => $this->project->name,
            'description' => $this->project->description ?? '',
            'url' => route('planner.projects.show', $this->project),
            'source' => 'planner.project.view',
            'recipients' => [],
            'capabilities' => [
                'manage_channels' => true,
                'threads' => false,
            ],
            'meta' => [
                'project_type' => $this->project->project_type,
                'created_at' => $this->project->created_at,
            ],
        ]);

        $this->dispatch('terminal:app:activity');
        $this->dispatch('terminal:app:files');

        // Organization-Kontext setzen - beides erlauben: Zeiten + Entity-Verknüpfung + Dimensionen
        $this->dispatch('organization', [
            'context_type' => get_class($this->project),
            'context_id' => $this->project->id,
            'allow_time_entry' => true,
            'allow_entities' => true,
            'allow_dimensions' => true,
            // Verfügbare Relations für Children-Cascade (z.B. Tasks mit/ohne Slots)
            'include_children_relations' => ['tasks', 'projectSlots.tasks'],
        ]);

        // KeyResult-Kontext setzen - ermöglicht Verknüpfung von KeyResults mit diesem Project
        $this->dispatch('keyresult', [
            'context_type' => get_class($this->project),
            'context_id' => $this->project->id,
        ]);

        // Tags-Tab im Terminal aktivieren
        $this->dispatch('terminal:app:tags');

        // Extra-Fields-Kontext setzen (für Modal-Definitionen)
        $this->dispatch('extrafields', [
            'context_type' => get_class($this->project),
            'context_id' => $this->project->id,
        ]);
    }

    public function render()
    {
        $user = Auth::user();

        // === SHARED DATA (beide Tabs) ===

        // Entity-Verknüpfungen laden via DimensionLink
        $linkedEntities = collect();

        $entityLinks = \Platform\Organization\Services\EntityDimensionBridge::linksForLinkables(
            ['project', 'planner_project', get_class($this->project)],
            [$this->project->id]
        );
        foreach ($entityLinks as $link) {
            $linkedEntities->push([
                'entity_name' => $link->entity?->name ?? 'Unbekannt',
                'entity_type' => $link->entity?->type?->name ?? '',
            ]);
        }

        $linkedEntities = $linkedEntities->unique('entity_name');

        // Aktuelle Rolle des Users im Projekt ermitteln
        $projectUser = $this->project->projectUsers()
            ->where('user_id', $user->id)
            ->first();
        $currentUserRole = $projectUser?->role;

        // Offene Aufgaben dieses Users im Projekt (zählt Tasks mit oder ohne Slot)
        $userOpenTaskCount = PlannerTask::where('project_id', $this->project->id)
            ->where('user_in_charge_id', $user->id)
            ->where('is_done', false)
            ->count();

        $hasAnyTasks = $userOpenTaskCount > 0 || PlannerTask::where('project_id', $this->project->id)
            ->where('user_in_charge_id', $user->id)
            ->exists();

        $permissions = [
            'view' => $user->can('view', $this->project),
            'update' => $user->can('update', $this->project),
            'delete' => $user->can('delete', $this->project),
            'settings' => $user->can('settings', $this->project),
            'invite' => $user->can('invite', $this->project),
        ];

        $allProjectUsers = $this->project->projectUsers()->with('user')->get();

        // === CANVAS-INFO (für Header + Dashboard) ===
        $canvas = $this->project->canvases()->first();
        $canvasInfo = null;
        $canvasAnalysis = null;
        if ($canvas) {
            $canvasAnalysis = (new ProjectCanvasAnalysisService())->analyze($canvas);
            $canvasInfo = [
                'exists' => true,
                'completeness' => $canvasAnalysis['completeness_percent'] ?? null,
                'status' => $canvasAnalysis['status'] ?? 'unknown',
                'warnings_count' => isset($canvasAnalysis['warnings']) ? count($canvasAnalysis['warnings']) : 0,
                'route' => route('planner.projects.canvas.show', [$this->project, $canvas]),
                'name' => $canvas->name,
            ];
        } else {
            $canvasInfo = [
                'exists' => false,
                'completeness' => null,
                'status' => 'missing',
                'warnings_count' => 0,
                'route' => null,
                'name' => null,
            ];
        }

        // === DASHBOARD TAB ===
        if ($this->activeTab === 'dashboard') {
            // Task-Counts (eine Query)
            $taskCounts = PlannerTask::where('project_id', $this->project->id)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN is_done = 0 THEN 1 ELSE 0 END) as open_count')
                ->selectRaw('SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) as done_count')
                ->first();

            $pointsData = PlannerTask::where('project_id', $this->project->id)
                ->get(['is_done', 'story_points']);

            $openPoints = $pointsData->filter(fn ($t) => !$t->is_done)->sum(
                fn ($t) => $t->story_points instanceof StoryPoints ? $t->story_points->points() : 0
            );
            $donePoints = $pointsData->filter(fn ($t) => $t->is_done)->sum(
                fn ($t) => $t->story_points instanceof StoryPoints ? $t->story_points->points() : 0
            );

            // Überfällige Tasks
            $overdueTasks = PlannerTask::with('userInCharge')
                ->where('project_id', $this->project->id)
                ->where('is_done', false)
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

            // Slots-Breakdown
            $slotsBreakdown = PlannerProjectSlot::where('project_id', $this->project->id)
                ->withCount([
                    'tasks as open_count' => fn ($q) => $q->where('is_done', false),
                    'tasks as done_count' => fn ($q) => $q->where('is_done', true),
                ])
                ->orderBy('order')
                ->get();

            // Canvas (Daten aus shared section wiederverwenden)
            $canvasData = $canvas ? [
                'name' => $canvas->name,
                'id' => $canvas->id,
                'analysis' => $canvasAnalysis,
                'route' => $canvasInfo['route'],
            ] : null;

            // Canvas-Briefing: strukturierten Inhalt nach Block-Typ aufbereiten.
            // Blocks + Entries sind durch analyze() bereits eager-loaded.
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

            // Team
            $teamMembers = $allProjectUsers->map(fn ($pu) => [
                'name' => $pu->user?->name ?? 'Unbekannt',
                'role' => $pu->role,
                'open_tasks' => PlannerTask::where('project_id', $this->project->id)
                    ->where('user_in_charge_id', $pu->user_id)
                    ->where('is_done', false)
                    ->count(),
            ]);

            // Aktivitäten
            $activities = $this->project->activities()->with('user')->latest()->limit(10)->get();

            $dashboardData = [
                // Zeit
                'planned_hours'  => $this->project->totalPlannedHours(),
                'logged_minutes' => $this->project->totalLoggedMinutes(),
                'logged_hours'   => round($this->project->totalLoggedMinutes() / 60, 2),
                'billed_hours'   => round($this->project->billedMinutes() / 60, 2),
                'unbilled_hours' => round($this->project->unbilledMinutes() / 60, 2),

                // Timeline
                'planned_start' => $this->project->plannedStart(),
                'planned_end'   => $this->project->plannedEnd(),

                // Budget
                'budget_amount'  => $this->project->budget_amount,
                'hourly_rate'    => $this->project->hourly_rate,
                'currency'       => $this->project->currency ?? 'EUR',
                'billing_method' => $this->project->billing_method,
                'budget_used'    => $this->project->hourly_rate
                    ? round(($this->project->totalLoggedMinutes() / 60) * (float) $this->project->hourly_rate, 2)
                    : null,

                // Tasks
                'open_count'    => (int) $taskCounts->open_count,
                'done_count'    => (int) $taskCounts->done_count,
                'total_count'   => (int) $taskCounts->total,
                'open_points'   => $openPoints,
                'done_points'   => $donePoints,
                'overdue_tasks' => $overdueTasks,

                // Slots
                'slots' => $slotsBreakdown,

                // Canvas
                'canvas' => $canvasData,
                'canvas_briefing' => $canvasBriefing,

                // Team
                'team_members' => $teamMembers,

                // Aktivitäten
                'activities' => $activities,
            ];

            return view('planner::livewire.project', [
                'groups' => collect(),
                'linkedEntities' => $linkedEntities,
                'currentUserRole' => $currentUserRole,
                'userOpenTaskCount' => $userOpenTaskCount,
                'hasAnyTasks' => $hasAnyTasks,
                'permissions' => $permissions,
                'allProjectUsers' => $allProjectUsers,
                'availableFilterTags' => collect(),
                'availableFilterColors' => collect(),
                'dashboardData' => $dashboardData,
                'canvasInfo' => $canvasInfo,
            ])->layout('platform::layouts.app');
        }

        // === BOARD TAB ===

        // === 1. BACKLOG ===
        $backlogTasks = PlannerTask::with(['tags', 'contextColors', 'userInCharge', 'project'])
            ->where('project_id', $this->project->id)
            ->whereNull('project_slot_id')
            ->where('is_done', false)
            ->orderBy('project_slot_order')
            ->get();

        $backlog = (object) [
            'id' => null,
            'label' => 'Backlog',
            'isBacklog' => true,
            'tasks' => $backlogTasks,
            'open_count' => $backlogTasks->count(),
            'open_points' => $backlogTasks->sum(
                fn ($task) => $task->story_points instanceof StoryPoints
                    ? $task->story_points->points()
                    : 1
            ),
        ];

        // === 2. PROJECT-SLOTS ===
        $slots = PlannerProjectSlot::with(['tasks' => function ($q) {
                $q->with(['tags', 'contextColors', 'userInCharge', 'project'])
                  ->where('is_done', false)
                  ->whereNotNull('project_slot_id') // Explizit: Nur Tasks mit project_slot_id (nicht NULL)
                  ->orderBy('project_slot_order');
            }])
            ->where('project_id', $this->project->id)
            ->orderBy('order')
            ->get()
            ->map(function ($slot) {
                // Zusätzliche Sicherheit: Filtere Tasks explizit nach project_slot_id
                $tasks = $slot->tasks->filter(function ($task) use ($slot) {
                    return $task->project_slot_id === $slot->id && $task->project_slot_id !== null;
                });

                return (object) [
                    'id' => $slot->id,
                    'label' => $slot->name,
                    'isBacklog' => false,
                    'tasks' => $tasks,
                    'open_count' => $tasks->count(),
                    'open_points' => $tasks->sum(
                        fn ($task) => $task->story_points instanceof StoryPoints
                            ? $task->story_points->points()
                            : 1
                    ),
                ];
            });

        // === 3. ERLEDIGTE AUFGABEN ===
        $doneTasks = PlannerTask::with(['tags', 'contextColors', 'userInCharge', 'project'])
            ->where('project_id', $this->project->id)
            ->where('is_done', true)
            ->orderByDesc('done_at') // Neueste zuerst (zuletzt erledigt)
            ->orderByDesc('updated_at') // Fallback für Tasks ohne done_at
            ->get();

        $completedGroup = (object) [
            'id' => 'done',
            'label' => 'Erledigt',
            'isDoneGroup' => true,
            'isBacklog' => false,
            'tasks' => $doneTasks,
        ];

        // === FILTER ANWENDEN (nach dem Laden, auf Collection-Ebene) ===
        $filterFn = function ($tasks) {
            return $tasks->filter(function ($task) {
                // Tag-Filter
                if (!empty($this->filterTagIds)) {
                    $taskTagIds = $task->contextTags->pluck('id')->toArray();
                    if (empty(array_intersect($this->filterTagIds, $taskTagIds))) {
                        return false;
                    }
                }
                // Farb-Filter
                if ($this->filterColor) {
                    if ($task->color !== $this->filterColor) {
                        return false;
                    }
                }
                return true;
            })->values();
        };

        $backlog->tasks = $filterFn($backlog->tasks);
        $backlog->open_count = $backlog->tasks->count();
        $backlog->open_points = $backlog->tasks->sum(
            fn ($task) => $task->story_points instanceof StoryPoints ? $task->story_points->points() : 1
        );

        $slots = $slots->map(function ($slot) use ($filterFn) {
            $slot->tasks = $filterFn($slot->tasks);
            $slot->open_count = $slot->tasks->count();
            $slot->open_points = $slot->tasks->sum(
                fn ($task) => $task->story_points instanceof StoryPoints ? $task->story_points->points() : 1
            );
            return $slot;
        });

        $completedGroup->tasks = $filterFn($completedGroup->tasks);

        // === VERFÜGBARE TAGS & FARBEN FÜR FILTER-UI ===
        $allProjectTasks = PlannerTask::with(['tags', 'contextColors'])
            ->where('project_id', $this->project->id)
            ->get();

        $availableFilterTags = $allProjectTasks->flatMap(fn ($t) => $t->contextTags)
            ->unique('id')
            ->sortBy('label')
            ->values()
            ->map(fn ($tag) => ['id' => $tag->id, 'label' => $tag->label, 'color' => $tag->color]);

        $availableFilterColors = $allProjectTasks->map(fn ($t) => $t->color)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // === BOARD-GRUPPEN ZUSAMMENSTELLEN ===
        $groups = collect([$backlog])->concat($slots)->push($completedGroup);

        return view('planner::livewire.project', [
            'groups' => $groups,
            'linkedEntities' => $linkedEntities,
            'currentUserRole' => $currentUserRole,
            'userOpenTaskCount' => $userOpenTaskCount,
            'hasAnyTasks' => $hasAnyTasks,
            'permissions' => $permissions,
            'allProjectUsers' => $allProjectUsers,
            'availableFilterTags' => $availableFilterTags,
            'availableFilterColors' => $availableFilterColors,
            'canvasInfo' => $canvasInfo,
            'dashboardData' => null,
        ])->layout('platform::layouts.app');
    }

    /**
     * Legt einen neuen Project-Slot an und lädt State neu.
     */
    public function createProjectSlot()
    {
        // Policy-Berechtigung prüfen
        $this->authorize('update', $this->project);

        $user = Auth::user();
        $maxOrder = $this->project->projectSlots()->max('order') ?? 0;

        $this->project->projectSlots()->create([
            'name' => 'Neuer Slot',
            'order' => $maxOrder + 1,
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
        ]);

        // Slots/State neu laden (Livewire 3 Way)
        $this->mount($this->project);
    }

    /**
     * Canvas öffnen — existiert noch keins, wird eins erstellt.
     */
    public function openCanvas()
    {
        $this->authorize('view', $this->project);

        $canvas = $this->project->canvases()->first();

        if (!$canvas) {
            $this->authorize('update', $this->project);

            $canvas = app(ProjectCanvasService::class)->createCanvas([
                'project_id' => $this->project->id,
                'team_id' => $this->project->team_id,
                'name' => $this->project->name . ' — Canvas',
                'status' => 'active',
                'created_by_user_id' => Auth::id(),
            ]);
        }

        return $this->redirect(
            route('planner.projects.canvas.show', [$this->project, $canvas]),
            navigate: true,
        );
    }

    /**
     * Legt eine neue Aufgabe an, optional direkt in einem Slot.
     */
    public function createTask($projectSlotId = null, $title = null)
    {
        // Policy-Berechtigung prüfen
        $this->authorize('update', $this->project);

        $user = Auth::user();

        $lowestOrder = PlannerTask::where('user_id', $user->id)
            ->where('team_id', $user->currentTeam->id)
            ->min('order') ?? 0;

        $order = $lowestOrder - 1;

        PlannerTask::create([
            'user_id'        => $user->id,
            'user_in_charge_id' => $user->id,
            'project_id'     => $this->project->id,
            'project_slot_id' => $projectSlotId,
            'title'          => $title ?: 'Neue Aufgabe',
            'description'    => null,
            'due_date'       => null,
            'priority'       => null,
            'story_points'   => null,
            'team_id'        => $user->currentTeam->id,
            'order'          => $order,
        ]);

        // Optional: State neu laden, falls Tasks direkt im UI erscheinen sollen
        $this->mount($this->project);
    }

    /**
     * Aktualisiert Reihenfolge und Slot-Zugehörigkeit der Tasks nach Drag&Drop.
     */
    public function updateTaskOrder($groups)
    {
        foreach ($groups as $group) {
            $taskGroupId = ($group['value'] === 'null' || (int) $group['value'] === 0)
                ? null
                : (int) $group['value'];

            foreach ($group['items'] as $item) {
                $task = PlannerTask::find($item['value']);

                if (! $task) {
                    continue;
                }

                $task->project_slot_order = $item['order'];
                $task->project_slot_id    = $taskGroupId;
                $task->save();
            }
        }

        // Nach Update optional State refresh
        $this->mount($this->project);
    }

    /**
     * Aktualisiert Reihenfolge der Slots nach Drag&Drop.
     */
    public function updateTaskGroupOrder($groups)
    {
        foreach ($groups as $taskGroup) {
            $taskGroupDb = PlannerProjectSlot::find($taskGroup['value']);
            if ($taskGroupDb) {
                $taskGroupDb->order = $taskGroup['order'];
                $taskGroupDb->save();
            }
        }

        // Nach Update optional State refresh
        $this->mount($this->project);
    }


    /**
     * Toggle für die Anzeige der Erledigt-Spalte
     */
    public function toggleShowDoneColumn()
    {
        $this->showDoneColumn = !$this->showDoneColumn;

        if ($this->showDoneColumn) {
            $this->dispatch('done-column-expanded');
        }
    }

    /**
     * Tag-Filter toggeln (hinzufügen/entfernen)
     */
    public function toggleTagFilter(int $tagId)
    {
        if (in_array($tagId, $this->filterTagIds)) {
            $this->filterTagIds = array_values(array_diff($this->filterTagIds, [$tagId]));
        } else {
            $this->filterTagIds[] = $tagId;
        }
    }

    /**
     * Farb-Filter setzen oder entfernen
     */
    public function toggleColorFilter(string $color)
    {
        $this->filterColor = $this->filterColor === $color ? null : $color;
    }

    /**
     * Alle Filter zurücksetzen
     */
    public function clearFilters()
    {
        $this->filterTagIds = [];
        $this->filterColor = null;
    }
}
