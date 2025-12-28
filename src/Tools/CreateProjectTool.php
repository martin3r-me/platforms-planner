<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;

/**
 * Tool zum Erstellen von Projekten im Planner-Modul
 * 
 * Ermöglicht es der AI, neue Projekte per Chat zu erstellen
 */
class CreateProjectTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.projects.create';
    }

    public function getDescription(): string
    {
        return 'Erstellt ein neues Projekt im Planner-Modul. Nutze dieses Tool, wenn der Nutzer ein neues Projekt erstellen möchte oder über Projekte spricht, die angelegt werden sollen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Projekts (erforderlich)'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Projekts'
                ],
                'project_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Typ des Projekts (z.B. "internal", "customer", "personal")',
                    'enum' => ['internal', 'customer', 'personal']
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

            // Team aus Context holen
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team im Kontext gefunden. Projekte benötigen ein Team.');
            }

            // Projekt erstellen
            $project = PlannerProject::create([
                'name' => $arguments['name'],
                'description' => $arguments['description'] ?? null,
                'user_id' => $context->user->id,
                'team_id' => $team->id,
                'project_type' => $arguments['project_type'] ?? null,
                'order' => 0,
            ]);

            return ToolResult::success([
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description,
                'team_id' => $project->team_id,
                'created_at' => $project->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Projekts: ' . $e->getMessage());
        }
    }
}

