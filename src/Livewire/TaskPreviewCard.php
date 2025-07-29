<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;

class TaskPreviewCard extends Component
{

    public PlannerTask $task;

    public function render()
    {   
        return view('planner::livewire.task-preview-card')->layout('platform::layouts.app');
    }
}