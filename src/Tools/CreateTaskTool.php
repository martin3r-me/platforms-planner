<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolDependencyContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

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
        return 'POST /tasks - Erstellt eine neue Aufgabe. REST-Parameter: title (required, string) - Titel der Aufgabe. project_id (optional, integer) - Projekt-ID. Wenn angegeben, wird Aufgabe dem Projekt zugeordnet. project_slot_id (optional, integer) - Slot-ID. Wenn angegeben, wird Aufgabe dem Slot zugeordnet. description (optional, string) - Beschreibung. definition_of_done (optional, string) - Definition of Done. due_date (optional, date) - Fälligkeitsdatum. user_in_charge_id (optional, integer) - verantwortlicher User.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel der Aufgabe (ERFORDERLICH).'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung der Aufgabe.'
                ],
                'dod' => [
                    'type' => 'string',
                    'description' => 'Optional: Definition of Done (DoD) - Kriterien, wann die Aufgabe als erledigt gilt.'
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Fälligkeitsdatum im Format YYYY-MM-DD oder ISO 8601 (z.B. "2025-01-20" oder "2025-01-20T10:00:00Z").'
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
                    'description' => 'Optional: Geplante Minuten für die Aufgabe.'
                ],
                'story_points' => [
                    'type' => 'string',
                    'description' => 'Optional: Story Points (xs|s|m|l|xl|xxl). Setze auf null/""/0 um zu entfernen.',
                    'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
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

                // Policy: Task erstellen mit Projekt
                try {
                    Gate::forUser($context->user)->authorize('create', [PlannerTask::class, $project]);
                } catch (AuthorizationException $e) {
                    return ToolResult::error('ACCESS_DENIED', 'Du darfst in diesem Projekt keine Aufgaben erstellen (Policy).');
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

            // Policy: persönliche Task erstellen (ohne Projekt) oder erneut absichern nach Slot->Project Resolving
            try {
                Gate::forUser($context->user)->authorize('create', [PlannerTask::class, $project]);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', $project
                    ? 'Du darfst in diesem Projekt keine Aufgaben erstellen (Policy).'
                    : 'Du darfst keine Aufgaben erstellen (Policy).'
                );
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

            // Story points normalisieren/validieren (damit der Enum-Cast nie knallt)
            $storyPointsValue = null;
            if (array_key_exists('story_points', $arguments)) {
                $sp = $arguments['story_points'];
                if (is_string($sp)) {
                    $sp = trim($sp);
                }
                if ($sp === null || $sp === '' || $sp === 'null' || $sp === 0 || $sp === '0') {
                    $storyPointsValue = null;
                } else {
                    $normalized = strtolower((string)$sp);
                    $enum = TaskStoryPoints::tryFrom($normalized);
                    if (!$enum) {
                        return ToolResult::error(
                            'VALIDATION_ERROR',
                            'Ungültige story_points. Erlaubt: xs|s|m|l|xl|xxl (oder null/""/0 zum Entfernen).'
                        );
                    }
                    $storyPointsValue = $enum->value;
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
            // WICHTIG: description und dod werden automatisch durch EncryptedString Cast verschlüsselt
            // Beim create() werden die Casts angewendet, aber wir setzen die verschlüsselten Felder
            // explizit danach, um sicherzustellen, dass die Verschlüsselung funktioniert
            $task = PlannerTask::create([
                'title' => $arguments['title'],
                'due_date' => $dueDate,
                'project_id' => $project?->id,
                'project_slot_id' => $projectSlotId, // null für Backlog, Slot-ID für Slot-Aufgaben
                'user_id' => $context->user->id,
                'user_in_charge_id' => $userInChargeId,
                'team_id' => $teamId,
                'planned_minutes' => $arguments['planned_minutes'] ?? null,
                'story_points' => $storyPointsValue,
                'order' => $order,
                'project_slot_order' => $projectSlotOrder, // 0 für Backlog, >0 für Slot-Aufgaben
            ]);
            
            // Verschlüsselte Felder explizit setzen (Cast verschlüsselt automatisch beim setAttribute)
            // Dies stellt sicher, dass die Verschlüsselung auch beim create() funktioniert
            $needsUpdate = false;
            if (isset($arguments['description'])) {
                $task->description = $arguments['description'];
                $needsUpdate = true;
            }
            if (isset($arguments['dod'])) {
                $task->dod = $arguments['dod'];
                $needsUpdate = true;
            }
            
            // Nur speichern, wenn verschlüsselte Felder gesetzt wurden
            if ($needsUpdate) {
                $task->save();
            }

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

