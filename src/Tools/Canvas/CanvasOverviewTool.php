<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class CanvasOverviewTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /planner/canvas/overview - Zeigt Uebersicht aller Project Canvases im Team mit Status-Ampel und Statistiken.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Canvases eines bestimmten Projekts.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $query = PlannerProjectCanvas::query()
                ->where('team_id', $teamId)
                ->withCount('blocks', 'snapshots');

            if (!empty($arguments['project_id'])) {
                $query->where('project_id', (int) $arguments['project_id']);
            }

            $canvases = $query->orderBy('updated_at', 'desc')->get();

            $statusService = new \Platform\Planner\Services\ProjectCanvasStatusService();

            $data = $canvases->map(function (PlannerProjectCanvas $canvas) use ($statusService) {
                $status = $statusService->assessStatus($canvas);
                return [
                    'id' => $canvas->id,
                    'uuid' => $canvas->uuid,
                    'name' => $canvas->name,
                    'project_id' => $canvas->project_id,
                    'status' => $canvas->status,
                    'ampel' => $status['color'],
                    'score' => $status['score'],
                    'completeness' => $status['completeness_percent'],
                    'blocks_count' => $canvas->blocks_count,
                    'snapshots_count' => $canvas->snapshots_count,
                    'updated_at' => $canvas->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'canvases' => $data,
                'total' => count($data),
                'by_status' => [
                    'draft' => $canvases->where('status', 'draft')->count(),
                    'active' => $canvases->where('status', 'active')->count(),
                    'archived' => $canvases->where('status', 'archived')->count(),
                ],
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Canvas-Uebersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvas', 'overview'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
