<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;

/**
 * Tool zum Löschen von Projekten im Planner-Modul
 */
class DeleteProjectTool implements ToolContract
{
    use HasStandardizedWriteOperations;
    public function getName(): string
    {
        return 'planner.projects.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht ein Projekt. RUF DIESES TOOL AUF, wenn der Nutzer ein Projekt löschen möchte. Die Projekt-ID ist erforderlich. Nutze "planner.projects.GET" um Projekte zu finden, wenn der Nutzer nur den Namen angibt. WICHTIG: Beim Löschen eines Projekts werden auch alle zugehörigen Slots und Aufgaben gelöscht. Frage den Nutzer nach Bestätigung, wenn das Projekt viele Aufgaben hat.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu löschenden Projekts (ERFORDERLICH). Nutze "planner.projects.GET" um Projekte zu finden.'
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bestätigung, dass das Projekt wirklich gelöscht werden soll. Wenn das Projekt viele Aufgaben hat, frage den Nutzer explizit nach Bestätigung.'
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
            
            // Prüfe Zugriff (nur Owner oder Admin kann löschen)
            $accessCheck = $this->checkAccess($project, $context, function($model, $ctx) {
                // Custom Access-Check: Owner oder Admin
                if ($model->user_id === $ctx->user->id) {
                    return true;
                }
                
                $isAdmin = $model->projectUsers()
                    ->where('user_id', $ctx->user->id)
                    ->where('role', 'admin')
                    ->exists();
                
                return $isAdmin;
            });
            
            if ($accessCheck) {
                return $accessCheck;
            }

            // Prüfe Anzahl der Aufgaben (für Warnung)
            $tasksCount = $project->tasks()->count();
            $slotsCount = $project->projectSlots()->count();

            // Bestätigung prüfen (wenn viele Aufgaben vorhanden)
            if ($tasksCount > 10 && !($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', "Das Projekt hat {$tasksCount} Aufgabe(n) und {$slotsCount} Slot(s). Bitte bestätige die Löschung mit 'confirm: true'. Beim Löschen werden alle Slots und Aufgaben ebenfalls gelöscht.");
            }

            $projectName = $project->name;
            $projectId = $project->id;
            $teamId = $project->team_id;

            // Projekt löschen (Cascade löscht automatisch Slots und Tasks)
            $project->delete();

            // Cache invalidieren für planner.projects.GET (damit gelöschte Projekte nicht mehr angezeigt werden)
            try {
                $cacheService = app(\Platform\Core\Services\ToolCacheService::class);
                if ($cacheService) {
                    // Invalidiere Cache für planner.projects.GET mit diesem Team
                    $cacheService->invalidate('planner.projects.GET', $context->user->id, $teamId);
                }
            } catch (\Throwable $e) {
                // Silent fail - Cache-Invalidierung ist nicht kritisch
            }

            return ToolResult::success([
                'project_id' => $projectId,
                'project_name' => $projectName,
                'deleted_tasks_count' => $tasksCount,
                'deleted_slots_count' => $slotsCount,
                'message' => "Projekt '{$projectName}' und alle zugehörigen Slots und Aufgaben wurden erfolgreich gelöscht."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Projekts: ' . $e->getMessage());
        }
    }
}

