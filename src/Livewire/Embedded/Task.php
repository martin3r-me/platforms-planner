<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Task as BaseTask;

class Task extends BaseTask
{
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

        $teamUsers = \Auth::user()?->currentTeam?->users()
            ?->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->fullname ?? $user->name,
                    'email' => $user->email,
                ];
            }) ?? collect();

        return view('planner::livewire.embedded.task', [
            'printers' => $printers,
            'printerGroups' => $groups,
            'printingAvailable' => $this->printingAvailable,
            'teamUsers' => $teamUsers,
        ]);
    }
}


