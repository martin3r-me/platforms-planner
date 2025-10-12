<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Task as BaseTask;
use Illuminate\Support\Facades\Auth;

class Task extends BaseTask
{
    public function mount($plannerTask)
    {
        $this->task = $plannerTask;
        $this->dueDateInput = $plannerTask->due_date ? $plannerTask->due_date->format('Y-m-d H:i') : '';
    }

    public function save()
    {
        // Policy-Prüfung umgehen für embedded Kontext
        // $this->authorize('update', $this->task);
        
        $this->validate();
        
        // Datum konvertieren
        if ($this->dueDateInput) {
            $this->task->due_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->dueDateInput);
        } else {
            $this->task->due_date = null;
        }
        
        $this->task->save();
        
        // Toast-Notification über das Notification-System
        $this->dispatch('notifications:store', [
            'notice_type' => 'success',
            'title' => 'Aufgabe gespeichert',
            'message' => 'Die Aufgabe wurde erfolgreich gespeichert.',
            'properties' => [
                'task_id' => $this->task->id,
                'task_title' => $this->task->title,
            ],
            'noticable_type' => get_class($this->task),
            'noticable_id' => $this->task->id,
        ]);
    }

    public function render()
    {
        // Team-Mitglieder für Assignee-Auswahl laden
        $teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->fullname ?? $user->name,
                    'email' => $user->email,
                ];
            });

        return view('planner::livewire.embedded.task', [
            'teamUsers' => $teamUsers,
        ]);
    }

    public function deleteTaskAndReturnToProject()
    {
        // Policy-Prüfung umgehen für embedded Kontext
        // $this->authorize('delete', $this->task);
        
        $taskTitle = $this->task->title;
        
        if (!$this->task->project) {
            // Fallback zu MyTasks wenn kein Projekt vorhanden
            $this->task->delete();
            
            $this->dispatch('notifications:store', [
                'notice_type' => 'info',
                'title' => 'Aufgabe gelöscht',
                'message' => "Die Aufgabe '{$taskTitle}' wurde gelöscht.",
            ]);
            
            return $this->redirect(route('planner.my-tasks'), navigate: true);
        }
        
        $this->task->delete();
        
        $this->dispatch('notifications:store', [
            'notice_type' => 'info',
            'title' => 'Aufgabe gelöscht',
            'message' => "Die Aufgabe '{$taskTitle}' wurde gelöscht.",
        ]);
        
        return $this->redirect(route('planner.embedded.project', $this->task->project), navigate: true);
    }

    public function printTask()
    {
        if ($this->printingAvailable) {
            $this->printModalShow = true;
        }
    }

    public function closePrintModal()
    {
        $this->printModalShow = false;
        $this->resetPrintSelection();
    }

    public function updatedPrintTarget()
    {
        // Reset Auswahl wenn Typ gewechselt wird
        $this->resetPrintSelection();
    }

    private function resetPrintSelection()
    {
        $this->selectedPrinterId = null;
        $this->selectedPrinterGroupId = null;
    }

    public function printTaskConfirm()
    {
        if ($this->printingAvailable) {
            $printing = app('Platform\\Printing\\Contracts\\PrintingServiceInterface');
            
            if ($this->printTarget === 'printer' && $this->selectedPrinterId) {
                $printing->printTask($this->task, $this->selectedPrinterId);
            } elseif ($this->printTarget === 'group' && $this->selectedPrinterGroupId) {
                $printing->printTaskToGroup($this->task, $this->selectedPrinterGroupId);
            }
            
            $this->closePrintModal();
        }
    }

    public function render()
    {
        $printers = collect();
        $groups = collect();
        $this->printingAvailable = interface_exists('Platform\\Printing\\Contracts\\PrintingServiceInterface')
            && app()->bound('Platform\\Printing\\Contracts\\PrintingServiceInterface');
        if ($this->printingAvailable) {
            $printing = app('Platform\\Printing\\Contracts\\PrintingServiceInterface');
            $printers = $printing->listPrinters();
            $groups   = $printing->listPrinterGroups();
        }

        // Einfacher Ansatz: User aus Teams Context einloggen
        $user = $this->loginUserFromTeamsContext();
        $teamUsers = collect();
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

        return view('planner::livewire.embedded.task', [
            'printers' => $printers,
            'printerGroups' => $groups,
            'printingAvailable' => $this->printingAvailable,
            'teamUsers' => $teamUsers,
        ]);
    }

    /**
     * Loggt User aus Teams Context ein (einfacher Ansatz)
     */
    private function loginUserFromTeamsContext()
    {
        // 1. Versuche Backend Teams SDK Auth
        $request = request();
        $teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser($request);
        
        if ($teamsUser) {
            \Log::info("Backend Teams User gefunden, logge ein", [
                'email' => $teamsUser['email'] ?? 'Keine Email',
                'name' => $teamsUser['name'] ?? 'Kein Name',
                'id' => $teamsUser['id'] ?? 'Keine ID'
            ]);
            
            $user = $this->findOrCreateUserFromTeams($teamsUser);
            if ($user) {
                \Auth::login($user);
                \Log::info("User erfolgreich eingeloggt", ['user_id' => $user->id, 'email' => $user->email]);
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
            'noticable_type' => 'Platform\\Planner\\Models\\PlannerTask',
            'noticable_id' => $this->task->id ?? null,
        ]);
        
        return null;
    }
    
    /**
     * Findet oder erstellt User aus Teams Context
     */
    private function findOrCreateUserFromTeams(array $teamsUser)
    {
        $userModelClass = config('auth.providers.users.model');
        
        // Versuche zuerst über Email zu finden
        $user = $userModelClass::query()
            ->where('email', $teamsUser['email'])
            ->first();
            
        if (!$user) {
            // Versuche über Azure ID zu finden
            $user = $userModelClass::query()
                ->where('azure_id', $teamsUser['id'])
                ->first();
        }
        
        if (!$user) {
            // User erstellen
            \Log::info("Erstelle neuen User aus Teams Context", [
                'email' => $teamsUser['email'],
                'name' => $teamsUser['name'] ?? $teamsUser['email'],
                'azure_id' => $teamsUser['id'] ?? null
            ]);
            
            $user = new $userModelClass();
            $user->email = $teamsUser['email'];
            $user->name = $teamsUser['name'] ?? $teamsUser['email'];
            $user->azure_id = $teamsUser['id'] ?? null;
            $user->save();
            
            // Personal Team erstellen
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);
            
            \Log::info("User erfolgreich erstellt", [
                'user_id' => $user->id,
                'email' => $user->email,
                'team_id' => $user->currentTeam?->id
            ]);
        }
        
        return $user;
    }

    /**
     * Findet oder erstellt User anhand Email
     */
    private function findOrCreateUserByEmail($email, $name = null)
    {
        $userModelClass = config('auth.providers.users.model');
        $user = $userModelClass::where('email', $email)->first();
        
        if (!$user) {
            \Log::info("Erstelle neuen User aus SSO", [
                'email' => $email,
                'name' => $name ?: $email
            ]);
            
            $user = new $userModelClass();
            $user->email = $email;
            $user->name = $name ?: $email;
            $user->save();
            
            // Personal Team erstellen
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);
            
            \Log::info("SSO User erfolgreich erstellt", [
                'user_id' => $user->id,
                'email' => $user->email,
                'team_id' => $user->currentTeam?->id
            ]);
        }
        
        return $user;
    }
    
}


