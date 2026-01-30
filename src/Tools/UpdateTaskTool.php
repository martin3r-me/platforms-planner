<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Bearbeiten von Aufgaben im Planner-Modul
 * 
 * Unterstützt auch das Verschieben von Aufgaben zwischen Slots oder Projekten.
 */
class UpdateTaskTool implements ToolContract
{
    use HasStandardizedWriteOperations;
    public function getName(): string
    {
        return 'planner.tasks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /tasks/{id} - Aktualisiert eine bestehende Aufgabe. Tool-Parameter: task_id (required). dod_items_update (optional, object) - Granulare DoD-Updates: toggle (Array von Indizes zum Umschalten), set_checked (Array von {index, checked}), add (Array von {text, checked}), remove (Array von Indizes), update_text (Array von {index, text}). dod_items (optional, array) - Komplette DoD als Array von {text, checked} (überschreibt alle). title, description, due_date, project_id, project_slot_id, user_in_charge_id, is_done sind weitere optionale Parameter.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Alias für task_id (Deprecated). Verwende bevorzugt task_id.'
                ],
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
                    'description' => 'Optional: Neue Definition of Done (DoD) als JSON-String. Format: [{"text": "Kriterium 1", "checked": false}]. ACHTUNG: Überschreibt alle bestehenden DoD-Items. Für granulare Änderungen nutze dod_items_update.'
                ],
                'dod_items' => [
                    'type' => 'array',
                    'description' => 'Optional: Komplette DoD-Items als Array (überschreibt alle bestehenden Items). Alternative zu dod-String.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'Text des DoD-Kriteriums'],
                            'checked' => ['type' => 'boolean', 'description' => 'Ob das Kriterium erfüllt ist']
                        ],
                        'required' => ['text']
                    ]
                ],
                'dod_items_update' => [
                    'type' => 'object',
                    'description' => 'Optional: Granulare DoD-Updates (ohne alle Items zu überschreiben). Kann toggle, add, remove und update_text Operationen enthalten.',
                    'properties' => [
                        'toggle' => [
                            'type' => 'array',
                            'description' => 'Array von Indizes (0-basiert), deren checked-Status umgeschaltet werden soll.',
                            'items' => ['type' => 'integer']
                        ],
                        'set_checked' => [
                            'type' => 'array',
                            'description' => 'Array von {index, checked} Objekten, um checked-Status explizit zu setzen.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'index' => ['type' => 'integer'],
                                    'checked' => ['type' => 'boolean']
                                ],
                                'required' => ['index', 'checked']
                            ]
                        ],
                        'add' => [
                            'type' => 'array',
                            'description' => 'Array von neuen DoD-Items zum Hinzufügen (am Ende der Liste).',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'text' => ['type' => 'string'],
                                    'checked' => ['type' => 'boolean']
                                ],
                                'required' => ['text']
                            ]
                        ],
                        'remove' => [
                            'type' => 'array',
                            'description' => 'Array von Indizes (0-basiert), die entfernt werden sollen. WICHTIG: Werden in absteigender Reihenfolge entfernt, damit die Indizes stimmen.',
                            'items' => ['type' => 'integer']
                        ],
                        'update_text' => [
                            'type' => 'array',
                            'description' => 'Array von {index, text} Objekten, um Text eines Items zu ändern.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'index' => ['type' => 'integer'],
                                    'text' => ['type' => 'string']
                                ],
                                'required' => ['index', 'text']
                            ]
                        ]
                    ]
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
                'story_points' => [
                    'type' => 'string',
                    'description' => 'Optional: Story Points der Aufgabe (xs|s|m|l|xl|xxl). Setze auf null/leeren String, um zu entfernen.',
                    'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl']
                ],
                'storyPoints' => [
                    'type' => 'string',
                    'description' => 'Alias für story_points.',
                    'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl']
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
            // Backward compatible: allow "id" as alias for "task_id"
            if (!array_key_exists('task_id', $arguments) && array_key_exists('id', $arguments)) {
                $arguments['task_id'] = $arguments['id'];
            }

            // Backward compatible: allow "storyPoints" as alias for "story_points"
            if (!array_key_exists('story_points', $arguments) && array_key_exists('storyPoints', $arguments)) {
                $arguments['story_points'] = $arguments['storyPoints'];
            }

            // Nutze standardisierte ID-Validierung (loose coupled - optional)
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'task_id',
                PlannerTask::class,
                'TASK_NOT_FOUND',
                'Die angegebene Aufgabe wurde nicht gefunden.'
            );
            
            if ($validation['error']) {
                return $validation['error'];
            }
            
            $task = $validation['model'];
            
            // Policy wie UI (Task::mount + Editing Aktionen nutzen authorize('update', $task))
            try {
                Gate::forUser($context->user)->authorize('update', $task);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, diese Aufgabe zu bearbeiten (Policy).');
            }

            // Update-Daten sammeln
            $updateData = [];

            // WICHTIG: user_id ist read-only (der ursprüngliche Ersteller) und kann nicht geändert werden
            if (array_key_exists('user_id', $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', 'user_id kann nicht geändert werden. Dieses Feld speichert den ursprünglichen Ersteller der Aufgabe und ist read-only.');
            }

            // PATCH-like Semantik: leere Strings NICHT schreiben (verhindert Bulk-Overwrites).
            // Explizites null/"null" löscht nur Felder, die nullable sind.
            if (array_key_exists('title', $arguments)) {
                $val = $arguments['title'];
                if ($val === null || $val === 'null') {
                    return ToolResult::error('VALIDATION_ERROR', 'title darf nicht null sein.');
                }
                $valStr = is_string($val) ? trim($val) : (string)$val;
                if ($valStr !== '') {
                    $updateData['title'] = $valStr;
                }
            }

            if (array_key_exists('description', $arguments)) {
                $val = $arguments['description'];
                if ($val === null || $val === 'null') {
                    $updateData['description'] = null;
                } else {
                    $valStr = is_string($val) ? trim($val) : (string)$val;
                    if ($valStr !== '') {
                        $updateData['description'] = $valStr;
                    }
                }
            }

            // DoD verarbeiten - Priorität: dod_items_update > dod_items > dod (String)
            $dodProcessed = false;

            // 1. Granulare DoD-Updates (dod_items_update)
            if (isset($arguments['dod_items_update']) && is_array($arguments['dod_items_update'])) {
                $dodUpdate = $arguments['dod_items_update'];
                $currentItems = $task->dod_items; // Geparste Items aus dem Model

                // Toggle-Operationen
                if (isset($dodUpdate['toggle']) && is_array($dodUpdate['toggle'])) {
                    foreach ($dodUpdate['toggle'] as $idx) {
                        if (isset($currentItems[$idx])) {
                            $currentItems[$idx]['checked'] = !$currentItems[$idx]['checked'];
                        }
                    }
                }

                // Explizites Setzen des checked-Status
                if (isset($dodUpdate['set_checked']) && is_array($dodUpdate['set_checked'])) {
                    foreach ($dodUpdate['set_checked'] as $op) {
                        $idx = $op['index'] ?? null;
                        if ($idx !== null && isset($currentItems[$idx])) {
                            $currentItems[$idx]['checked'] = (bool)($op['checked'] ?? false);
                        }
                    }
                }

                // Text-Updates
                if (isset($dodUpdate['update_text']) && is_array($dodUpdate['update_text'])) {
                    foreach ($dodUpdate['update_text'] as $op) {
                        $idx = $op['index'] ?? null;
                        $text = trim($op['text'] ?? '');
                        if ($idx !== null && isset($currentItems[$idx]) && $text !== '') {
                            $currentItems[$idx]['text'] = $text;
                        }
                    }
                }

                // Entfernen (in absteigender Reihenfolge, damit Indizes stimmen)
                if (isset($dodUpdate['remove']) && is_array($dodUpdate['remove'])) {
                    $removeIndices = array_unique(array_map('intval', $dodUpdate['remove']));
                    rsort($removeIndices); // Absteigend sortieren
                    foreach ($removeIndices as $idx) {
                        if (isset($currentItems[$idx])) {
                            array_splice($currentItems, $idx, 1);
                        }
                    }
                }

                // Hinzufügen (am Ende)
                if (isset($dodUpdate['add']) && is_array($dodUpdate['add'])) {
                    foreach ($dodUpdate['add'] as $item) {
                        $text = trim($item['text'] ?? '');
                        if ($text !== '') {
                            $currentItems[] = [
                                'text' => $text,
                                'checked' => (bool)($item['checked'] ?? false),
                            ];
                        }
                    }
                }

                // Leere Items entfernen und re-indexieren
                $currentItems = array_values(array_filter($currentItems, fn($item) => !empty(trim($item['text'] ?? ''))));

                // Als JSON speichern
                if (empty($currentItems)) {
                    $updateData['dod'] = null;
                } else {
                    $updateData['dod'] = json_encode($currentItems, JSON_UNESCAPED_UNICODE);
                }
                $dodProcessed = true;
            }

            // 2. Komplette DoD-Items (dod_items Array)
            if (!$dodProcessed && isset($arguments['dod_items']) && is_array($arguments['dod_items'])) {
                $dodItems = array_values(array_filter(array_map(function ($item) {
                    if (!is_array($item) || empty(trim($item['text'] ?? ''))) {
                        return null;
                    }
                    return [
                        'text' => trim($item['text']),
                        'checked' => (bool)($item['checked'] ?? false),
                    ];
                }, $arguments['dod_items'])));

                if (empty($dodItems)) {
                    $updateData['dod'] = null;
                } else {
                    $updateData['dod'] = json_encode($dodItems, JSON_UNESCAPED_UNICODE);
                }
                $dodProcessed = true;
            }

            // 3. DoD als String (Legacy-Kompatibilität)
            if (!$dodProcessed && array_key_exists('dod', $arguments)) {
                $val = $arguments['dod'];
                if ($val === null || $val === 'null') {
                    $updateData['dod'] = null;
                } else {
                    $valStr = is_string($val) ? trim($val) : (string)$val;
                    if ($valStr !== '') {
                        $updateData['dod'] = $valStr;
                    }
                }
            }

            // Fälligkeitsdatum
            if (isset($arguments['due_date'])) {
                $rawDueDate = $arguments['due_date'];
                if (is_string($rawDueDate)) {
                    $rawDueDate = trim($rawDueDate);
                }
                if ($rawDueDate === null || $rawDueDate === '' || $rawDueDate === 'null') {
                    $updateData['due_date'] = null;
                } else {
                    try {
                        $updateData['due_date'] = Carbon::parse($rawDueDate);
                    } catch (\Exception $e) {
                        return ToolResult::error('INVALID_DATE', 'Ungültiges Datumsformat. Verwende YYYY-MM-DD oder ISO 8601 (z.B. "2025-01-20").');
                    }
                }
            }

            // Projekt ändern
            $projectChanged = false;
            if (array_key_exists('project_id', $arguments)) {
                if ($arguments['project_id'] === '') {
                    // leere Strings ignorieren (Bulk-Overwrites vermeiden)
                    $arguments['project_id'] = null;
                    // aber NICHT als "clear" behandeln: wir setzen keinen updateData key
                }
            }
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

                    // Policy: für Verschieben in anderes Projekt braucht man Update-Recht auf dem Zielprojekt
                    try {
                        Gate::forUser($context->user)->authorize('update', $newProject);
                    } catch (AuthorizationException $e) {
                        return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf das angegebene Projekt (Policy).');
                    }

                    $updateData['project_id'] = $newProject->id;
                    $updateData['team_id'] = $newProject->team_id; // Team vom Projekt übernehmen
                    $projectChanged = true;
                }
            }

            // Slot ändern (Verschieben zwischen Slots)
            $slotChanged = false;
            if (array_key_exists('project_slot_id', $arguments)) {
                if ($arguments['project_slot_id'] === '') {
                    // leere Strings ignorieren (Bulk-Overwrites vermeiden)
                    $arguments['project_slot_id'] = null;
                    // aber NICHT als "clear" behandeln: wir setzen keinen updateData key
                }
            }
            if (isset($arguments['project_slot_id'])) {
                if (empty($arguments['project_slot_id']) || $arguments['project_slot_id'] === 0) {
                    // Aus Slot ins Backlog verschieben
                    $updateData['project_slot_id'] = null;
                    $updateData['project_slot_order'] = 0; // Backlog-Aufgaben haben project_slot_order = 0 (nicht null!)
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
                    
                    // Order im neuen Slot berechnen (neue Aufgabe kommt an den Anfang: min - 1)
                    $minOrder = PlannerTask::where('project_slot_id', $newSlot->id)
                        ->min('project_slot_order');
                    $updateData['project_slot_order'] = ($minOrder === null) ? 0 : $minOrder - 1;
                    $slotChanged = true;
                }
            }

            // Wenn Projekt geändert wurde, aber Slot nicht explizit gesetzt, Slot entfernen
            if ($projectChanged && !isset($arguments['project_slot_id'])) {
                $updateData['project_slot_id'] = null;
                $updateData['project_slot_order'] = 0; // Backlog-Aufgaben haben project_slot_order = 0 (nicht null!)
            }
            
            // WICHTIG: Wenn nur andere Felder geändert werden (z.B. description, dod), 
            // NICHT project_slot_order setzen - es behält seinen aktuellen Wert
            // Nur wenn project_slot_id geändert wird, wird project_slot_order auch aktualisiert

            if (array_key_exists('user_in_charge_id', $arguments)) {
                $val = $arguments['user_in_charge_id'];
                if ($val === '' || $val === null || $val === 'null') {
                    // ignore empty string; allow explicit null to clear
                    if ($val === null || $val === 'null') {
                        $updateData['user_in_charge_id'] = null;
                    }
                } elseif (is_numeric($val)) {
                    $updateData['user_in_charge_id'] = (int)$val;
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'user_in_charge_id muss eine Zahl sein (oder null zum Entfernen).');
                }
            }

            if (array_key_exists('planned_minutes', $arguments)) {
                $val = $arguments['planned_minutes'];
                if ($val === '' ) {
                    // ignore (Bulk-Overwrites vermeiden)
                } elseif ($val === null || $val === 'null') {
                    $updateData['planned_minutes'] = null;
                } elseif (is_numeric($val)) {
                    $updateData['planned_minutes'] = (int)$val;
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'planned_minutes muss eine Zahl sein (oder null zum Entfernen).');
                }
            }

            if (array_key_exists('story_points', $arguments)) {
                $sp = $arguments['story_points'];
                if (is_string($sp)) {
                    $sp = trim($sp);
                }

                // Robust: 0/"0" wird häufig als "keine Story Points" gesendet → entfernen
                if ($sp === null || $sp === '' || $sp === 'null' || $sp === 0 || $sp === '0') {
                    $updateData['story_points'] = null;
                } else {
                    $normalized = strtolower((string)$sp);
                    $enum = TaskStoryPoints::tryFrom($normalized);
                    if (!$enum) {
                        return ToolResult::error(
                            'VALIDATION_ERROR',
                            'Ungültige story_points. Erlaubt: xs|s|m|l|xl|xxl (oder null/""/0 zum Entfernen).'
                        );
                    }
                    $updateData['story_points'] = $enum->value;
                }
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
            $task->load(['project', 'projectSlot', 'user', 'userInCharge']);

            // Response zusammenstellen
            $response = [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,
                'dod' => $task->dod,
                'dod_items' => $task->dod_items, // Geparste DoD-Items als Array
                'dod_progress' => $task->dod_progress, // {total, checked, percentage, isComplete}
                'due_date' => $task->due_date?->toIso8601String(),
                'project_id' => $task->project_id,
                'project_name' => $task->project?->name,
                'project_slot_id' => $task->project_slot_id,
                'project_slot_name' => $task->projectSlot?->name,
                'user_id' => $task->user_id, // Der User, der die Aufgabe ursprünglich erstellt hat (read-only)
                'user_name' => $task->user?->name ?? 'Unbekannt',
                'user_in_charge_id' => $task->user_in_charge_id,
                'user_in_charge_name' => $task->userInCharge?->name ?? 'Unbekannt',
                'planned_minutes' => $task->planned_minutes,
                'story_points' => $task->story_points?->value,
                'story_points_label' => $task->story_points?->label(),
                'story_points_points' => $task->story_points?->points(),
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

