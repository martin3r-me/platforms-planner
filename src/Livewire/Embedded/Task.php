<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Task as BaseTask;

class Task extends BaseTask
{
    public function render()
    {
        return view('planner::livewire.embedded.task');
    }
}


