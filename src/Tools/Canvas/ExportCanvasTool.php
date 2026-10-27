<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class ExportCanvasTool implements ToolContract, ToolMetadataContract
{
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.export.GET';
    }

    public function getDescription(): string
    {
        return 'Exportiert einen Project Canvas als strukturierte Daten (fuer Markdown/PDF). ERFORDERLICH: canvas_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->canvasTargetSchema(),
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'view');
            if ($error) {
                return $error;
            }

            $canvasService = new ProjectCanvasService();
            $export = $canvasService->exportCanvas($canvas);

            return ToolResult::success($export);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Exportieren des Canvas: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvas', 'export'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
