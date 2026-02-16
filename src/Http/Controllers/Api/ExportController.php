<?php

namespace Platform\Planner\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Export\ExportService;
use Platform\Planner\Export\ExportFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Export-Controller für Aufgaben und Projekte.
 *
 * Stellt API-Endpoints für den Export als JSON und PDF bereit.
 * Nutzt den ExportService für die Formatierung.
 */
class ExportController extends ApiController
{
    protected ExportService $exportService;

    public function __construct()
    {
        $this->exportService = new ExportService();
    }

    /**
     * Exportiert eine einzelne Aufgabe.
     *
     * GET /api/planner/export/tasks/{task}
     * Query: format=json|pdf
     */
    public function exportTask(Request $request, int $taskId)
    {
        $task = PlannerTask::find($taskId);

        if (!$task) {
            return $this->error('Aufgabe nicht gefunden.', 404);
        }

        // Autorisierung: User muss die Aufgabe sehen dürfen
        if (Gate::denies('view', $task)) {
            return $this->error('Keine Berechtigung zum Export dieser Aufgabe.', 403);
        }

        $format = $this->resolveFormat($request);

        if (!$format) {
            return $this->error('Ungültiges Format. Erlaubt: json, pdf', 422);
        }

        return $this->exportService->exportTask($task, $format);
    }

    /**
     * Exportiert ein ganzes Projekt.
     *
     * GET /api/planner/export/projects/{project}
     * Query: format=json|pdf
     */
    public function exportProject(Request $request, int $projectId)
    {
        $project = PlannerProject::find($projectId);

        if (!$project) {
            return $this->error('Projekt nicht gefunden.', 404);
        }

        // Autorisierung: User muss das Projekt sehen dürfen
        if (Gate::denies('view', $project)) {
            return $this->error('Keine Berechtigung zum Export dieses Projekts.', 403);
        }

        $format = $this->resolveFormat($request);

        if (!$format) {
            return $this->error('Ungültiges Format. Erlaubt: json, pdf', 422);
        }

        return $this->exportService->exportProject($project, $format);
    }

    /**
     * Listet verfügbare Export-Formate.
     *
     * GET /api/planner/export/formats
     */
    public function formats()
    {
        $formats = array_map(fn(ExportFormat $f) => [
            'key' => $f->value,
            'label' => $f->label(),
            'mime_type' => $f->mimeType(),
            'extension' => $f->extension(),
        ], $this->exportService->availableFormats());

        return $this->success($formats, 'Verfügbare Export-Formate');
    }

    /**
     * Löst das gewünschte Format aus dem Request auf.
     */
    protected function resolveFormat(Request $request): ?ExportFormat
    {
        $formatValue = $request->get('format', 'json');

        return ExportFormat::tryFrom($formatValue);
    }
}
