<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Carbon\Carbon;

/**
 * Tool zum Bearbeiten von Aufgaben im Planner-Modul
 * 
 * Unterstützt auch das Verschieben von Aufgaben zwischen Slots oder Projekten.
 */
class UpdateTaskTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.tasks.PUT';
    }

    public function getDescription(): string
    {
        return 'Bearbeitet eine bestehende Aufgabe. RUF DIESES TOOL AUF, wenn der Nutzer eine Aufgabe ändern möchte (Titel, Beschreibung, DoD, Fälligkeitsdatum, etc.) oder wenn eine Aufgabe in einen anderen Slot oder ein anderes Projekt verschoben werden soll. Die Task-ID ist erforderlich. Nutze "planner.tasks.GET" um Aufgaben zu finden. WICHTIG: Um eine Aufgabe in einen anderen Slot zu verschieben, setze "project_slot_id" auf die neue Slot-ID. Um eine Aufgabe in ein anderes Projekt zu verschieben, setze "project_id" auf die neue Projekt-ID.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => [
                    'type' => 'integer',
                    'description' => 'ID der zu bearbeitenden Aufgabe (ERFORDERLICH). Nutze "planner.tasks.GET" um Aufgaben zu finden.'
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Titel der Aufgabe. Frage nach, wenn der Nutzer den Titel ändern möchte.'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung der Aufgabe. Frage nach, wenn der Nutzer die Beschreibung ändern möchte.'
                ],
                'dod' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Definition of Done (DoD). Frage nach, wenn der Nutzer die DoD ändern möchte.'
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues Fälligkeitsdatum im Format YYYY-MM-DD oder ISO 8601. Frage nach, wenn der Nutzer das Datum ändern möchte. Setze auf null oder leeren String, um das Datum zu entfernen.'
                ],
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Projekt-ID. Nutze "planner.projects.GET" um Projekte zu finden. Setze auf null, um die Aufgabe zu einer persönlichen Aufgabe zu machen. WICHTIG: Wenn du project_id änderst, wird project_slot_id automatisch auf null gesetzt, es sei denn, du setzt auch project_slot_id.'
                ],
                'project_slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Slot-ID. Nutze "planner.project_slots.GET" um Slots zu finden. Setze auf null, um die Aufgabe aus dem Slot ins Backlog zu verschieben. WICHTIG: Um eine Aufgabe in einen anderen Slot zu verschieben, setze diese ID. Der Slot muss zum gleichen Projekt gehören wie project_id.'
                ],
                'user_in_charge_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue User-ID des zuständigen Users. Frage nach, wenn der Nutzer die Zuständigkeit ändern möchte.'
                ],
                'planned_minutes' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue geplante Minuten. Frage nach, wenn der Nutzer die Zeit ändern möchte.'
                ],
                'is_done' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Aufgabe als erledigt markieren. Frage nach, wenn der Nutzer die Aufgabe abschließen möchte.'
                ]
            ],
            'required' => ['task_id']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['task_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Task-ID ist erforderlich. Nutze "planner.tasks.GET" um Aufgaben zu finden.');
            }

            // Task finden
            $task = PlannerTask::find($arguments['task_id']);
            if (!$task) {
                return ToolResult::error('TASK_NOT_FOUND', 'Die angegebene Aufgabe wurde nicht gefunden. Nutze "planner.tasks.GET" um alle verfügbaren Aufgaben zu sehen.');
            }

            // Prüfe Zugriff (User muss Owner der Aufgabe sein oder Zugriff auf das Projekt haben)
            if ($task->user_in_charge_id !== $context->user->id && $task->user_id !== $context->user->id) {
                if ($task->project_id) {
                    $project = $task->project;
                    $hasAccess = $project->projectUsers()
                        ->where('user_id', $context->user->id)
                        ->whereIn('role', ['owner', 'admin'])
                        ->exists();
                    
                    if (!$hasAccess && $project->user_id !== $context->user->id) {
                        return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, diese Aufgabe zu bearbeiten.');
                    }
                } else {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, diese Aufgabe zu bearbeiten.');
                }
            }

            // Update-Daten sammeln
            $updateData = [];

            if (isset($arguments['title'])) {
                $updateData['title'] = $arguments['title'];
            }

            if (isset($arguments['description'])) {
                $updateData['description'] = $arguments['description'];
            }

            if (isset($arguments['dod'])) {
                $updateData['dod'] = $arguments['dod'];
            }

            // Fälligkeitsdatum
            if (isset($arguments['due_date'])) {
                if (empty($arguments['due_date']) || $arguments['due_date'] === 'null') {
                    $updateData['due_date'] = null;
                } else {
                    try {
                        $updateData['due_date'] = Carbon::parse($arguments['due_date']);
                    } catch (\Exception $e) {
                        return ToolResult::error('INVALID_DATE', 'Ungültiges Datumsformat. Verwende YYYY-MM-DD oder ISO 8601 (z.B. "2025-01-20").');
                    }
                }
            }

            // Projekt ändern
            $projectChanged = false;
            if (isset($arguments['project_id'])) {
                if (empty($arguments['project_id']) || $arguments['project_id'] === 0) {
                    // Persönliche Aufgabe machen
                    $updateData['project_id'] = null;
                    $updateData['project_slot_id'] = null; // Slot muss auch entfernt werden
                    $projectChanged = true;
                } else {
                    // Neues Projekt prüfen
                    $newProject = PlannerProject::find($arguments['project_id']);
                    if (!$newProject) {
                        return ToolResult::error('PROJECT_NOT_FOUND', 'Das angegebene Projekt wurde nicht gefunden. Nutze "planner.projects.GET" um alle verfügbaren Projekte zu sehen.');
                    }

                    // Prüfe Zugriff auf neues Projekt
                    $hasAccess = $newProject->projectUsers()
                        ->where('user_id', $context->user->id)
                        ->exists();
                    
                    if (!$hasAccess && $newProject->user_id !== $context->user->id) {
                        return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf das angegebene Projekt.');
                    }

                    $updateData['project_id'] = $newProject->id;
                    $updateData['team_id'] = $newProject->team_id; // Team vom Projekt übernehmen
                    $projectChanged = true;
                }
            }

            // Slot ändern (Verschieben zwischen Slots)
            $slotChanged = false;
            if (isset($arguments['project_slot_id'])) {
                if (empty($arguments['project_slot_id']) || $arguments['project_slot_id'] === 0) {
                    // Aus Slot ins Backlog verschieben
                    $updateData['project_slot_id'] = null;
                    $updateData['project_slot_order'] = null;
                    $slotChanged = true;
                } else {
                    // Neuen Slot prüfen
                    $newSlot = PlannerProjectSlot::find($arguments['project_slot_id']);
                    if (!$newSlot) {
                        return ToolResult::error('SLOT_NOT_FOUND', 'Der angegebene Slot wurde nicht gefunden. Nutze "planner.project_slots.GET" um alle verfügbaren Slots zu sehen.');
                    }

                    // Prüfe, ob Slot zum Projekt gehört
                    $currentProjectId = $updateData['project_id'] ?? $task->project_id;
                    if ($newSlot->project_id !== $currentProjectId) {
                        return ToolResult::error('SLOT_MISMATCH', 'Der angegebene Slot gehört nicht zum angegebenen Projekt.');
                    }

                    $updateData['project_slot_id'] = $newSlot->id;
                    
                    // Order im neuen Slot berechnen
                    $maxOrder = PlannerTask::where('project_slot_id', $newSlot->id)
                        ->max('project_slot_order') ?? 0;
                    $updateData['project_slot_order'] = $maxOrder + 1;
                    $slotChanged = true;
                }
            }

            // Wenn Projekt geändert wurde, aber Slot nicht explizit gesetzt, Slot entfernen
            if ($projectChanged && !isset($arguments['project_slot_id'])) {
                $updateData['project_slot_id'] = null;
                $updateData['project_slot_order'] = null;
            }

            if (isset($arguments['user_in_charge_id'])) {
                $updateData['user_in_charge_id'] = $arguments['user_in_charge_id'];
            }

            if (isset($arguments['planned_minutes'])) {
                $updateData['planned_minutes'] = $arguments['planned_minutes'];
            }

            if (isset($arguments['is_done'])) {
                $updateData['is_done'] = $arguments['is_done'];
                if ($arguments['is_done']) {
                    $updateData['done_at'] = now();
                } else {
                    $updateData['done_at'] = null;
                }
            }

            // Task aktualisieren
            if (!empty($updateData)) {
                $task->update($updateData);
            }

            // Aktualisierte Task laden
            $task->refresh();
            $task->load(['project', 'projectSlot', 'userInCharge']);

            // Response zusammenstellen
            $response = [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,
                'dod' => $task->dod,
                'due_date' => $task->due_date?->toIso8601String(),
                'project_id' => $task->project_id,
                'project_name' => $task->project?->name,
                'project_slot_id' => $task->project_slot_id,
                'project_slot_name' => $task->projectSlot?->name,
                'user_in_charge_id' => $task->user_in_charge_id,
                'user_in_charge_name' => $task->userInCharge?->name ?? 'Unbekannt',
                'is_done' => $task->is_done,
                'done_at' => $task->done_at?->toIso8601String(),
                'is_personal' => $task->project_id === null,
                'updated_at' => $task->updated_at->toIso8601String(),
            ];

            // Message basierend auf Änderungen
            $changes = [];
            if ($slotChanged) {
                if ($task->project_slot_id) {
                    $changes[] = "in Slot '{$task->projectSlot->name}' verschoben";
                } else {
                    $changes[] = "ins Backlog verschoben";
                }
            }
            if ($projectChanged) {
                if ($task->project_id) {
                    $changes[] = "in Projekt '{$task->project->name}' verschoben";
                } else {
                    $changes[] = "zu persönlicher Aufgabe gemacht";
                }
            }
            
            $message = "Aufgabe '{$task->title}' erfolgreich aktualisiert.";
            if (!empty($changes)) {
                $message .= " " . implode(", ", $changes) . ".";
            }

            $response['message'] = $message;

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Aufgabe: ' . $e->getMessage());
        }
    }
}

