<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Project as BaseProject;

class Project extends BaseProject
{
    public function render()
    {
        return view('planner::livewire.embedded.project');
    }
}


