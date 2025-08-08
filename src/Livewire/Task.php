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
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->task),                                // z. B. 'Platform\Planner\Models\PlannerTask'
            'modelId' => $this->task->id,
            'subject' => $this->task->title,
            'description' => $this->task->description ?? '',
            'url' => route('planner.tasks.show', $this->task),                // absolute URL zum Task
            'source' => 'planner.task.view',                                 // eindeutiger Quell-Identifier (frei wählbar)
            'recipients' => [$this->task->user_in_charge_id],                // falls vorhanden, sonst leer
            'meta' => [
                'priority' => $this->task->priority,
                'due_date' => $this->task->due_date,
                'story_points' => $this->task->story_points,
            ],
        ]);
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

    public function deleteTaskAndReturnToDashboard()
    {
        $this->authorize('delete', $this->task);
        $this->task->delete();
        return $this->redirect(route('planner.my-tasks'), navigate: true);
    }

    public function deleteTaskAndReturnToProject()
    {
        $this->authorize('delete', $this->task);
        
        if (!$this->task->project) {
            // Fallback zu MyTasks wenn kein Projekt vorhanden
            $this->task->delete();
            return $this->redirect(route('planner.my-tasks'), navigate: true);
        }
        
        $this->task->delete();
        return $this->redirect(route('planner.projects.show', $this->task->project), navigate: true);
    }

	public function render()
    {        
        return view('planner::livewire.task')->layout('platform::layouts.app');
    }
}
