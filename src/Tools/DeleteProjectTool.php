<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

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
        return 'DELETE /projects/{id} - Löscht ein Projekt. REST-Parameter: id (required, integer) - Projekt-ID. Hinweis: Beim Löschen werden auch alle zugehörigen Slots und Aufgaben gelöscht (Standard: Soft-Delete, wiederherstellbar). Mit force=true wird unwiderruflich hart gelöscht (Purge, z.B. für DSGVO-Löschpflichten) — nur für Owner/Admin, erfordert confirm=true.';
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
                    'description' => 'Optional: Bestätigung, dass das Projekt wirklich gelöscht werden soll. Wenn das Projekt viele Aufgaben hat, frage den Nutzer explizit nach Bestätigung. Bei force=true IMMER erforderlich.'
                ],
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Hard-Delete/Purge statt Soft-Delete. Löscht das Projekt inkl. Slots, Aufgaben und Canvases unwiderruflich aus der Datenbank (kein Restore möglich). Nur für Owner/Admin. Erfordert zwingend confirm=true.'
                ]
            ],
            'required' => ['project_id']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $force = (bool) ($arguments['force'] ?? false);

            // Nutze standardisierte ID-Validierung (loose coupled - optional)
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'project_id',
                PlannerProject::class,
                'PROJECT_NOT_FOUND',
                'Das angegebene Projekt wurde nicht gefunden.'
            );

            $project = $validation['model'];

            // Bei force=true auch bereits soft-gelöschte Projekte finden — genau diese sind
            // der Hauptfall für Purge (z.B. DSGVO-Löschpflicht auf zuvor soft-gelöschten Daten).
            // validateAndFindModel() nutzt den Standard-Scope (ohne withTrashed) und würde sie
            // sonst fälschlich als "nicht gefunden" melden.
            if (!$project && $force && !empty($arguments['project_id'])) {
                $project = PlannerProject::withTrashed()->find((int) $arguments['project_id']);
            }

            if (!$project) {
                return $validation['error'] ?? ToolResult::error('PROJECT_NOT_FOUND', 'Das angegebene Projekt wurde nicht gefunden.');
            }

            // Policy: Soft-Delete wie bisher nur Owner/Admin (delete); Hard-Delete/Purge
            // nutzt eine eigene Ability (aktuell gleiche Schwelle: Owner/Admin), damit die
            // Absicht im Code sichtbar bleibt und sich beide Schwellen unabhängig verschärfen lassen.
            try {
                Gate::forUser($context->user)->authorize($force ? 'forceDelete' : 'delete', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', $force
                    ? 'Du darfst dieses Projekt nicht unwiderruflich löschen (nur Owner/Admin).'
                    : 'Du darfst dieses Projekt nicht löschen (Policy).');
            }

            // Prüfe Anzahl der Aufgaben (für Warnung); bei force inkl. bereits soft-gelöschter
            // Kinder, damit die Purge-Zahlen vollständig sind.
            $tasksCount = $force ? $project->tasks()->withTrashed()->count() : $project->tasks()->count();
            $slotsCount = $project->projectSlots()->count();
            $canvasesCount = $force ? $project->canvases()->withTrashed()->count() : $project->canvases()->count();

            if ($force) {
                // Hard-Delete ist unumkehrbar: Bestätigung immer erforderlich, unabhängig von der Größe.
                if (!($arguments['confirm'] ?? false)) {
                    return ToolResult::error('CONFIRMATION_REQUIRED', "Hard-Delete/Purge von Projekt '{$project->name}' (inkl. {$tasksCount} Aufgabe(n), {$slotsCount} Slot(s), {$canvasesCount} Canvas/Canvases) ist UNWIDERRUFLICH und nicht wiederherstellbar. Bitte bestätige explizit mit 'confirm: true'.");
                }
            } elseif ($tasksCount > 10 && !($arguments['confirm'] ?? false)) {
                // Bestätigung prüfen (wenn viele Aufgaben vorhanden)
                return ToolResult::error('CONFIRMATION_REQUIRED', "Das Projekt hat {$tasksCount} Aufgabe(n) und {$slotsCount} Slot(s). Bitte bestätige die Löschung mit 'confirm: true'. Beim Löschen werden alle Slots und Aufgaben ebenfalls gelöscht.");
            }

            $projectName = $project->name;
            $projectId = $project->id;
            $teamId = $project->team_id;

            if ($force) {
                // Audit-Log VOR dem Purge schreiben (danach ist die morphMany-Relation weg möglich,
                // je nach Reihenfolge der Kaskade — daher explizit zuerst).
                if (method_exists($project, 'logActivity')) {
                    $project->logActivity(
                        "Projekt '{$projectName}' hart gelöscht (Purge, force=true) durch {$context->user->name}.",
                        ['deleted_tasks_count' => $tasksCount, 'deleted_slots_count' => $slotsCount, 'deleted_canvases_count' => $canvasesCount]
                    );
                }

                // Kinder explizit hart löschen (statt auf DB-Cascade zu vertrauen), damit jedes
                // Kind sein eigenes Audit-Log-Event bekommt — DB-seitiges ON DELETE CASCADE feuert
                // keine Eloquent-Events und würde das Activity-Log für Kinder überspringen.
                foreach ($project->tasks()->withTrashed()->get() as $task) {
                    if (method_exists($task, 'logActivity')) {
                        $task->logActivity("Aufgabe hart gelöscht (Purge von Projekt '{$projectName}', force=true) durch {$context->user->name}.");
                    }
                    $task->forceDelete();
                }
                foreach ($project->canvases()->withTrashed()->get() as $canvas) {
                    if (method_exists($canvas, 'logActivity')) {
                        $canvas->logActivity("Canvas hart gelöscht (Purge von Projekt '{$projectName}', force=true) durch {$context->user->name}.");
                    }
                    $canvas->forceDelete();
                }

                // Slots haben kein SoftDeletes (bereits echter Delete) — Kaskade über die
                // project_id-FK (onDelete cascade) beim finalen forceDelete() ist hier ausreichend.
                $project->forceDelete();
            } else {
                // Projekt löschen (Cascade löscht automatisch Slots und Tasks)
                $project->delete();
            }

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
                'deleted_canvases_count' => $canvasesCount,
                'force' => $force,
                'message' => $force
                    ? "Projekt '{$projectName}' und alle zugehörigen Slots, Aufgaben und Canvases wurden UNWIDERRUFLICH aus der Datenbank gelöscht (Purge)."
                    : "Projekt '{$projectName}' und alle zugehörigen Slots und Aufgaben wurden erfolgreich gelöscht."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Projekts: ' . $e->getMessage());
        }
    }
}

