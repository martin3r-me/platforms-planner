<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class ListCanvasesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvases.GET';
    }

    public function getDescription(): string
    {
        return 'GET /planner/canvases - Listet Project Canvases. Parameter: project_id (required), status (optional), filters/search/sort/limit/offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                    'project_id' => [
                        'type' => 'integer',
                        'description' => 'Projekt-ID (ERFORDERLICH).',
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['draft', 'active', 'archived'],
                        'description' => 'Optional: Filter nach Status.',
                    ],
                ],
                'required' => ['project_id'],
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

            $projectId = (int) ($arguments['project_id'] ?? 0);
            if ($projectId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'project_id ist erforderlich.');
            }

            $query = PlannerProjectCanvas::query()
                ->withCount('blocks', 'snapshots')
                ->where('team_id', $teamId)
                ->where('project_id', $projectId);

            if (isset($arguments['status'])) {
                $query->byStatus($arguments['status']);
            }

            $this->applyStandardFilters($query, $arguments, ['name', 'status', 'created_at', 'updated_at']);
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            $this->applyStandardSort($query, $arguments, ['name', 'status', 'created_at', 'updated_at'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (PlannerProjectCanvas $canvas) {
                return [
                    'id' => $canvas->id,
                    'uuid' => $canvas->uuid,
                    'name' => $canvas->name,
                    'description' => $canvas->description,
                    'status' => $canvas->status,
                    'project_id' => $canvas->project_id,
                    'blocks_count' => $canvas->blocks_count,
                    'snapshots_count' => $canvas->snapshots_count,
                    'team_id' => $canvas->team_id,
                    'created_at' => $canvas->created_at?->toISOString(),
                    'updated_at' => $canvas->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Canvases: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvases', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
