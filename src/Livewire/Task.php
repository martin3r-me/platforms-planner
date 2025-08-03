<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;


class Task extends Component
{
	public $task;

	protected $rules = [
        'task.title' => 'required|string|max:255',
        'task.description' => 'nullable|string',
        'task.is_frog' => 'boolean',
        'task.is_done' => 'boolean',
        'task.due_date' => 'nullable|date',
        'task.user_in_charge_id' => 'nullable|integer',
        'task.priority' => 'required|in:low,normal,high',
        'task.story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
        'task.project_id' => 'nullable|integer',
    ];

    public function mount(PlannerTask $plannerTask)
    {
        $this->authorize('view', $plannerTask);
        $this->task = $plannerTask;
        $this->dispatch('comms');
    }

    public function updatedTask($property, $value)
    {
        $this->validateOnly("task.$property");
        $this->task->save();
    }

    public function deleteTask()
    {
        $this->task->delete();
        return $this->redirect('/', navigate: true);
    }

	public function render()
    {        
        return view('planner::livewire.task')->layout('platform::layouts.app');
    }
}
