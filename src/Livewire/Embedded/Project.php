<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Project as BaseProject;
use Platform\Planner\Models\PlannerTask;
use Platform\Core\Helpers\TeamsAuthHelper;
use Illuminate\Http\Request;

class Project extends BaseProject
{
    public function createTask($projectSlotId = null)
    {
        // DEBUG: Log dass die embedded createTask aufgerufen wird
        \Log::info("🔍 EMBEDDED CREATE TASK CALLED:", [
            'project_id' => $this->project->id,
            'project_slot_id' => $projectSlotId,
            'timestamp' => now()
        ]);

        // Teams User-Info aus Request holen (ohne Laravel Auth)
        $request = request();
        $teamsUser = TeamsAuthHelper::getTeamsUser($request);
        
        \Log::info("🔍 TEAMS USER DEBUG:", [
            'teamsUser' => $teamsUser,
            'request_attributes' => $request->attributes->all(),
            'headers' => $request->headers->all()
        ]);
        
        // Fallback: Frontend Teams SDK verwenden wenn Backend nicht funktioniert
        if (!$teamsUser) {
            \Log::info("Backend Teams User nicht gefunden, verwende Frontend Teams SDK");
            $this->dispatch('notifications:store', [
                'notice_type' => 'info',
                'title' => 'Teams SDK',
                'message' => 'Verwende Frontend Teams SDK für Authentifizierung...',
                'noticable_type' => 'Platform\\Planner\\Models\\PlannerProject',
                'noticable_id' => $this->project->id,
            ]);
            
            // Frontend Teams SDK verwenden
            return $this->createTaskWithFrontendTeams($projectSlotId);
        }

        // User aus Teams Context finden oder erstellen
        $user = $this->findOrCreateUserFromTeams($teamsUser);
        
        if (!$user) {
            \Log::warning("Could not find or create user from Teams context");
            $this->dispatch('notifications:store', [
                'notice_type' => 'error',
                'title' => 'User Fehler',
                'message' => 'User konnte nicht aus Teams Context erstellt werden.',
                'noticable_type' => 'Platform\\Planner\\Models\\PlannerProject',
                'noticable_id' => $this->project->id,
            ]);
            return;
        }

        $lowestOrder = PlannerTask::where('user_id', $user->id)
            ->where('team_id', $user->currentTeam->id)
            ->min('order') ?? 0;

        $order = $lowestOrder - 1;

        $task = PlannerTask::create([
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

        \Log::info("🔍 EMBEDDED TASK CREATED:", [
            'task_id' => $task->id,
            'redirect_to' => route('planner.embedded.task', $task),
            'timestamp' => now()
        ]);

        // State neu laden, damit die neue Aufgabe im Board erscheint
        $this->mount($this->project);
        
        // Einfache Weiterleitung ohne JavaScript
        return $this->redirect(route('planner.embedded.task', $task), navigate: true);
    }

    /**
     * Findet oder erstellt einen User basierend auf Teams Context
     */
    public static function findOrCreateUserFromTeams(array $teamsUser)
    {
        $userModelClass = config('auth.providers.users.model');
        
        // User anhand Email oder Azure ID finden
        $user = $userModelClass::query()
            ->where('email', $teamsUser['email'])
            ->orWhere('azure_id', $teamsUser['id'])
            ->first();

        if (!$user) {
            // User erstellen
            $user = new $userModelClass();
            $user->email = $teamsUser['email'];
            $user->name = $teamsUser['name'] ?? $teamsUser['email'];
            $user->azure_id = $teamsUser['id'] ?? null;
            $user->save();
            
            // Personal Team erstellen
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);
        }

        return $user;
    }

    public function updateTaskOrder($groups)
    {
        \Log::info("🔍 EMBEDDED UPDATE TASK ORDER:", [
            'groups' => $groups,
            'timestamp' => now()
        ]);
        
        return parent::updateTaskOrder($groups);
    }

    public function updateTaskGroupOrder($groups)
    {
        \Log::info("🔍 EMBEDDED UPDATE TASK GROUP ORDER:", [
            'groups' => $groups,
            'timestamp' => now()
        ]);
        
        return parent::updateTaskGroupOrder($groups);
    }

    public function createProjectSlot()
    {
        \Log::info("🔍 EMBEDDED CREATE PROJECT SLOT CALLED:", [
            'project_id' => $this->project->id,
            'timestamp' => now()
        ]);

        // Teams User-Info aus Request holen (ohne Laravel Auth)
        $request = request();
        $teamsUser = TeamsAuthHelper::getTeamsUser($request);
        
        \Log::info("🔍 TEAMS USER DEBUG (SLOT):", [
            'teamsUser' => $teamsUser,
            'request_attributes' => $request->attributes->all()
        ]);
        
        if (!$teamsUser) {
            \Log::warning("Teams User not found for project slot creation");
            $this->dispatch('notifications:store', [
                'notice_type' => 'error',
                'title' => 'Teams Auth Fehler',
                'message' => 'Teams User nicht gefunden für Spalte-Erstellung.',
                'noticable_type' => 'Platform\\Planner\\Models\\PlannerProject',
                'noticable_id' => $this->project->id,
            ]);
            return;
        }

        // User aus Teams Context finden oder erstellen
        $user = $this->findOrCreateUserFromTeams($teamsUser);
        
        if (!$user) {
            \Log::warning("Could not find or create user for project slot creation");
            $this->dispatch('notifications:store', [
                'notice_type' => 'error',
                'title' => 'User Fehler',
                'message' => 'User konnte nicht für Spalte-Erstellung erstellt werden.',
                'noticable_type' => 'Platform\\Planner\\Models\\PlannerProject',
                'noticable_id' => $this->project->id,
            ]);
            return;
        }

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

    public function render()
    {
        // Teams User aus Request holen (ohne Laravel Auth)
        $request = request();
        $teamsUser = TeamsAuthHelper::getTeamsUser($request);
        
        $teamUsers = collect();
        if ($teamsUser) {
            // User aus Teams Context finden
            $user = $this->findOrCreateUserFromTeams($teamsUser);
            if ($user && $user->currentTeam) {
                $teamUsers = $user->currentTeam->users()
                    ->orderBy('name')
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->fullname ?? $user->name,
                            'email' => $user->email,
                        ];
                    });
            }
        }

        // Groups wie in der Basis-Klasse erstellen
        $groups = $this->buildGroups();

        // Embedded View verwenden
        return view('planner::livewire.embedded.project', [
            'teamUsers' => $teamUsers,
            'groups' => $groups,
        ]);
    }

    /**
     * Erstellt die Groups für das Kanban Board (aus Basis-Klasse kopiert)
     */
    private function buildGroups()
    {
        // === 1. BACKLOG ===
        $backlogTasks = \Platform\Planner\Models\PlannerTask::where('project_id', $this->project->id)
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
                fn ($task) => $task->story_points instanceof \Platform\Planner\Enums\StoryPoints
                    ? $task->story_points->points()
                    : 1
            ),
        ];

        // === 2. PROJECT-SLOTS ===
        $slots = \Platform\Planner\Models\PlannerProjectSlot::with(['tasks' => function ($q) {
                $q->where('is_done', false)->orderBy('project_slot_order');
            }])
            ->where('project_id', $this->project->id)
            ->orderBy('order')
            ->get()
            ->map(fn ($slot) => (object) [
                'id' => $slot->id,
                'label' => $slot->name,
                'isBacklog' => false,
                'tasks' => $slot->tasks,
                'open_count' => $slot->tasks->count(),
                'open_points' => $slot->tasks->sum(
                    fn ($task) => $task->story_points instanceof \Platform\Planner\Enums\StoryPoints
                        ? $task->story_points->points()
                        : 1
                ),
            ]);

        // === 3. ERLEDIGTE AUFGABEN ===
        $doneTasks = \Platform\Planner\Models\PlannerTask::where('project_id', $this->project->id)
            ->where('is_done', true)
            ->orderByDesc('done_at')
            ->get();

        $completedGroup = (object) [
            'id' => 'done',
            'label' => 'Erledigt',
            'isDoneGroup' => true,
            'isBacklog' => false,
            'tasks' => $doneTasks,
        ];

        // === BOARD-GRUPPEN ZUSAMMENSTELLEN ===
        return collect([$backlog])->concat($slots)->push($completedGroup);
    }

    /**
     * Erstellt eine Aufgabe mit Frontend Teams SDK (Fallback)
     */
    private function createTaskWithFrontendTeams($projectSlotId = null)
    {
        \Log::info("🔍 CREATE TASK WITH FRONTEND TEAMS SDK");
        
        // JavaScript Event dispatch für Frontend Teams SDK
        $this->dispatch('create-task-with-teams', [
            'projectId' => $this->project->id,
            'projectSlotId' => $projectSlotId
        ]);
    }
}


