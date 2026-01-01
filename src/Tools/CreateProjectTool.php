<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolDependencyContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Enums\ProjectRole;
use Platform\Planner\Enums\ProjectType;

/**
 * Tool zum Erstellen von Projekten im Planner-Modul
 * 
 * Ermöglicht es der AI, neue Projekte per Chat zu erstellen.
 * WICHTIG: Wenn der Nutzer nicht alle Informationen angibt, frage nach:
 * - Projektname (erforderlich)
 * - Beschreibung (optional, aber empfohlen)
 * - Projekttyp (internal, customer, event, cooking)
 * - Projekt-Owner (falls nicht der aktuelle Nutzer)
 * - Weitere Projektmitglieder mit Rollen (optional)
 * 
 * LOOSE COUPLED: Definiert seine Dependencies selbst via ToolDependencyContract.
 */
class CreateProjectTool implements ToolContract, ToolDependencyContract
{
    public function getName(): string
    {
        return 'planner.projects.create';
    }

    public function getDescription(): string
    {
        return 'Erstellt ein neues Projekt im Planner-Modul. RUF DIESES TOOL AUF, wenn der Nutzer ein Projekt erstellen möchte. Der Projektname ist erforderlich. Wenn der Nutzer nur den Namen angibt, rufe zuerst "core.teams.list" auf, um die verfügbaren Teams zu sehen. Wenn es mehrere Teams gibt, frage dialog-mäßig nach dem gewünschten Team (z.B. "Soll ich das aktuelle Team verwenden?"). Wenn nur ein Team verfügbar ist, verwende es automatisch. Alle anderen Felder (Beschreibung, Typ, Owner, Mitglieder) sind optional - frage nur nach, wenn der Nutzer sie erwähnt oder wenn sie für den Kontext wichtig sind.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Projekts (ERFORDERLICH). Frage den Nutzer explizit nach dem Namen, wenn er nicht angegeben wurde.'
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Teams, in dem das Projekt erstellt werden soll. Wenn nicht angegeben, wird das aktuelle Team aus dem Kontext verwendet. Wenn der Nutzer ein anderes Team wünscht, nutze das Tool "core.teams.list" um alle verfügbaren Teams anzuzeigen und frage dann nach der Team-ID.'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Beschreibung des Projekts. Frage nach, wenn der Nutzer ein Projekt erstellt, aber keine Beschreibung angegeben hat.'
                ],
                'project_type' => [
                    'type' => 'string',
                    'description' => 'Typ des Projekts. Mögliche Werte: "internal" (internes Projekt), "customer" (Kundenprojekt), "event" (Event-Projekt), "cooking" (Kochprojekt). Standard: "internal". Frage nach, wenn unklar ist.'
                ],
                'owner_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Projekt-Owners. WICHTIG: Wenn der Nutzer sagt "nimm mich selbst" oder "nimm nur mich", LASS DIESEN PARAMETER WEG oder setze ihn auf null. Das Tool verwendet dann automatisch die User-ID des aktuellen Nutzers aus dem Kontext. Verwende NIEMALS hardcoded IDs wie 1 oder 0. Wenn nicht angegeben, wird automatisch der aktuelle Nutzer als Owner gesetzt. Frage nur nach, wenn der Nutzer explizit einen anderen Owner wünscht.'
                ],
                'members' => [
                    'type' => 'array',
                    'description' => 'Optional: Array von Projektmitgliedern. WICHTIG: Wenn der Nutzer sagt "nimm nur mich" oder "nimm mich selbst", LASS DIESEN PARAMETER WEG oder setze ihn auf null. Das Tool fügt automatisch den aktuellen Nutzer als Owner hinzu. Jedes Mitglied ist ein Objekt mit "user_id" (integer, erforderlich) und "role" (string: "owner", "admin", "member", "viewer", Standard: "member"). Verwende NIEMALS hardcoded User-IDs wie 1 oder 0. Frage nur nach, wenn der Nutzer explizit weitere Mitglieder hinzufügen möchte.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'user_id' => [
                                'type' => 'integer',
                                'description' => 'ID des Benutzers. WICHTIG: Verwende NIEMALS hardcoded IDs wie 1 oder 0. Nutze "core.teams.users.list" um User-IDs zu finden.'
                            ],
                            'role' => [
                                'type' => 'string',
                                'description' => 'Rolle des Benutzers im Projekt: "owner", "admin", "member", "viewer"',
                                'enum' => ['owner', 'admin', 'member', 'viewer']
                            ]
                        ],
                        'required' => ['user_id']
                    ]
                ],
                'planned_minutes' => [
                    'type' => 'integer',
                    'description' => 'Optional: Geplante Minuten für das Projekt. Frage nach, wenn der Nutzer ein Zeitbudget angibt.'
                ],
                'customer_cost_center' => [
                    'type' => 'string',
                    'description' => 'Optional: Kostenstelle für Kundenprojekte. Frage nach, wenn es ein Kundenprojekt ist und eine Kostenstelle angegeben werden soll.'
                ]
            ],
            'required' => ['name']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Validierung
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Projektname ist erforderlich');
            }

            // Team bestimmen: aus Argumenten oder Context
            // WICHTIG: Prüfe auch auf 0, da OpenAI manchmal 0 statt null sendet
            $teamId = $arguments['team_id'] ?? null;
            if ($teamId === 0 || $teamId === '0') {
                $teamId = null; // Behandle 0 als "nicht gesetzt"
            }
            
            $team = null;
            if (!empty($teamId)) {
                // Prüfe, ob User Zugriff auf dieses Team hat
                $team = $context->user->teams()->find($teamId);
                if (!$team) {
                    return ToolResult::error('TEAM_NOT_FOUND', 'Das angegebene Team wurde nicht gefunden oder du hast keinen Zugriff darauf. Nutze das Tool "core.teams.list" um alle verfügbaren Teams zu sehen.');
                }
            } else {
                // Team aus Context holen
                $team = $context->team;
                if (!$team) {
                    return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Projekte benötigen ein Team. Nutze das Tool "core.teams.list" um alle verfügbaren Teams zu sehen und frage dann nach der Team-ID.');
                }
            }

            // Owner bestimmen (Standard: aktueller User)
            // WICHTIG: Prüfe auch auf 1, da OpenAI manchmal 1 als Default sendet
            $ownerUserId = $arguments['owner_user_id'] ?? null;
            if ($ownerUserId === 0 || $ownerUserId === '0' || $ownerUserId === 1 || $ownerUserId === '1') {
                $ownerUserId = null; // Behandle 0/1 als "nicht gesetzt"
            }
            // Wenn nicht gesetzt, verwende aktuellen User aus Kontext
            if (!$ownerUserId) {
                $ownerUserId = $context->user->id;
            }

            // Projekttyp bestimmen (Standard: internal)
            $projectType = ProjectType::INTERNAL; // Default
            if (!empty($arguments['project_type'])) {
                $projectType = ProjectType::tryFrom($arguments['project_type']) ?? ProjectType::INTERNAL;
            }

            // Order berechnen (neues Projekt kommt ans Ende)
            $maxOrder = PlannerProject::where('team_id', $team->id)->max('order') ?? 0;

            // Projekt erstellen
            $project = PlannerProject::create([
                'name' => $arguments['name'],
                'description' => $arguments['description'] ?? null,
                'user_id' => $ownerUserId, // Projekt-Ersteller
                'team_id' => $team->id,
                'project_type' => $projectType,
                'order' => $maxOrder + 1,
                'planned_minutes' => $arguments['planned_minutes'] ?? null,
                'customer_cost_center' => $arguments['customer_cost_center'] ?? null,
            ]);

            // Owner als Projekt-User hinzufügen
            PlannerProjectUser::create([
                'project_id' => $project->id,
                'user_id' => $ownerUserId,
                'role' => ProjectRole::OWNER->value,
            ]);

            // Weitere Mitglieder hinzufügen (falls angegeben)
            if (!empty($arguments['members']) && is_array($arguments['members'])) {
                foreach ($arguments['members'] as $member) {
                    if (empty($member['user_id'])) {
                        continue; // Überspringe ungültige Einträge
                    }

                    // Prüfe, ob User bereits als Owner existiert
                    $existingUser = PlannerProjectUser::where('project_id', $project->id)
                        ->where('user_id', $member['user_id'])
                        ->first();

                    if ($existingUser) {
                        // Update Rolle, falls nicht Owner
                        if ($existingUser->role !== ProjectRole::OWNER->value) {
                            $existingUser->update([
                                'role' => $member['role'] ?? ProjectRole::MEMBER->value
                            ]);
                        }
                    } else {
                        // Neues Mitglied hinzufügen
                        PlannerProjectUser::create([
                            'project_id' => $project->id,
                            'user_id' => $member['user_id'],
                            'role' => $member['role'] ?? ProjectRole::MEMBER->value,
                        ]);
                    }
                }
            }

            // Projekt-User laden für Response
            $projectUsers = PlannerProjectUser::where('project_id', $project->id)
                ->with('user')
                ->get()
                ->map(function ($pu) {
                    return [
                        'user_id' => $pu->user_id,
                        'user_name' => $pu->user->name ?? 'Unbekannt',
                        'role' => $pu->role,
                    ];
                })
                ->toArray();

            return ToolResult::success([
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description,
                'project_type' => $project->project_type?->value,
                'team_id' => $project->team_id,
                'owner_user_id' => $ownerUserId,
                'members' => $projectUsers,
                'created_at' => $project->created_at->toIso8601String(),
                'message' => "Projekt '{$project->name}' erfolgreich erstellt."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Projekts: ' . $e->getMessage());
        }
    }

    /**
     * Definiert die Dependencies dieses Tools (loose coupled)
     * 
     * Wenn team_id fehlt, wird automatisch core.teams.list aufgerufen.
     */
    public function getDependencies(): array
    {
        return [
            'required_fields' => [], // team_id ist optional im Schema, aber wird benötigt
            'dependencies' => [
                [
                    'tool_name' => 'core.teams.list',
                    'condition' => function(array $arguments, ToolContext $context): bool {
                        // Führe Dependency aus, wenn team_id fehlt oder 0 ist
                        // OpenAI sendet manchmal 0 statt null/leer
                        return empty($arguments['team_id']) || ($arguments['team_id'] ?? null) === 0;
                    },
                    'args' => function(array $arguments, ToolContext $context): array {
                        // Argumente für core.teams.list
                        return ['include_personal' => true];
                    },
                    'merge_result' => function(string $mainToolName, ToolResult $depResult, array $arguments): ?array {
                        // Prüfe auch auf 0, da OpenAI manchmal 0 statt null sendet
                        $teamId = $arguments['team_id'] ?? null;
                        if ($teamId === 0 || $teamId === '0') {
                            $teamId = null;
                        }
                        
                        // Wenn team_id noch fehlt, gib Dependency-Ergebnis zurück (AI soll Teams zeigen)
                        if (empty($teamId) && $depResult->success) {
                            // null = Dependency-Ergebnis direkt zurückgeben (AI zeigt Teams)
                            return null;
                        }
                        
                        // team_id ist vorhanden oder wurde gesetzt, weiter mit Haupt-Tool
                        return $arguments;
                    }
                ]
            ]
        ];
    }
}

