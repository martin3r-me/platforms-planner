<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class ListSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /planner/canvas/snapshots - Listet Snapshots eines Canvas. ERFORDERLICH: canvas_id. Optional: limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID.',
                    ],
                    'canvas_id' => [
                        'type' => 'integer',
                        'description' => 'ID des Canvas (ERFORDERLICH).',
                    ],
                ],
                'required' => ['canvas_id'],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $canvasId = (int) ($arguments['canvas_id'] ?? 0);
            if ($canvasId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'canvas_id ist erforderlich.');
            }

            $canvas = PlannerProjectCanvas::query()
                ->where('team_id', $teamId)
                ->find($canvasId);

            if (!$canvas) {
                return ToolResult::error('NOT_FOUND', 'Canvas nicht gefunden (oder kein Zugriff).');
            }

            $query = PlannerProjectCanvasSnapshot::query()
                ->where('canvas_id', $canvasId)
                ->orderBy('version', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (PlannerProjectCanvasSnapshot $snapshot) {
                return [
                    'id' => $snapshot->id,
                    'uuid' => $snapshot->uuid,
                    'version' => $snapshot->version,
                    'created_by_user_id' => $snapshot->created_by_user_id,
                    'created_at' => $snapshot->created_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'canvas_id' => $canvasId,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Snapshots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvas', 'snapshots', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
