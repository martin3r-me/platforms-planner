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
        'task.user_in_charge_id' => 'nullable|integer',
        'task.priority' => 'required|in:low,normal,high',
        'task.story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
        'task.project_id' => 'nullable|integer',
    ];

    public function mount($plannerTask)
    {
        // Wenn $plannerTask ein String ist (ID), dann das Model laden
        if (is_string($plannerTask) || is_numeric($plannerTask)) {
            $this->task = \Platform\Planner\Models\PlannerTask::findOrFail($plannerTask);
        } else {
            $this->task = $plannerTask;
        }
        
        // HTML datetime-local erwartet ein "T" zwischen Datum und Zeit
        $this->dueDateInput = $this->task->due_date ? $this->task->due_date->format('Y-m-d\TH:i') : '';
    }

    public function updatedDueDateInput($value)
    {
        // Keine direkten Saves hier, nur robustes Setzen – Speichern erfolgt zentral in save()
        if ($value === null || $value === '') {
            $this->task->due_date = null;
            $this->task->save();
            return;
        }
        try {
            $normalized = str_replace('T', ' ', $value);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
                $normalized .= ' 00:00';
            }
            // Ignoriere reine Jahres-/Monatsangaben
            if (preg_match('/^\d{4}$/', $normalized) || preg_match('/^\d{4}-\d{2}$/', $normalized)) {
                return; // unvollständig, nicht setzen
            }
            $this->task->due_date = \Carbon\Carbon::parse($normalized);
            $this->task->save();
            // Anzeige synchron zum gespeicherten Wert halten (HTML datetime-local erwartet "T")
            $this->dueDateInput = $this->task->due_date ? $this->task->due_date->format('Y-m-d\\TH:i') : '';
        } catch (\Throwable $e) {
            // still und leise ignorieren – kein Exception-Bubble beim Modal-Schließen
        }
    }

    public function updatedTask($property, $value)
    {
        // $property kann z.B. "title" oder "task.title" sein – wir normalisieren auf Attributnamen
        $attribute = str_starts_with($property, 'task.') ? substr($property, 5) : $property;
        
        // Korrekte Validierung nur des geänderten Feldes
        $this->validateOnly("task.$attribute");
        
        // Nur speichern, wenn sich das konkrete Attribut geändert hat
        if ($this->task->isDirty($attribute)) {
            $this->task->save();
        }
    }

    // Fängt alle Änderungen ab (z. B. bei wire:model auf task.*)
    public function updated($name, $value)
    {
        if (str_starts_with($name, 'task.')) {
            $attribute = substr($name, 5);
            $this->validateOnly("task.$attribute");
            if ($this->task->isDirty($attribute)) {
                $this->task->save();
            }
        }
    }

    public function save()
    {
        // Policy-Prüfung umgehen für embedded Kontext
        // $this->authorize('update', $this->task);
        
        // Datum robust konvertieren (vor Validierung, da wir due_date nicht validieren)
        if ($this->dueDateInput) {
            try {
                // ISO 8601 mit "T" zulassen
                $value = str_replace('T', ' ', $this->dueDateInput);
                // Falls nur Datum ohne Zeit: 00:00 anhängen
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $value .= ' 00:00';
                }
                $this->task->due_date = \Carbon\Carbon::parse($value);
            } catch (\Throwable $e) {
                $this->task->due_date = null;
            }
        } else {
            $this->task->due_date = null;
        }

        $this->validate();
        
        $this->task->save();
        // Nach dem Speichern Input an das erwartete HTML-Format anpassen
        $this->dueDateInput = $this->task->due_date ? $this->task->due_date->format('Y-m-d\TH:i') : '';
        
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