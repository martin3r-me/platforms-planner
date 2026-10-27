<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Database\Eloquent\Model;
use Platform\Core\Livewire\Concerns\HasPageContext;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\DavSubscription;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\TaskStoryPoints as StoryPoints;
use Platform\Planner\Livewire\Concerns\QuickTogglesDone;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Services\ProjectCanvasAnalysisService;

class Project extends Component
{
    use QuickTogglesDone, HasPageContext;

    public PlannerProject $project;
    public $sprint; // Aktueller Sprint des Projekts
    public bool $showDoneColumn = false; // Erledigt-Spalte ein/ausblenden

    protected $queryString = [
        'showDoneColumn' => ['except' => false, 'as' => 'done'],
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

    protected function pageContext(): ?Model
    {
        return $this->project;
    }

    protected function pageContextOptions(): array
    {
        return [
            // Name statt Anzeigename („RUN · …"), sonst benennt sich der Terminal-Kanal um
            'subject' => $this->project->name,
            'url' => route('planner.projects.show', $this->project),
            'source' => 'planner.project.view',
            'capabilities' => ['manage_channels' => true, 'threads' => false],
            'meta' => [
                'project_type' => $this->project->project_type,
                'created_at' => $this->project->created_at,
            ],
        ];
    }

    /**
     * Hat der User ein Planner-CalDAV-Abo? (Nur dann ist der „in App zeigen"-Toggle sinnvoll.)
     */
    public function hasPlannerCaldavSubscription(): bool
    {
        return DavSubscription::query()
            ->where('module', 'planner')
            ->where('type', 'caldav')
            ->where('user_id', Auth::id())
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Wird dieses Projekt in der Aufgaben-App (CalDAV) des Users als eigene Liste gezeigt?
     */
    public function caldavExposed(): bool
    {
        return \Platform\Planner\Models\PlannerCalendarExposure::query()
            ->where('user_id', Auth::id())
            ->where('project_id', $this->project->id)
            ->exists();
    }

    public function toggleCaldavExposure(): void
    {
        // Kalender-Abo = Zeile existiert (An/Aus). Reine per-User-Wahl, kein Zugriff.
        $existing = \Platform\Planner\Models\PlannerCalendarExposure::query()
            ->where('user_id', Auth::id())
            ->where('project_id', $this->project->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            \Platform\Planner\Models\PlannerCalendarExposure::create([
                'user_id' => Auth::id(),
                'project_id' => $this->project->id,
            ]);
        }
    }

    public function render()
    {
        $user = Auth::user();

        // === SHARED DATA (beide Tabs) ===

        // Entity-Verknüpfungen laden via DimensionLink
        $linkedEntities = collect();

        $entityLinks = \Platform\Organization\Services\EntityDimensionBridge::linksForLinkables(
            ['project'],
            [$this->project->id]
        );
        foreach ($entityLinks as $link) {
            $linkedEntities->push([
                'entity_name' => $link->entity?->name ?? 'Unbekannt',
                'entity_type' => $link->entity?->type?->name ?? '',
            ]);
        }

        $linkedEntities = $linkedEntities->unique('entity_name');

        // Rolle im Projekt = Ersteller (owner) oder nichts. Keine Mitgliedschaft mehr.
        $currentUserRole = ((int) $this->project->user_id === (int) $user->id) ? 'owner' : null;

        // Offene Aufgaben dieses Users im Projekt (zählt Tasks mit oder ohne Slot)
        $userOpenTaskCount = PlannerTask::where('project_id', $this->project->id)
            ->where('user_in_charge_id', $user->id)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
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

        // Kickoff-Hinweis ueber dem Board (Ueberblick-Daten: Support\ProjectDashboardData)
        $canvas = app(ProjectCanvasService::class)->forProject($this->project);
        $canvasInfo = [
            'exists' => (bool) $canvas,
            'kickoff_done' => (bool) $canvas?->kickoff_completed_at,
        ];

        // === BOARD ===

        // === 1. BACKLOG ===
        $backlogTasks = PlannerTask::with(['tags', 'contextColors', 'userInCharge', 'project'])
            ->where('project_id', $this->project->id)
            ->whereNull('project_slot_id')
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
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
                  ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
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
                    'color' => $slot->color,
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
            ->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)
            ->orderByDesc('lifecycle_state_changed_at') // Neueste zuerst (zuletzt erledigt)
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
            'availableFilterTags' => $availableFilterTags,
            'availableFilterColors' => $availableFilterColors,
            'canvasInfo' => $canvasInfo,
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

        $canvas = app(ProjectCanvasService::class)->forProject($this->project);

        if (!$canvas) {
            $this->authorize('update', $this->project);
            $canvas = app(ProjectCanvasService::class)->createForProject($this->project, Auth::id());
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

        // Backlog rendert im Blade als leere ID ('') → zu null normalisieren,
        // sonst wirft MySQL "Incorrect integer value: '' for project_slot_id".
        $projectSlotId = ($projectSlotId === '' || $projectSlotId === null) ? null : (int) $projectSlotId;

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
