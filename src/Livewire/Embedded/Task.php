<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Task as BaseTask;

class Task extends BaseTask
{
    public function deleteTaskAndReturnToProject()
    {
        // Teams User aus Request holen (ohne Laravel Auth)
        $request = request();
        $teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser($request);
        
        if (!$teamsUser) {
            \Log::warning("Teams User not found for task deletion");
            return;
        }
        
        // User aus Teams Context finden
        $user = \Platform\Planner\Livewire\Embedded\Project::findOrCreateUserFromTeams($teamsUser);
        if (!$user) {
            \Log::warning("Could not find or create user for task deletion");
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

        // Teams User aus Request holen (ohne Laravel Auth)
        $request = request();
        $teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser($request);
        
        $teamUsers = collect();
        if ($teamsUser) {
            // User aus Teams Context finden
            $user = \Platform\Planner\Livewire\Embedded\Project::findOrCreateUserFromTeams($teamsUser);
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

        return view('planner::livewire.embedded.task', [
            'printers' => $printers,
            'printerGroups' => $groups,
            'printingAvailable' => $this->printingAvailable,
            'teamUsers' => $teamUsers,
        ]);
    }
}


