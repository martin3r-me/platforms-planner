<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Platform\Planner\Models\PlannerTask;


class Task extends Component
{
	public $task;
    public $dueDateInput; // Separate Property für das Datum
    public $dueDateModalShow = false;
    public $dueDateInputModal; // Temporärer Wert für das Modal
    public $calendarMonth; // Aktueller Monat (1-12)
    public $calendarYear; // Aktuelles Jahr
    public $selectedDate; // Ausgewähltes Datum (Y-m-d)
    public $selectedTime; // Ausgewählte Zeit (H:i)
    public $selectedHour = 12; // Ausgewählte Stunde (0-23)
    public $selectedMinute = 0; // Ausgewählte Minute (0-59)
    public $printModalShow = false;
    public $printTarget = 'printer'; // 'printer' oder 'group'
    public $selectedPrinterId = null;
    public $selectedPrinterGroupId = null;
    public $printingAvailable = false;

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
        $this->dueDateInput = $plannerTask->due_date ? $plannerTask->due_date->format('Y-m-d H:i') : '';
    }

    #[Computed]
    public function isDirty(): bool
    {
        if (!$this->task) {
            return false;
        }
        // Nur echte, noch nicht gespeicherte Änderungen berücksichtigen
        return count($this->task->getDirty()) > 0;
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
        $this->authorize('update', $this->task);
        
        $this->validate();
        
        // Datum konvertieren
        if ($this->dueDateInput) {
            try {
                $this->task->due_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->dueDateInput);
            } catch (\Exception $e) {
                // Fallback: Versuche direkt zu parsen
                $this->task->due_date = \Carbon\Carbon::parse($this->dueDateInput);
            }
        } else {
            $this->task->due_date = null;
        }
        
        $this->task->save();
        
        // Lade die Task neu für die Anzeige
        $this->task = $this->task->fresh();
        
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

    public function toggleDone(): void
    {
        $this->authorize('update', $this->task);
        $this->task->is_done = (bool)!$this->task->is_done;
        $this->task->save();
    }

    public function toggleFrog(): void
    {
        $this->authorize('update', $this->task);
        $this->task->is_frog = (bool)!$this->task->is_frog;
        $this->task->save();
    }

    public function deleteTask()
    {
        $this->authorize('delete', $this->task);
        
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
        
        // Zielroute vor dem Löschen bestimmen, damit die Model-Bindung beim Redirect nicht fehlt
        $redirectUrl = route('planner.projects.show', $this->task->project);
        
        $this->task->delete();
        
        $this->dispatch('notifications:store', [
            'notice_type' => 'info',
            'title' => 'Aufgabe gelöscht',
            'message' => "Die Aufgabe '{$taskTitle}' wurde gelöscht.",
        ]);
        
        return $this->redirect($redirectUrl, navigate: true);
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
        if (! $this->printingAvailable) {
            return;
        }

        if (!$this->selectedPrinterId && !$this->selectedPrinterGroupId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Bitte wählen Sie einen Drucker oder eine Gruppe',
            ]);
            return;
        }

        // Auflösung nur, wenn Service existiert
        $printing = app('Platform\\Printing\\Contracts\\PrintingServiceInterface');

        $printing->createJob(
            printable: $this->task,
            // template: null, // Automatische Template-Auswahl: planner-task
            data: [
                'requested_by' => Auth::user()?->name,
            ],
            printerId: $this->selectedPrinterId ? (int) $this->selectedPrinterId : null,
            printerGroupId: $this->selectedPrinterGroupId ? (int) $this->selectedPrinterGroupId : null,
        );

        $this->closePrintModal();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Druckauftrag wurde erstellt',
        ]);
    }

    #[Computed]
    public function calendarDays()
    {
        $firstDay = \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1);
        $lastDay = $firstDay->copy()->endOfMonth();
        
        // Start mit dem ersten Tag der Woche (Montag = 1)
        $startDate = $firstDay->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $endDate = $lastDay->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
        
        $days = [];
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            $days[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'isCurrentMonth' => $current->month == $this->calendarMonth,
                'isToday' => $current->isToday(),
                'isSelected' => $this->selectedDate === $current->format('Y-m-d'),
            ];
            $current->addDay();
        }
        
        return $days;
    }

    #[Computed]
    public function calendarMonthName()
    {
        return \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1)
            ->locale('de')
            ->isoFormat('MMMM YYYY');
    }

    public function openDueDateModal()
    {
        $this->authorize('update', $this->task);
        
        // Initialisiere Kalender mit aktuellem Datum oder heute
        if ($this->task->due_date) {
            $date = $this->task->due_date;
            $this->calendarMonth = $date->month;
            $this->calendarYear = $date->year;
            $this->selectedDate = $date->format('Y-m-d');
            $this->selectedTime = $date->format('H:i');
            $this->selectedHour = (int) $date->format('H');
            $this->selectedMinute = (int) $date->format('i');
        } else {
            $today = now();
            $this->calendarMonth = $today->month;
            $this->calendarYear = $today->year;
            $this->selectedDate = null;
            $this->selectedTime = $today->format('H:i');
            $this->selectedHour = (int) $today->format('H');
            $this->selectedMinute = (int) $today->format('i');
        }
        
        $this->dueDateModalShow = true;
    }

    public function closeDueDateModal()
    {
        // Verwerfe Änderungen - setze zurück auf aktuelles Datum
        if ($this->task->due_date) {
            $date = $this->task->due_date;
            $this->selectedDate = $date->format('Y-m-d');
            $this->selectedTime = $date->format('H:i');
            $this->selectedHour = (int) $date->format('H');
            $this->selectedMinute = (int) $date->format('i');
        } else {
            $this->selectedDate = null;
            $this->selectedTime = null;
            $this->selectedHour = 12;
            $this->selectedMinute = 0;
        }
        $this->dueDateModalShow = false;
    }

    public function previousMonth()
    {
        $date = \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1);
        $date->subMonth();
        $this->calendarMonth = $date->month;
        $this->calendarYear = $date->year;
    }

    public function nextMonth()
    {
        $date = \Carbon\Carbon::create($this->calendarYear, $this->calendarMonth, 1);
        $date->addMonth();
        $this->calendarMonth = $date->month;
        $this->calendarYear = $date->year;
    }

    public function selectDate($date)
    {
        $this->selectedDate = $date;
    }

    public function selectHour($hour)
    {
        $this->selectedHour = (int) $hour;
        $this->updateSelectedTime();
    }

    public function selectMinute($minute)
    {
        $this->selectedMinute = (int) $minute;
        $this->updateSelectedTime();
    }

    public function updatedSelectedHour()
    {
        $this->updateSelectedTime();
    }

    public function updatedSelectedMinute()
    {
        $this->updateSelectedTime();
    }

    private function updateSelectedTime()
    {
        $this->selectedTime = sprintf('%02d:%02d', $this->selectedHour, $this->selectedMinute);
    }

    public function saveDueDate()
    {
        try {
            $this->authorize('update', $this->task);

            if (empty($this->selectedDate)) {
                $this->task->due_date = null;
            } else {
                // Verwende die ausgewählte Stunde und Minute
                $time = sprintf('%02d:%02d', $this->selectedHour ?? 12, $this->selectedMinute ?? 0);
                $this->task->due_date = \Carbon\Carbon::parse("{$this->selectedDate} {$time}");
            }

            // Speichere die Task explizit
            $this->task->save();
            
            // Lade die Task neu mit allen Attributen (fresh() lädt aus DB neu)
            $this->task = $this->task->fresh();
            
            // Aktualisiere auch dueDateInput für die Anzeige
            $this->dueDateInput = $this->task->due_date ? $this->task->due_date->format('Y-m-d H:i') : '';
            
            // Schließe Modal
            $this->dueDateModalShow = false;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fälligkeitsdatum gespeichert',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung, diese Aufgabe zu bearbeiten.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving due date: ' . $e->getMessage(), [
                'task_id' => $this->task->id,
                'selectedDate' => $this->selectedDate,
                'selectedHour' => $this->selectedHour,
                'selectedMinute' => $this->selectedMinute,
            ]);
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Speichern: ' . $e->getMessage(),
            ]);
        }
    }

    public function clearDueDate()
    {
        $this->authorize('update', $this->task);
        $this->task->due_date = null;
        $this->task->save();
        
        // Lade die Task neu
        $this->task = $this->task->fresh();
        
        $this->dueDateInput = '';
        $this->dueDateModalShow = false;
        $this->selectedDate = null;
        $this->selectedTime = null;
        $this->selectedHour = 12;
        $this->selectedMinute = 0;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Fälligkeitsdatum entfernt',
        ]);
    }

	public function render()
    {        
        $this->printingAvailable = interface_exists('Platform\\Printing\\Contracts\\PrintingServiceInterface')
            && app()->bound('Platform\\Printing\\Contracts\\PrintingServiceInterface');

        $printers = collect();
        $groups = collect();
        if ($this->printingAvailable) {
            $printing = app('Platform\\Printing\\Contracts\\PrintingServiceInterface');
            $printers = $printing->listPrinters();
            $groups   = $printing->listPrinterGroups();
        }

        // Projekt-Mitglieder für Verantwortliche-Auswahl laden (wenn Aufgabe zu Projekt gehört)
        // Sonst Team-Mitglieder als Fallback
        if ($this->task->project_id && $this->task->project) {
            $projectUsers = $this->task->project
                ->projectUsers()
                ->with('user')
                ->get()
                ->map(function ($projectUser) {
                    $user = $projectUser->user;
                    if (!$user) {
                        return null;
                    }
                    return [
                        'id' => $user->id,
                        'name' => $user->fullname ?? $user->name,
                        'email' => $user->email,
                    ];
                })
                ->filter()
                ->sortBy('name')
                ->values();
            
            $teamUsers = $projectUsers;
        } else {
            // Fallback: Team-Mitglieder für Aufgaben ohne Projekt
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
        }

        return view('planner::livewire.task', [
            'printers' => $printers,
            'printerGroups' => $groups,
            'printingAvailable' => $this->printingAvailable,
            'teamUsers' => $teamUsers,
        ])->layout('platform::layouts.app');
    }
}
