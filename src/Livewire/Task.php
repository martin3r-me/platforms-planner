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
        'task.planned_minutes' => 'nullable|integer|min:0',
        'task.user_in_charge_id' => 'nullable|integer',
        'task.priority' => 'required|in:low,normal,high',
        'task.story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
        'task.project_id' => 'nullable|integer',
    ];

    public function mount($plannerTask)
    {
        // Wenn Task nicht existiert (z.B. wurde gelöscht), weiterleiten
        if (!$plannerTask || !($plannerTask instanceof PlannerTask)) {
            $this->redirect(route('planner.my-tasks'), navigate: true);
            return;
        }
        
        $this->authorize('view', $plannerTask);
        $this->task = $plannerTask->load(['user', 'userInCharge', 'project', 'team']);
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

    #[Computed]
    public function canAccessProject(): bool
    {
        if (!$this->task || !$this->task->project) {
            return false;
        }

        $user = Auth::user();
        if (!$user || !$user->current_team_id) {
            return false;
        }

        // Prüfe ob das Projekt zum aktuellen Team gehört
        return $this->task->project->team_id === $user->current_team_id;
    }

    #[Computed]
    public function activities()
    {
        if (!$this->task) {
            return collect();
        }

        return $this->task->activities()
            ->with('user')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                $title = $this->formatActivityTitle($activity);
                $time = $activity->created_at->diffForHumans();
                
                return [
                    'id' => $activity->id,
                    'title' => $title,
                    'time' => $time,
                    'user' => $activity->user?->name ?? 'System',
                    'type' => $activity->activity_type,
                    'name' => $activity->name,
                ];
            });
    }

    private function formatActivityTitle($activity): string
    {
        $userName = $activity->user?->name ?? 'System';
        $activityName = $activity->name;
        
        // Übersetze Activity-Namen
        $translations = [
            'created' => 'erstellt',
            'updated' => 'aktualisiert',
            'deleted' => 'gelöscht',
            'manual' => 'hat eine Nachricht hinzugefügt',
        ];
        
        $translatedName = $translations[$activityName] ?? $activityName;
        
        // Wenn es eine Nachricht gibt, zeige diese
        if ($activity->message) {
            return "{$userName}: {$activity->message}";
        }
        
        // Wenn es Änderungen gibt, zeige diese
        if ($activity->properties && !empty($activity->properties)) {
            $props = $activity->properties;
            $changedFields = [];
            
            // Prüfe ob es old/new gibt (strukturierte Properties)
            if (isset($props['old']) || isset($props['new'])) {
                if (isset($props['old']) && isset($props['new'])) {
                    $changedFields = array_keys($props['new']);
                } elseif (isset($props['new'])) {
                    $changedFields = array_keys($props['new']);
                }
            } else {
                // Direkte Properties (z.B. bei created)
                $changedFields = array_keys($props);
            }
            
            if (!empty($changedFields)) {
                $fieldNames = array_map(function($field) {
                    $translations = [
                        'title' => 'Titel',
                        'description' => 'Beschreibung',
                        'due_date' => 'Fälligkeitsdatum',
                        'is_done' => 'Status',
                        'is_frog' => 'Frosch',
                        'priority' => 'Priorität',
                        'story_points' => 'Story Points',
                        'user_in_charge_id' => 'Verantwortlicher',
                    ];
                    return $translations[$field] ?? $field;
                }, $changedFields);
                
                $fields = implode(', ', $fieldNames);
                return "{$userName} hat {$fields} {$translatedName}";
            }
        }
        
        // Standard-Format
        return "{$userName} hat die Aufgabe {$translatedName}";
    }

    public function rendered()
    {
        // Wenn Task gelöscht wurde, nichts tun (wird nach Redirect nicht mehr aufgerufen)
        if (!$this->task) {
            return;
        }

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

        // Organization-Kontext setzen - nur Zeiten erlauben, keine Entity-Verknüpfung
        $this->dispatch('organization', [
            'context_type' => get_class($this->task),
            'context_id' => $this->task->id,
            'linked_contexts' => $this->task->project ? [['type' => get_class($this->task->project), 'id' => $this->task->project->id]] : [],
            'allow_time_entry' => true,
            'allow_context_management' => false,
            'can_link_to_entity' => false,
        ]);

        // KeyResult-Kontext setzen - ermöglicht Verknüpfung von KeyResults mit dieser Task
        $this->dispatch('keyresult', [
            'context_type' => get_class($this->task),
            'context_id' => $this->task->id,
        ]);
    }

    public function updatedDueDateInput($value)
    {
        // Für Kompatibilität mit anderen Eingabewegen nur den lokalen State setzen
        $this->dueDateInput = $value;
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
        // done_at wird automatisch vom PlannerTaskObserver gesetzt
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
        
        // Alle notwendigen Informationen VOR dem Löschen speichern
        $taskTitle = $this->task->title;
        $projectId = $this->task->project_id;
        $hasProject = $this->task->project !== null;
        $canAccessProject = $hasProject && $this->canAccessProject;
        
        // Zielroute vor dem Löschen bestimmen
        // Nur zum Projekt redirecten, wenn es zum aktuellen Team gehört
        if (!$hasProject || !$canAccessProject) {
            $redirectUrl = route('planner.my-tasks');
        } else {
            // Projekt-ID direkt verwenden, um Model-Binding zu vermeiden
            $redirectUrl = route('planner.projects.show', ['plannerProject' => $projectId]);
        }
        
        // Task löschen
        $this->task->delete();
        
        // Task-Property auf null setzen, damit render() und rendered() nicht mehr darauf zugreifen
        $this->task = null;
        
        // Notification dispatchen (wird nach Redirect verarbeitet)
        $this->dispatch('notifications:store', [
            'notice_type' => 'info',
            'title' => 'Aufgabe gelöscht',
            'message' => "Die Aufgabe '{$taskTitle}' wurde gelöscht.",
        ]);
        
        // Sofort redirecten - render() wird übersprungen, da $this->task null ist
        $this->redirect($redirectUrl, navigate: true);
    }

    public function deleteTaskAndReturnToDashboard()
    {
        $this->authorize('delete', $this->task);
        $this->task->delete();
        $this->task = null;
        return $this->redirect(route('planner.my-tasks'), navigate: true);
    }

    public function deleteTaskAndReturnToProject()
    {
        $this->authorize('delete', $this->task);
        
        // Projekt-ID vor dem Löschen speichern
        $projectId = $this->task->project_id;
        $canAccessProject = $projectId && $this->canAccessProject;
        
        if (!$projectId || !$canAccessProject) {
            // Fallback zu MyTasks wenn kein Projekt vorhanden oder nicht zum aktuellen Team
            $this->task->delete();
            $this->task = null;
            return $this->redirect(route('planner.my-tasks'), navigate: true);
        }
        
        // Task löschen
        $this->task->delete();
        $this->task = null;
        
        // Redirect mit Projekt-ID (direkt, nicht über Model)
        return $this->redirect(route('planner.projects.show', ['plannerProject' => $projectId]), navigate: true);
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
        // Standardzeit nach Datumsauswahl auf 12:00 setzen
        $this->selectedHour = 12;
        $this->selectedMinute = 0;
        $this->updateSelectedTime();
    }

    public function updatedSelectedHour($value)
    {
        $this->selectedHour = (int) $value;
        $this->updateSelectedTime();
    }

    public function updatedSelectedMinute($value)
    {
        $this->selectedMinute = (int) $value;
        $this->updateSelectedTime();
    }

    private function updateSelectedTime()
    {
        $this->selectedTime = sprintf('%02d:%02d', (int) $this->selectedHour, (int) $this->selectedMinute);
    }

    public function saveDueDate()
    {
        try {
            $this->authorize('update', $this->task);

            // Setze das Datum
            if (empty($this->selectedDate)) {
                $this->task->due_date = null;
            } else {
                $hour = $this->selectedHour ?? 12;
                $minute = $this->selectedMinute ?? 0;
                $time = sprintf('%02d:%02d', $hour, $minute);
                $this->task->due_date = \Carbon\Carbon::parse("{$this->selectedDate} {$time}");
            }

            // Speichere
            $this->task->save();
            
            // Aktualisiere das Model im gleichen Objekt
            $this->task->refresh();
            
            // Relationen neu laden
            $this->task->load(['user', 'userInCharge', 'project', 'team']);
            
            // Aktualisiere dueDateInput und den Selektions-State
            $this->dueDateInput = $this->task->due_date ? $this->task->due_date->format('Y-m-d H:i') : '';
            $this->selectedDate = $this->task->due_date ? $this->task->due_date->format('Y-m-d') : null;
            $this->selectedHour = $this->task->due_date ? (int) $this->task->due_date->format('H') : 12;
            $this->selectedMinute = $this->task->due_date ? (int) $this->task->due_date->format('i') : 0;
            
            // Modal schließen
            $this->dueDateModalShow = false;

            // Notification
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
                'selectedHour' => $this->selectedHour ?? null,
                'selectedMinute' => $this->selectedMinute ?? null,
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
        $this->task->refresh();
        // Relationen neu laden
        $this->task->load(['user', 'userInCharge', 'project', 'team']);
        
        $this->dueDateInput = '';
        $this->selectedDate = null;
        $this->selectedTime = null;
        $this->selectedHour = 12;
        $this->selectedMinute = 0;
        
        $this->dueDateModalShow = false;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Fälligkeitsdatum entfernt',
        ]);
    }

	public function render()
    {
        // Wenn Task gelöscht wurde, sofort redirecten (verhindert 404)
        if (!$this->task) {
            // Fallback-Redirect zu MyTasks
            return $this->redirect(route('planner.my-tasks'), navigate: true);
        }
        
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
            'activities' => $this->activities,
        ])->layout('platform::layouts.app');
    }
}
