<?php

namespace Platform\Planner\Livewire\Embedded;

use Platform\Planner\Livewire\Task as BaseTask;
use Illuminate\Support\Facades\Auth;

class Task extends BaseTask
{
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
}