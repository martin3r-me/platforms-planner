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
        
        if (!$teamsUser) {
            \Log::warning("Teams User not found in embedded context");
            return;
        }

        // User aus Teams Context finden oder erstellen
        $user = $this->findOrCreateUserFromTeams($teamsUser);
        
        if (!$user) {
            \Log::warning("Could not find or create user from Teams context");
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
        // Teams User-Info aus Request holen (ohne Laravel Auth)
        $request = request();
        $teamsUser = TeamsAuthHelper::getTeamsUser($request);
        
        if (!$teamsUser) {
            \Log::warning("Teams User not found for project slot creation");
            return;
        }

        // User aus Teams Context finden oder erstellen
        $user = $this->findOrCreateUserFromTeams($teamsUser);
        
        if (!$user) {
            \Log::warning("Could not find or create user for project slot creation");
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

        // Embedded View verwenden
        return view('planner::livewire.embedded.project', [
            'teamUsers' => $teamUsers,
        ]);
    }
}


