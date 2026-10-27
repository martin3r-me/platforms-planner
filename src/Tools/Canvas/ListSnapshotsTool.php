<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class ListSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'Listet die Versionen (Stände) des Projekt-Canvas, neueste zuerst — mit Herkunft (kickoff/manual/agent/auto/restore) und Bezeichnung. Pflicht: project_id oder canvas_id. Was sich geändert hat: planner.canvas.changes.GET.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    ...$this->canvasTargetSchema(),
                ],
                'required' => [],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'view');
            if ($error) {
                return $error;
            }

            $query = PlannerProjectCanvasSnapshot::query()
                ->where('canvas_id', $canvas->id)
                ->orderBy('version', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (PlannerProjectCanvasSnapshot $snapshot) {
                return [
                    'id' => $snapshot->id,
                    'uuid' => $snapshot->uuid,
                    'version' => $snapshot->version,
                    'source' => $snapshot->source,
                    'label' => $snapshot->label,
                    'created_by_user_id' => $snapshot->created_by_user_id,
                    'created_at' => $snapshot->created_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'canvas_id' => $canvas->id,
                'team_id' => $canvas->team_id,
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
