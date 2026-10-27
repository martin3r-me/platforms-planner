<?php

namespace Platform\Planner\Tools\Canvas\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Services\ProjectCanvasService;

/**
 * Canvas-Aufloesung fuer Tools: ein Canvas pro Projekt, Zugriff wie in der UI
 * (Projekt-Policy view/update + Sichtbarkeit privater Canvases).
 */
trait ResolvesProjectCanvas
{
    /** Schema-Schnipsel: Canvas per canvas_id ODER project_id adressieren. */
    protected function canvasTargetSchema(): array
    {
        return [
            'project_id' => [
                'type' => 'integer',
                'description' => 'Projekt-ID — adressiert das (einzige) Canvas des Projekts. Alternativ canvas_id.',
            ],
            'canvas_id' => [
                'type' => 'integer',
                'description' => 'Canvas-ID. Alternativ project_id.',
            ],
        ];
    }

    /**
     * @param 'view'|'update' $ability
     * @return array{0: ?PlannerProjectCanvas, 1: ?ToolResult}
     */
    protected function resolveCanvas(array $arguments, ToolContext $context, string $ability = 'view'): array
    {
        if (! $context->user) {
            return [null, ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.')];
        }

        $canvasId = (int) ($arguments['canvas_id'] ?? 0);
        $projectId = (int) ($arguments['project_id'] ?? 0);

        if ($canvasId > 0) {
            $canvas = PlannerProjectCanvas::with('project')->find($canvasId);
            if (! $canvas || ($projectId > 0 && (int) $canvas->project_id !== $projectId)) {
                return [null, ToolResult::error('NOT_FOUND', 'Canvas nicht gefunden.')];
            }
        } elseif ($projectId > 0) {
            $project = PlannerProject::find($projectId);
            if (! $project) {
                return [null, ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden.')];
            }
            // Ein Projekt ohne Canvas ist keine Option → bei Altbestand anlegen
            $canvas = Gate::forUser($context->user)->allows('view', $project)
                ? app(ProjectCanvasService::class)->ensureForProject($project, $context->user->id)
                : null;
            if (! $canvas) {
                return [null, ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Projekt.')];
            }
        } else {
            return [null, ToolResult::error('VALIDATION_ERROR', 'project_id oder canvas_id ist erforderlich.')];
        }

        if ($error = $this->authorizeCanvas($canvas, $context, $ability)) {
            return [null, $error];
        }

        return [$canvas, null];
    }

    /** @param 'view'|'update' $ability */
    protected function authorizeCanvas(?PlannerProjectCanvas $canvas, ToolContext $context, string $ability = 'view'): ?ToolResult
    {
        if (! $canvas || ! $context->user) {
            return ToolResult::error('NOT_FOUND', 'Canvas nicht gefunden.');
        }
        $project = $canvas->project;
        if (! $project || ! Gate::forUser($context->user)->allows($ability, $project)) {
            return ToolResult::error('ACCESS_DENIED', $ability === 'view'
                ? 'Kein Lesezugriff auf das Projekt dieses Canvas.'
                : 'Keine Schreibrechte am Projekt dieses Canvas.');
        }
        if (method_exists($canvas, 'isVisibleTo') && ! $canvas->isVisibleTo($context->user)) {
            return ToolResult::error('ACCESS_DENIED', 'Dieses Canvas ist privat.');
        }

        return null;
    }
}
