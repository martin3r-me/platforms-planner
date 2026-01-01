<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolDependencyContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Carbon\Carbon;

/**
 * Tool zum Erstellen von Aufgaben im Planner-Modul
 * 
 * Aufgaben können auf drei Arten erstellt werden:
 * 1. In einem Projekt-Slot (project_slot_id gesetzt) - strukturierte Aufgaben
 * 2. Im Projekt-Backlog (project_id gesetzt, project_slot_id null) - Backlog-Aufgaben
 * 3. Persönliche Aufgaben (project_id null, project_slot_id null) - persönliche Aufgaben des Users
 * 
 * LOOSE COUPLED: Definiert seine Dependencies selbst via ToolDependencyContract.
 */
class CreateTaskTool implements ToolContract, ToolDependencyContract
{
    public function getName(): string
    {
        return 'planner.tasks.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt eine neue Aufgabe. Aufgaben können in einem Projekt-Slot (strukturiert), im Projekt-Backlog (ohne Slot) oder als persönliche Aufgabe (ohne Projekt) erstellt werden. RUF DIESES TOOL AUF, wenn der Nutzer eine Aufgabe erstellen möchte. Der Titel ist erforderlich. Wenn der Nutzer ein Projekt oder einen Slot erwähnt, nutze "planner.projects.GET" und "planner.project_slots.GET" um die IDs zu finden. Wenn nichts angegeben ist, erstelle eine persönliche Aufgabe.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel der Aufgabe (ERFORDERLICH). Frage den Nutzer explizit nach dem Titel, wenn er nicht angegeben wurde.'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung der Aufgabe. Frage nach, wenn der Nutzer Details erwähnt, aber keine Beschreibung angibt.'
                ],
                'dod' => [
                    'type' => 'string',
                    'description' => 'Optional: Definition of Done (DoD) - Kriterien, wann die Aufgabe als erledigt gilt. Frage nach, wenn der Nutzer DoD-Kriterien erwähnt.'
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Fälligkeitsdatum im Format YYYY-MM-DD oder ISO 8601 (z.B. "2025-01-20" oder "2025-01-20T10:00:00Z"). Frage nach, wenn der Nutzer ein Datum erwähnt (z.B. "bis nächste Woche", "bis Freitag").'
                ],
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Projekts. Wenn angegeben, wird die Aufgabe dem Projekt zugeordnet (Backlog, wenn project_slot_id fehlt). Wenn nicht angegeben, wird eine persönliche Aufgabe erstellt. Nutze "planner.projects.GET" um Projekte zu finden.'
                ],
                'project_slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Projekt-Slots. Wenn angegeben, wird die Aufgabe dem Slot zugeordnet. project_id muss dann auch gesetzt sein. Nutze "planner.project_slots.GET" um Slots zu finden.'
                ],
                'user_in_charge_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Users, der für die Aufgabe zuständig ist. Wenn nicht angegeben, wird der aktuelle User verwendet. Nutze "core.users.list" um Users zu finden.'
                ],
                'planned_minutes' => [
                    'type' => 'integer',
                    'description' => 'Optional: Geplante Minuten für die Aufgabe. Frage nach, wenn der Nutzer eine Zeitangabe macht.'
                ]
            ],
            'required' => ['title']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Validierung
            if (empty($arguments['title'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Titel ist erforderlich');
            }

            // Projekt prüfen (wenn angegeben)
            $project = null;
            if (!empty($arguments['project_id'])) {
                $project = PlannerProject::find($arguments['project_id']);
                if (!$project) {
                    return ToolResult::error('PROJECT_NOT_FOUND', 'Das angegebene Projekt wurde nicht gefunden. Nutze "planner.projects.GET" um alle verfügbaren Projekte zu sehen.');
                }

                // Prüfe, ob User Zugriff auf Projekt hat
                $hasAccess = $project->projectUsers()
                    ->where('user_id', $context->user->id)
                    ->exists();
                
                if (!$hasAccess && $project->user_id !== $context->user->id) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Projekt. Nutze "planner.projects.GET" um alle verfügbaren Projekte zu sehen.');
                }
            }

            // Slot prüfen (wenn angegeben)
            $slot = null;
            if (!empty($arguments['project_slot_id'])) {
                $slot = PlannerProjectSlot::find($arguments['project_slot_id']);
                if (!$slot) {
                    return ToolResult::error('SLOT_NOT_FOUND', 'Der angegebene Slot wurde nicht gefunden. Nutze "planner.project_slots.GET" um alle verfügbaren Slots zu sehen.');
                }

                // Prüfe, ob Slot zum Projekt gehört (wenn project_id auch gesetzt ist)
                if ($project && $slot->project_id !== $project->id) {
                    return ToolResult::error('SLOT_MISMATCH', 'Der angegebene Slot gehört nicht zum angegebenen Projekt.');
                }

                // Wenn project_slot_id gesetzt ist, aber project_id fehlt, hole es vom Slot
                if (!$project) {
                    $project = $slot->project;
                }
            }

            // Fälligkeitsdatum parsen
            $dueDate = null;
            if (!empty($arguments['due_date'])) {
                try {
                    $dueDate = Carbon::parse($arguments['due_date']);
                } catch (\Exception $e) {
                    return ToolResult::error('INVALID_DATE', 'Ungültiges Datumsformat. Verwende YYYY-MM-DD oder ISO 8601 (z.B. "2025-01-20").');
                }
            }

            // Team bestimmen: aus Projekt oder Context
            $teamId = $project?->team_id ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team gefunden. Persönliche Aufgaben benötigen ein Team im Kontext.');
            }

            // User in Charge bestimmen
            $userInChargeId = $arguments['user_in_charge_id'] ?? $context->user->id;

            // Order berechnen (neue Aufgabe kommt an den Anfang)
            $order = null;
            $projectSlotOrder = 0; // Default für Backlog-Aufgaben (ohne Slot)
            
            if ($slot) {
                // Order innerhalb des Slots - neue Aufgabe kommt an den Anfang
                $minOrder = PlannerTask::where('project_slot_id', $slot->id)
                    ->min('project_slot_order') ?? 0;
                $projectSlotOrder = $minOrder - 1; // Kann auch negativ sein
                $order = $projectSlotOrder;
            } else {
                // Order für Backlog oder persönliche Aufgaben - neue Aufgabe kommt an den Anfang
                $minOrder = PlannerTask::where('project_id', $project?->id)
                    ->whereNull('project_slot_id')
                    ->min('order') ?? 0;
                $order = $minOrder - 1; // Kann auch negativ sein
                // project_slot_order bleibt 0 für Backlog-Aufgaben (ohne Slot)
            }

            // Handle project_slot_id: Wenn 0 oder leer, setze auf null (Backlog)
            $projectSlotId = null;
            if (!empty($arguments['project_slot_id']) && $arguments['project_slot_id'] !== 0) {
                $projectSlotId = $arguments['project_slot_id'];
            } elseif ($slot) {
                $projectSlotId = $slot->id;
            }

            // Aufgabe erstellen
            $task = PlannerTask::create([
                'title' => $arguments['title'],
                'description' => $arguments['description'] ?? null,
                'dod' => $arguments['dod'] ?? null,
                'due_date' => $dueDate,
                'project_id' => $project?->id,
                'project_slot_id' => $projectSlotId, // null für Backlog, Slot-ID für Slot-Aufgaben
                'user_id' => $context->user->id,
                'user_in_charge_id' => $userInChargeId,
                'team_id' => $teamId,
                'planned_minutes' => $arguments['planned_minutes'] ?? null,
                'order' => $order,
                'project_slot_order' => $projectSlotOrder, // 0 für Backlog, >0 für Slot-Aufgaben
            ]);

            // Response zusammenstellen
            $response = [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,
                'dod' => $task->dod,
                'due_date' => $task->due_date?->toIso8601String(),
                'project_id' => $task->project_id,
                'project_name' => $project?->name,
                'project_slot_id' => $task->project_slot_id,
                'project_slot_name' => $slot?->name,
                'user_in_charge_id' => $task->user_in_charge_id,
                'is_personal' => $task->project_id === null,
                'created_at' => $task->created_at->toIso8601String(),
            ];

            // Message basierend auf Typ
            if ($slot) {
                $message = "Aufgabe '{$task->title}' erfolgreich im Slot '{$slot->name}' des Projekts '{$project->name}' erstellt.";
            } elseif ($project) {
                $message = "Aufgabe '{$task->title}' erfolgreich im Backlog des Projekts '{$project->name}' erstellt.";
            } else {
                $message = "Persönliche Aufgabe '{$task->title}' erfolgreich erstellt.";
            }

            $response['message'] = $message;

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Aufgabe: ' . $e->getMessage());
        }
    }

    /**
     * Definiert die Dependencies dieses Tools (loose coupled)
     */
    public function getDependencies(): array
    {
        return [
            'required_fields' => [],
            'dependencies' => [
                [
                    'tool_name' => 'planner.projects.GET',
                    'condition' => function(array $arguments, ToolContext $context): bool {
                        // Wenn project_id erwähnt wird, aber nicht gesetzt ist, hole Projekte
                        // (Wir können nicht direkt prüfen, ob der User ein Projekt erwähnt hat,
                        // daher lassen wir die LLM entscheiden, wann sie das Tool aufruft)
                        return false; // Keine automatische Dependency - LLM entscheidet
                    },
                    'args' => function(array $arguments, ToolContext $context): array {
                        return [];
                    },
                    'merge_result' => function(string $mainToolName, ToolResult $depResult, array $arguments): ?array {
                        return $arguments;
                    }
                ],
                [
                    'tool_name' => 'planner.project_slots.GET',
                    'condition' => function(array $arguments, ToolContext $context): bool {
                        // Wenn project_slot_id erwähnt wird, aber nicht gesetzt ist
                        return false; // Keine automatische Dependency - LLM entscheidet
                    },
                    'args' => function(array $arguments, ToolContext $context): array {
                        return ['project_id' => $arguments['project_id'] ?? null];
                    },
                    'merge_result' => function(string $mainToolName, ToolResult $depResult, array $arguments): ?array {
                        return $arguments;
                    }
                ]
            ]
        ];
    }
}

