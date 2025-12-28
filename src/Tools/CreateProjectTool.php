<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
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
 */
class CreateProjectTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.projects.create';
    }

    public function getDescription(): string
    {
        return 'Erstellt ein neues Projekt im Planner-Modul. WICHTIG: Frage den Nutzer nach allen wichtigen Informationen (Name, Team, Beschreibung, Typ, Owner, Mitglieder), bevor du das Tool aufrufst. Wenn Informationen fehlen, frage explizit nach. Der Projektname ist erforderlich. Wenn kein Team angegeben ist, nutze das Tool "core.teams.list" um alle verfügbaren Teams anzuzeigen und frage dann nach dem gewünschten Team. Alle anderen Felder sind optional.';
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
                    'description' => 'Optional: ID des Projekt-Owners. Wenn nicht angegeben, wird der aktuelle Nutzer als Owner gesetzt. Frage nach, wenn der Nutzer einen anderen Owner wünscht.'
                ],
                'members' => [
                    'type' => 'array',
                    'description' => 'Optional: Array von Projektmitgliedern. Jedes Mitglied ist ein Objekt mit "user_id" (integer, erforderlich) und "role" (string: "owner", "admin", "member", "viewer", Standard: "member"). Frage nach, wenn der Nutzer weitere Mitglieder hinzufügen möchte.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'user_id' => [
                                'type' => 'integer',
                                'description' => 'ID des Benutzers'
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
            $team = null;
            if (!empty($arguments['team_id'])) {
                // Prüfe, ob User Zugriff auf dieses Team hat
                $team = $context->user->teams()->find($arguments['team_id']);
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
            $ownerUserId = $arguments['owner_user_id'] ?? $context->user->id;

            // Projekttyp bestimmen
            $projectType = null;
            if (!empty($arguments['project_type'])) {
                $projectType = ProjectType::tryFrom($arguments['project_type']);
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
}

