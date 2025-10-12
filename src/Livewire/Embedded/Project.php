<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Project as BaseProject;
use Platform\Planner\Models\PlannerTask;
use Platform\Core\Helpers\TeamsAuthHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Project extends BaseProject
{
    public function createTask($projectSlotId = null)
    {
        $user = Auth::user();

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

    public function render()
    {
        // Groups wie in der Basis-Klasse erstellen
        $groups = $this->buildGroups();

        // Embedded View verwenden
        return view('planner::livewire.embedded.project', [
            'groups' => $groups,
        ])->layout('platform::layouts.embedded');
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
     * Loggt User aus Teams Context ein (einfacher Ansatz)
     */
    private function loginUserFromTeamsContext()
    {
        // 1. Versuche Backend Teams SDK Auth
        $request = request();
        $teamsUser = TeamsAuthHelper::getTeamsUser($request);
        
        if ($teamsUser) {
            \Log::info("Backend Teams User gefunden, logge ein");
            $user = $this->findOrCreateUserFromTeams($teamsUser);
            if ($user) {
                \Auth::login($user);
                return $user;
            }
        }
        
        // 2. Fallback: User aus Request Headers/Query extrahieren
        $userEmail = $request->header('X-User-Email') ?: $request->query('user_email');
        $userName = $request->header('X-User-Name') ?: $request->query('user_name');
        
        if ($userEmail) {
            \Log::info("User aus Headers/Query gefunden: {$userEmail}");
            $user = $this->findOrCreateUserByEmail($userEmail, $userName);
            if ($user) {
                \Auth::login($user);
                return $user;
            }
        }
        
        // 3. Kein Fallback - Teams User ist erforderlich
        \Log::error("Kein Teams User oder SSO gefunden - Teams Authentication erforderlich!");
        $this->dispatch('notifications:store', [
            'notice_type' => 'error',
            'title' => 'Teams Authentication Fehler',
            'message' => 'Kein Teams User gefunden. Bitte stellen Sie sicher, dass Sie über Microsoft Teams auf die Anwendung zugreifen.',
            'noticable_type' => 'Platform\\Planner\\Models\\PlannerProject',
            'noticable_id' => $this->project->id ?? null,
        ]);
        
        return null;
    }
    
    /**
     * Findet oder erstellt User anhand Email
     */
    private function findOrCreateUserByEmail($email, $name = null)
    {
        $userModelClass = config('auth.providers.users.model');
        $user = $userModelClass::where('email', $email)->first();
        
        if (!$user) {
            $user = new $userModelClass();
            $user->email = $email;
            $user->name = $name ?: $email;
            $user->save();
            
            // Personal Team erstellen
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);
        }
        
        return $user;
    }
    
}


