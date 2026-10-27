<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Services\ProjectCanvasStatusService;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class CanvasStatusTool implements ToolContract, ToolMetadataContract
{
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.status.GET';
    }

    public function getDescription(): string
    {
        return 'Bewertet Canvas-Vollstaendigkeit und Health-Metriken (Ampel-Logik). ERFORDERLICH: canvas_id.';
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
            $canvas->loadMissing(['blocks.entries']);

            $statusService = new ProjectCanvasStatusService();
            $status = $statusService->assessStatus($canvas);

            return ToolResult::success($status);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bewerten des Canvas-Status: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvas', 'status', 'metrics'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
