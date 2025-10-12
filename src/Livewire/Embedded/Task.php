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
        \Log::info('🔍 EMBEDDED TASK MOUNT:', [
            'plannerTask' => $plannerTask,
            'type' => gettype($plannerTask),
            'is_string' => is_string($plannerTask),
            'is_numeric' => is_numeric($plannerTask)
        ]);
        
        // Wenn $plannerTask ein String ist (ID), dann das Model laden
        if (is_string($plannerTask) || is_numeric($plannerTask)) {
            $this->task = \Platform\Planner\Models\PlannerTask::findOrFail($plannerTask);
        } else {
            $this->task = $plannerTask;
        }
        
        \Log::info('🔍 EMBEDDED TASK AFTER LOAD:', [
            'task_id' => $this->task->id ?? 'NULL',
            'task_title' => $this->task->title ?? 'NULL',
            'task_type' => gettype($this->task)
        ]);
        
        $this->dueDateInput = $this->task->due_date ? $this->task->due_date->format('Y-m-d H:i') : '';
    }

    public function updatedDueDateInput($value)
    {
        if (empty($value)) {
            $this->task->due_date = null;
        } else {
            try {
                // Prüfe ob es nur ein Jahr ist (z.B. "2025")
                if (preg_match('/^\d{4}$/', $value)) {
                    $this->task->due_date = null; // Ungültiges Format ignorieren
                } else {
                    // Parse das Date-Format (YYYY-MM-DD oder YYYY-MM-DD HH:MM)
                    $this->task->due_date = \Carbon\Carbon::parse($value);
                }
            } catch (\Exception $e) {
                // Bei ungültigem Datum auf null setzen
                $this->task->due_date = null;
            }
        }
        
        $this->task->save();
    }

    public function updatedTask($property, $value)
    {
        $this->validateOnly("task.$property");
        
        // Nur speichern wenn sich wirklich was geändert hat
        if ($this->task->isDirty($property)) {
            $this->task->save();
            // Auto-Save läuft still im Hintergrund
        }
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
            'task' => $this->task,
        ])->layout('platform::layouts.embedded');
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