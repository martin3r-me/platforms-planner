<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Enums\ProjectType;

/**
 * Tool zum Bearbeiten von Projekten im Planner-Modul
 */
class UpdateProjectTool implements ToolContract
{
    use HasStandardizedWriteOperations;
    public function getName(): string
    {
        return 'planner.projects.PUT';
    }

    public function getDescription(): string
    {
        return 'Bearbeitet ein bestehendes Projekt. RUF DIESES TOOL AUF, wenn der Nutzer ein Projekt ändern möchte (Name, Beschreibung, Typ, etc.). Die Projekt-ID ist erforderlich. Nutze "planner.projects.GET" um Projekte zu finden, wenn der Nutzer nur den Namen angibt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu bearbeitenden Projekts (ERFORDERLICH). Nutze "planner.projects.GET" um Projekte zu finden.'
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name des Projekts. Frage nach, wenn der Nutzer den Namen ändern möchte.'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung des Projekts. Frage nach, wenn der Nutzer die Beschreibung ändern möchte.'
                ],
                'project_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Projekttyp. Mögliche Werte: "internal", "customer", "event", "cooking". Frage nach, wenn der Nutzer den Typ ändern möchte.',
                    'enum' => ['internal', 'customer', 'event', 'cooking']
                ],
                'owner_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Owner-User-ID. Frage nach, wenn der Nutzer den Owner ändern möchte.'
                ],
                'planned_minutes' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue geplante Minuten. Frage nach, wenn der Nutzer das Zeitbudget ändern möchte.'
                ],
                'customer_cost_center' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Kostenstelle für Kundenprojekte. Frage nach, wenn der Nutzer die Kostenstelle ändern möchte.'
                ],
                'done' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Projekt als erledigt markieren. Frage nach, wenn der Nutzer das Projekt abschließen möchte.'
                ]
            ],
            'required' => ['project_id']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Nutze standardisierte ID-Validierung (loose coupled - optional)
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'project_id',
                PlannerProject::class,
                'PROJECT_NOT_FOUND',
                'Das angegebene Projekt wurde nicht gefunden.'
            );
            
            if ($validation['error']) {
                return $validation['error'];
            }
            
            $project = $validation['model'];
            
            // Prüfe Zugriff (optional - kann überschrieben werden)
            $accessCheck = $this->checkAccess($project, $context, function($model, $ctx) {
                // Custom Access-Check: Owner oder Admin
                $hasAccess = $model->projectUsers()
                    ->where('user_id', $ctx->user->id)
                    ->whereIn('role', ['owner', 'admin'])
                    ->exists();
                
                return $hasAccess || $model->user_id === $ctx->user->id;
            });
            
            if ($accessCheck) {
                return $accessCheck;
            }

            // Update-Daten sammeln
            $updateData = [];

            if (isset($arguments['name'])) {
                $updateData['name'] = $arguments['name'];
            }

            if (isset($arguments['description'])) {
                $updateData['description'] = $arguments['description'];
            }

            if (isset($arguments['project_type'])) {
                $projectType = ProjectType::tryFrom($arguments['project_type']);
                if ($projectType) {
                    $updateData['project_type'] = $projectType;
                }
            }

            if (isset($arguments['planned_minutes'])) {
                $updateData['planned_minutes'] = $arguments['planned_minutes'];
            }

            if (isset($arguments['customer_cost_center'])) {
                $updateData['customer_cost_center'] = $arguments['customer_cost_center'];
            }

            if (isset($arguments['done'])) {
                $updateData['done'] = $arguments['done'];
                if ($arguments['done']) {
                    $updateData['done_at'] = now();
                } else {
                    $updateData['done_at'] = null;
                }
            }

            if (isset($arguments['owner_user_id'])) {
                // Owner ändern: Projekt-User aktualisieren
                $project->user_id = $arguments['owner_user_id'];
                $updateData['user_id'] = $arguments['owner_user_id'];
                
                // Rolle des neuen Owners auf 'owner' setzen
                $projectUser = $project->projectUsers()
                    ->where('user_id', $arguments['owner_user_id'])
                    ->first();
                
                if ($projectUser) {
                    $projectUser->update(['role' => 'owner']);
                } else {
                    $project->projectUsers()->create([
                        'user_id' => $arguments['owner_user_id'],
                        'role' => 'owner',
                    ]);
                }
            }

            // Projekt aktualisieren
            if (!empty($updateData)) {
                $project->update($updateData);
            }

            // Aktualisiertes Projekt laden
            $project->refresh();
            $project->load(['user', 'projectUsers.user']);

            $projectUsers = $project->projectUsers->map(function($pu) {
                return [
                    'user_id' => $pu->user_id,
                    'user_name' => $pu->user->name ?? 'Unbekannt',
                    'role' => $pu->role,
                ];
            })->toArray();

            return ToolResult::success([
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description,
                'project_type' => $project->project_type?->value,
                'team_id' => $project->team_id,
                'owner_user_id' => $project->user_id,
                'owner_name' => $project->user->name ?? 'Unbekannt',
                'members' => $projectUsers,
                'done' => $project->done,
                'done_at' => $project->done_at?->toIso8601String(),
                'updated_at' => $project->updated_at->toIso8601String(),
                'message' => "Projekt '{$project->name}' erfolgreich aktualisiert."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Projekts: ' . $e->getMessage());
        }
    }
}

