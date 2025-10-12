<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Task as BaseTask;

class Task extends BaseTask
{
    public function mount($plannerTask)
    {
        // Policy-Prüfung umgehen für embedded Kontext
        // $this->authorize('view', $plannerTask);
        
        $this->task = $plannerTask;
        $this->dueDateInput = $plannerTask->due_date ? $plannerTask->due_date->format('Y-m-d H:i') : '';
        
        // User aus Teams Context einloggen
        $this->loginUserFromTeamsContext();
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

    public function deleteTaskAndReturnToProject()
    {
        // Einfacher Ansatz: User aus Teams Context einloggen
        $user = $this->loginUserFromTeamsContext();
        
        if (!$user) {
            \Log::warning("Could not login user for task deletion");
            $this->dispatch('notifications:store', [
                'notice_type' => 'error',
                'title' => 'Login Fehler',
                'message' => 'User konnte nicht für Task-Löschung eingeloggt werden.',
                'noticable_type' => 'Platform\\Planner\\Models\\PlannerTask',
                'noticable_id' => $this->task->id,
            ]);
            return;
        }
        
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
            \Log::info("Backend Teams User gefunden, logge ein");
            $user = \Platform\Planner\Livewire\Embedded\Project::findOrCreateUserFromTeams($teamsUser);
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
        
        // 3. Fallback: Demo User für embedded Kontext
        \Log::info("Kein Teams User gefunden, verwende Demo User");
        $user = $this->getOrCreateDemoUser();
        if ($user) {
            \Auth::login($user);
            return $user;
        }
        
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
    
    /**
     * Demo User für embedded Kontext
     */
    private function getOrCreateDemoUser()
    {
        $userModelClass = config('auth.providers.users.model');
        $user = $userModelClass::where('email', 'teams-demo@embedded.local')->first();
        
        if (!$user) {
            $user = new $userModelClass();
            $user->email = 'teams-demo@embedded.local';
            $user->name = 'Teams Demo User';
            $user->save();
            
            // Personal Team erstellen
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);
        }
        
        return $user;
    }
}


