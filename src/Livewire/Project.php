<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Enums\StoryPoints;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Platform\Core\Contracts\CrmCompanyResolverInterface;

class Project extends Component
{
    public PlannerProject $project;
    public $sprint; // Aktueller Sprint des Projekts
    public bool $showDoneColumn = false; // Erledigt-Spalte ein/ausblenden

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
        
        // Sprints werden nicht mehr geladen - nur Project-Slots
    }

    public function rendered()
    {
        // DEBUG: Log dass rendered() ausgelöst wurde
        \Log::info("🔍 PROJECT RENDERED EVENT:", [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'url' => route('planner.projects.show', $this->project),
            'timestamp' => now()
        ]);
        
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
        
        // DEBUG: Log dass comms Event gesendet wurde
        \Log::info("🔍 PROJECT COMMS EVENT GESENDET:", [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name
        ]);

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

        // Tagging-Kontext setzen - ermöglicht Tagging und Farbzuweisung für dieses Projekt
        $this->dispatch('tagging', [
            'context_type' => get_class($this->project),
            'context_id' => $this->project->id,
        ]);
    }

    public function render()
    {
        $user = Auth::user();

        // === 1. BACKLOG ===
        $backlogTasks = PlannerTask::where('project_id', $this->project->id)
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
                $q->where('is_done', false)
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
        $doneTasks = PlannerTask::where('project_id', $this->project->id)
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

        // === BOARD-GRUPPEN ZUSAMMENSTELLEN ===
        $groups = collect([$backlog])->concat($slots)->push($completedGroup);

        // Kundenprojekt-Company anzeigen
        /** @var CrmCompanyResolverInterface $companyResolver */
        $companyResolver = app(CrmCompanyResolverInterface::class);
        $companyId = $this->project?->customerProject?->company_id;
        $customerCompanyName = $companyResolver->displayName($companyId);
        $customerCompanyUrl = $companyResolver->url($companyId);

        // Aktuelle Rolle des Users im Projekt ermitteln
        $projectUser = $this->project->projectUsers()
            ->where('user_id', $user->id)
            ->first();
        $currentUserRole = $projectUser?->role;

        // Prüfen ob User Aufgaben im Projekt hat (auch ohne Mitgliedschaft)
        $hasTasks = $this->project->tasks()
            ->where('user_in_charge_id', $user->id)
            ->exists();
        
        $hasTasksInSlots = $this->project->projectSlots()
            ->whereHas('tasks', function ($q) use ($user) {
                $q->where('user_in_charge_id', $user->id);
            })
            ->exists();
        
        $hasAnyTasks = $hasTasks || $hasTasksInSlots;

        // Debug: Berechtigungen prüfen
        $permissions = [
            'view' => $user->can('view', $this->project),
            'update' => $user->can('update', $this->project),
            'delete' => $user->can('delete', $this->project),
            'settings' => $user->can('settings', $this->project),
            'invite' => $user->can('invite', $this->project),
        ];

        // Alle Projekt-Mitglieder für Debug
        $allProjectUsers = $this->project->projectUsers()->with('user')->get();

        return view('planner::livewire.project', [
            'groups' => $groups,
            'customerCompanyName' => $customerCompanyName,
            'customerCompanyUrl' => $customerCompanyUrl,
            'currentUserRole' => $currentUserRole,
            'hasAnyTasks' => $hasAnyTasks,
            'permissions' => $permissions,
            'allProjectUsers' => $allProjectUsers,
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
     * Legt eine neue Aufgabe an, optional direkt in einem Slot.
     */
    public function createTask($projectSlotId = null)
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
            'title'          => 'Neue Aufgabe',
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
    }
}