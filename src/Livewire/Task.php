<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Platform\Core\Livewire\Concerns\WithExtraFields;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;


class Task extends Component
{
    use WithExtraFields;
	public $task;
    public $description; // Separate Property für entschlüsselte description
    public $dod; // Separate Property für entschlüsselte dod (JSON-Format: Array von {text, checked})
    public array $dodItems = []; // Parsed DoD-Items als Array
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
    public $targetProjectId = null;
    public $targetSlotId = null;
    public bool $moveModalOpen = false;
    public array $projectMoveOptions = [];
    public array $projectSlotOptions = [];

	protected $rules = [
        'task.title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'dod' => 'nullable|string',
        'task.is_frog' => 'boolean',
        'task.is_forced_frog' => 'boolean',
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
        
        // Verschüsselte Felder explizit lesen über den Cast (löst Entschlüsselung aus)
        // In separate Properties speichern (wie bei Checkin mit Arrays)
        $this->description = $this->task->description; // Löst Cast aus -> entschlüsselt
        $this->dod = $this->task->dod; // Löst Cast aus -> entschlüsselt

        // DoD-Items parsen (vom alten Format in neues Checklist-Format konvertieren falls nötig)
        $this->dodItems = $this->parseDodItems($this->dod);
        
        $this->dueDateInput = $plannerTask->due_date ? $plannerTask->due_date->format('Y-m-d H:i') : '';
        $this->targetProjectId = $plannerTask->project_id;
        $this->targetSlotId = $plannerTask->project_slot_id;
        $this->loadProjectMoveOptions();
        $this->syncProjectSlotOptions();

        // Extra-Felder laden (Definitionen vom Projekt, Werte von der Task)
        if ($this->task->project) {
            $this->loadExtraFieldValuesFromParent($this->task, $this->task->project);
        }
    }

    #[Computed]
    public function isDirty(): bool
    {
        if (!$this->task) {
            return false;
        }

        // Prüfe Model-Änderungen (nur wenn wirklich ungespeichert)
        $modelDirty = count($this->task->getDirty()) > 0;

        // Prüfe verschlüsselte Felder (separate Properties)
        // Vergleiche mit entschlüsselten Werten aus dem Model
        $currentDescription = $this->task->description ?? '';

        $descriptionDirty = isset($this->description) && $this->description !== $currentDescription;

        // DoD: Vergleiche serialisierte dodItems mit gespeichertem Wert
        $currentDod = $this->task->dod ?? '';
        $serializedDodItems = $this->serializeDodItems($this->dodItems);
        $dodDirty = $serializedDodItems !== $currentDod;

        // Extra-Felder prüfen
        $extraFieldsDirty = $this->isExtraFieldsDirty();

        return $modelDirty || $descriptionDirty || $dodDirty || $extraFieldsDirty;
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

        // Extra-Fields-Kontext setzen (zeigt auf Projekt für Definitionen)
        if ($this->task->project) {
            $this->dispatch('extrafields', [
                'context_type' => get_class($this->task->project),
                'context_id' => $this->task->project->id,
            ]);
        }

        $this->dispatch('comms', [
            'model' => get_class($this->task),                                // z. B. 'Platform\Planner\Models\PlannerTask'
            'modelId' => $this->task->id,
            'subject' => $this->task->title,
            'description' => $this->task->description ?? '',
            'url' => route('planner.tasks.show', $this->task),                // absolute URL zum Task
            'source' => 'planner.task.view',                                 // eindeutiger Quell-Identifier (frei wählbar)
            'recipients' => [$this->task->user_in_charge_id],                // falls vorhanden, sonst leer
            'capabilities' => [
                'manage_channels' => false,
                'threads' => true,
            ],
            'meta' => [
                'priority' => $this->task->priority,
                'due_date' => $this->task->due_date,
                'story_points' => $this->task->story_points,
            ],
        ]);

        // Organization-Kontext setzen - nur Zeiten erlauben, keine Entity-Verknüpfung, keine Dimensionen
        $this->dispatch('organization', [
            'context_type' => get_class($this->task),
            'context_id' => $this->task->id,
            'linked_contexts' => $this->task->project ? [['type' => get_class($this->task->project), 'id' => $this->task->project->id]] : [],
            'allow_time_entry' => true,
            'allow_entities' => false,
            'allow_dimensions' => false,
        ]);

        // KeyResult-Kontext setzen - ermöglicht Verknüpfung von KeyResults mit dieser Task
        $this->dispatch('keyresult', [
            'context_type' => get_class($this->task),
            'context_id' => $this->task->id,
        ]);

        // Tagging-Kontext setzen - ermöglicht Tagging dieser Task
        $this->dispatch('tagging', [
            'context_type' => get_class($this->task),
            'context_id' => $this->task->id,
        ]);

        // Files-Kontext setzen - ermöglicht Datei-Upload für diese Task
        $this->dispatch('files', [
            'context_type' => get_class($this->task),
            'context_id' => $this->task->id,
        ]);

        // Playground-Kontext setzen - ermöglicht LLM den Task-Kontext zu kennen
        \Log::info('[Playground] Task dispatching playground event', ['task_id' => $this->task->id, 'title' => $this->task->title]);
        $this->dispatch('playground', [
            'type' => 'Task',
            'model' => get_class($this->task),
            'modelId' => $this->task->id,
            'title' => $this->task->title,
            'description' => $this->task->description ?? '',
            'url' => route('planner.tasks.show', $this->task),
            'source' => 'planner.task.view',
            'meta' => [
                'priority' => $this->task->priority,
                'due_date' => $this->task->due_date?->toIso8601String(),
                'story_points' => $this->task->story_points,
                'is_done' => $this->task->is_done,
                'project' => $this->task->project?->name,
            ],
        ]);
    }

    public function updatedDueDateInput($value)
    {
        // Für Kompatibilität mit anderen Eingabewegen nur den lokalen State setzen
        $this->dueDateInput = $value;
    }

    public function updatedDescription($value)
    {
        if (!$this->task) {
            return;
        }
        
        $this->validateOnly('description');
        
        // Setze Wert im Model (Cast verschlüsselt automatisch)
        $this->task->description = $value;
        
        // Prüfe ob wirklich geändert (verhindert unnötige Speicherungen)
        if ($this->task->isDirty('description')) {
            $this->task->save();
            
            // Lade entschlüsselten Wert wieder in Property
            $this->description = $this->task->description;
            
            // Dezentes Feedback
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Anmerkung gespeichert',
            ]);
        }
    }

    public function updatedDod($value)
    {
        if (!$this->task) {
            return;
        }
        
        $this->validateOnly('dod');
        
        // Setze Wert im Model (Cast verschlüsselt automatisch)
        $this->task->dod = $value;
        
        // Prüfe ob wirklich geändert (verhindert unnötige Speicherungen)
        if ($this->task->isDirty('dod')) {
            $this->task->save();
            
            // Lade entschlüsselten Wert wieder in Property
            $this->dod = $this->task->dod;
            
            // Dezentes Feedback
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Definition of Done gespeichert',
            ]);
        }
    }

    public function updatedTask($property, $value)
    {
        if (!$this->task) {
            return;
        }
        
        // Überspringe description und dod, die haben eigene Handler
        if (in_array($property, ['description', 'dod'])) {
            return;
        }
        
        $this->validateOnly("task.$property");
        
        // Nur speichern wenn sich wirklich was geändert hat
        if ($this->task->isDirty($property)) {
            try {
                $this->task->save();
                
                // Dezentes Feedback für Auto-Save (nur bei wichtigen Feldern)
                $importantFields = ['title', 'priority', 'story_points', 'user_in_charge_id'];
                if (in_array($property, $importantFields)) {
                    $this->dispatch('notify', [
                        'type' => 'success',
                        'message' => 'Änderungen gespeichert',
                    ]);
                }
            } catch (\Exception $e) {
                // Bei Fehler: Benutzer kann manuell speichern
                // Der Speichern-Button wird durch isDirty() angezeigt
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Auto-Save fehlgeschlagen. Bitte manuell speichern.',
                ]);
            }
        }
    }

    public function save()
    {
        if (!$this->task) {
            return;
        }
        
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
        
        // Speichere verschlüsselte Felder über separate Properties
        if (isset($this->description)) {
            $this->task->description = $this->description;
        }
        // DoD wird als JSON gespeichert (über dodItems)
        $this->dod = $this->serializeDodItems($this->dodItems);
        $this->task->dod = $this->dod;

        $this->task->save();
        $this->saveExtraFieldValues($this->task);

        // Lade die Task neu für die Anzeige
        $this->task = $this->task->fresh(['user', 'userInCharge', 'project', 'team']);

        // Lade entschlüsselte Werte wieder in separate Properties
        $this->description = $this->task->description;
        $this->dod = $this->task->dod;
        $this->dodItems = $this->parseDodItems($this->dod);

        // Extra-Felder neu laden
        $this->loadExtraFieldValues($this->task);

        // Toast-Notification
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Alle Änderungen gespeichert',
        ]);
    }

    public function updatedTargetProjectId($value): void
    {
        $this->targetProjectId = $value ? (int) $value : null;
        $this->targetSlotId = null; // Standard: Backlog im Zielprojekt
        $this->syncProjectSlotOptions();
    }

    public function updatedTargetSlotId($value): void
    {
        $this->targetSlotId = $value ? (int) $value : null;
    }

    public function openMoveModal(): void
    {
        $this->moveModalOpen = true;
    }

    public function closeMoveModal(): void
    {
        $this->moveModalOpen = false;
    }

    /**
     * Lädt alle Projekte, die der aktuelle User sehen darf (Policy: view).
     * Nur diese Projekte stehen als Ziel für einen Move zur Verfügung.
     */
    private function loadProjectMoveOptions(): void
    {
        $user = Auth::user();
        $taskTeamId = $this->task?->team_id ?? $user->currentTeam?->id;

        $projects = PlannerProject::query()
            ->with(['projectSlots' => fn ($q) => $q->orderBy('order')])
            ->where('team_id', $taskTeamId)
            ->where(function ($query) use ($user) {
                // Projekte, in denen der User Mitglied ist oder Aufgaben hat
                $query->whereHas('projectUsers', fn ($q) => $q->where('user_id', $user->id))
                      ->orWhereHas('tasks', fn ($q) => $q->where('user_in_charge_id', $user->id))
                      ->orWhereHas('projectSlots.tasks', fn ($q) => $q->where('user_in_charge_id', $user->id));
            })
            ->orderBy('name')
            ->get()
            ->filter(fn ($project) => $user->can('view', $project))
            ->values();

        $this->projectMoveOptions = $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'team_id' => $project->team_id,
                'slots' => $project->projectSlots->map(fn ($slot) => [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'order' => $slot->order,
                ])->values()->toArray(),
            ];
        })->toArray();
    }

    /**
     * Baut Slot-Optionen für das aktuell gewählte Ziel-Projekt auf.
     */
    private function syncProjectSlotOptions(): void
    {
        $selectedProject = collect($this->projectMoveOptions)
            ->firstWhere('id', $this->targetProjectId);

        $slots = collect($selectedProject['slots'] ?? [])
            ->map(fn ($slot) => [
                'id' => $slot['id'],
                'name' => $slot['name'],
            ]);

        // Backlog-Option immer anbieten
        $this->projectSlotOptions = collect([
            ['id' => null, 'name' => 'Backlog'],
        ])->concat($slots)->values()->toArray();
    }

    /**
     * Verschiebt die Aufgabe in ein anderes Projekt und optional in einen Slot.
     * Nur Projekte, die der User laut Policy sehen darf, sind erlaubt.
     */
    public function moveTaskToProject(): void
    {
        $this->authorize('update', $this->task);

        if (! $this->targetProjectId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Bitte wähle ein Zielprojekt aus.',
            ]);
            return;
        }

        $targetProject = PlannerProject::with('projectSlots')->find($this->targetProjectId);

        if (! $targetProject) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Zielprojekt wurde nicht gefunden.',
            ]);
            return;
        }

        // Zugriffsprüfung: nur Projekte, die der User sehen darf
        if (! Auth::user()->can('view', $targetProject)) {
            abort(403, 'Kein Zugriff auf das Zielprojekt.');
        }

        // Team-Konsistenz: nur Projekte im gleichen Team wie die Aufgabe
        if ($targetProject->team_id !== $this->task->team_id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Das Zielprojekt gehört zu einem anderen Team.',
            ]);
            return;
        }

        $targetSlotId = $this->targetSlotId ? (int) $this->targetSlotId : null;

        if ($targetSlotId) {
            $slotBelongsToProject = $targetProject->projectSlots->contains(fn (PlannerProjectSlot $slot) => $slot->id === $targetSlotId);
            if (! $slotBelongsToProject) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Der ausgewählte Slot gehört nicht zum Zielprojekt.',
                ]);
                return;
            }
        }

        // Neuen Order-Wert im Ziel-Slot bestimmen (oben einreihen)
        $lowestOrder = PlannerTask::query()
            ->where('project_id', $targetProject->id)
            ->where('project_slot_id', $targetSlotId)
            ->min('project_slot_order');

        $newOrder = $lowestOrder === null ? 0 : $lowestOrder - 1;

        // Aufgabe aktualisieren
        $this->task->project_id = $targetProject->id;
        $this->task->project_slot_id = $targetSlotId;
        $this->task->project_slot_order = $newOrder;
        $this->task->team_id = $targetProject->team_id;
        // Persönliche Gruppierungen nicht anfassen; Delegations-Gruppen zurücksetzen
        $this->task->delegated_group_id = null;
        $this->task->delegated_group_order = 0;

        $this->task->save();
        $this->task->load(['user', 'userInCharge', 'project', 'team']);

        // UI-State angleichen
        $this->targetProjectId = $this->task->project_id;
        $this->targetSlotId = $this->task->project_slot_id;
        $this->syncProjectSlotOptions();
        $this->closeMoveModal();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Aufgabe wurde verschoben.',
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
        if ($this->task->is_forced_frog) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Frosch-Status ist gesperrt (Zwangs-Frosch).',
            ]);
            return;
        }
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
        
        // Toast-Notification dispatchen (keine persistente Notification, da Task gelöscht)
        $this->dispatch('notify', [
            'type' => 'info',
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

    /**
     * Prüft ob ein Wert bereits verschlüsselt ist
     */
    private function isEncryptedValue(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Laravel Crypt erzeugt base64-kodierte Strings
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        // Verschlüsselte Werte haben typischerweise eine Mindestlänge
        // und enthalten nicht-printable Zeichen nach Decodierung
        return strlen($decoded) > 16 && !ctype_print($decoded);
    }

    /**
     * Parst DoD-Wert in ein Array von Items.
     * Unterstützt sowohl das neue JSON-Format als auch das alte Plaintext-Format.
     *
     * Neues Format: JSON-Array [{"text": "Punkt 1", "checked": false}, ...]
     * Altes Format: Plaintext mit Zeilen, evtl. mit "- [ ]" oder "- [x]" Markdown-Checkboxen
     */
    private function parseDodItems(?string $dod): array
    {
        if (empty($dod)) {
            return [];
        }

        // Versuche zuerst als JSON zu parsen (neues Format)
        $decoded = json_decode($dod, true);
        if (is_array($decoded) && !empty($decoded)) {
            // Prüfe ob es das neue Format ist (Array von Objekten mit text und checked)
            $firstItem = reset($decoded);
            if (is_array($firstItem) && array_key_exists('text', $firstItem)) {
                return array_values(array_map(function ($item) {
                    return [
                        'text' => trim($item['text'] ?? ''),
                        'checked' => (bool)($item['checked'] ?? false),
                    ];
                }, $decoded));
            }
        }

        // Altes Format: Plaintext in Zeilen aufteilen
        $lines = preg_split('/\r\n|\r|\n/', $dod);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Prüfe auf Markdown-Checkbox-Format "- [ ] Text" oder "- [x] Text"
            if (preg_match('/^[-*]\s*\[([ xX])\]\s*(.+)$/', $line, $matches)) {
                $items[] = [
                    'text' => trim($matches[2]),
                    'checked' => strtolower($matches[1]) === 'x',
                ];
            }
            // Prüfe auf einfaches Listenformat "- Text" oder "* Text"
            elseif (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $items[] = [
                    'text' => trim($matches[1]),
                    'checked' => false,
                ];
            }
            // Einfacher Text ohne Format
            else {
                $items[] = [
                    'text' => $line,
                    'checked' => false,
                ];
            }
        }

        return $items;
    }

    /**
     * Konvertiert DoD-Items Array zurück zu JSON-String für die Speicherung.
     */
    private function serializeDodItems(array $items): string
    {
        // Filtere leere Items heraus
        $items = array_values(array_filter($items, function ($item) {
            return !empty(trim($item['text'] ?? ''));
        }));

        if (empty($items)) {
            return '';
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Schaltet den checked-Status eines DoD-Items um.
     */
    public function toggleDodItem(int $index): void
    {
        if (!$this->task || !isset($this->dodItems[$index])) {
            return;
        }

        $this->authorize('update', $this->task);

        // Toggle checked-Status
        $this->dodItems[$index]['checked'] = !$this->dodItems[$index]['checked'];

        // In JSON serialisieren und speichern
        $this->dod = $this->serializeDodItems($this->dodItems);
        $this->task->dod = $this->dod;
        $this->task->save();

        // Feedback
        $itemText = $this->dodItems[$index]['text'];
        $status = $this->dodItems[$index]['checked'] ? 'erledigt' : 'offen';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "DoD-Punkt als {$status} markiert",
        ]);
    }

    /**
     * Fügt ein neues DoD-Item hinzu.
     */
    public function addDodItem(string $text): void
    {
        if (!$this->task || empty(trim($text))) {
            return;
        }

        $this->authorize('update', $this->task);

        // Neues Item hinzufügen
        $this->dodItems[] = [
            'text' => trim($text),
            'checked' => false,
        ];

        // Speichern
        $this->dod = $this->serializeDodItems($this->dodItems);
        $this->task->dod = $this->dod;
        $this->task->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'DoD-Punkt hinzugefügt',
        ]);
    }

    /**
     * Entfernt ein DoD-Item.
     */
    public function removeDodItem(int $index): void
    {
        if (!$this->task || !isset($this->dodItems[$index])) {
            return;
        }

        $this->authorize('update', $this->task);

        // Item entfernen
        array_splice($this->dodItems, $index, 1);

        // Speichern
        $this->dod = $this->serializeDodItems($this->dodItems);
        $this->task->dod = $this->dod;
        $this->task->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'DoD-Punkt entfernt',
        ]);
    }

    /**
     * Aktualisiert den Text eines DoD-Items.
     */
    public function updateDodItemText(int $index, string $text): void
    {
        if (!$this->task || !isset($this->dodItems[$index])) {
            return;
        }

        $this->authorize('update', $this->task);

        // Text aktualisieren
        $this->dodItems[$index]['text'] = trim($text);

        // Leere Items entfernen
        if (empty($this->dodItems[$index]['text'])) {
            array_splice($this->dodItems, $index, 1);
        }

        // Speichern
        $this->dod = $this->serializeDodItems($this->dodItems);
        $this->task->dod = $this->dod;
        $this->task->save();
    }

    /**
     * Berechnet den DoD-Fortschritt.
     */
    #[Computed]
    public function dodProgress(): array
    {
        $total = count($this->dodItems);
        $checked = count(array_filter($this->dodItems, fn($item) => $item['checked']));

        return [
            'total' => $total,
            'checked' => $checked,
            'percentage' => $total > 0 ? round(($checked / $total) * 100) : 0,
            'isComplete' => $total > 0 && $checked === $total,
        ];
    }
}
