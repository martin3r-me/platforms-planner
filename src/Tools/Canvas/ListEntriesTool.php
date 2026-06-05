<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProjectCanvasBlock;
use Platform\Planner\Models\PlannerProjectCanvasEntry;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class ListEntriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.entries.GET';
    }

    public function getDescription(): string
    {
        return 'GET /planner/canvas/entries - Listet Entries eines Building Blocks. ERFORDERLICH: block_id. Optional: entry_type, limit/offset.';
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
                    'block_id' => [
                        'type' => 'integer',
                        'description' => 'ID des Building Blocks (ERFORDERLICH).',
                    ],
                    'entry_type' => [
                        'type' => 'string',
                        'enum' => ['text', 'date', 'person', 'amount'],
                        'description' => 'Optional: Filter nach Entry-Typ.',
                    ],
                ],
                'required' => ['block_id'],
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

            $blockId = (int) ($arguments['block_id'] ?? 0);
            if ($blockId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'block_id ist erforderlich.');
            }

            $block = PlannerProjectCanvasBlock::query()
                ->whereHas('canvas', fn ($q) => $q->where('team_id', $teamId))
                ->find($blockId);

            if (!$block) {
                return ToolResult::error('NOT_FOUND', 'Building Block nicht gefunden (oder kein Zugriff).');
            }

            $query = $block->entries()->orderBy('position');

            if (isset($arguments['entry_type'])) {
                $query->where('entry_type', $arguments['entry_type']);
            }

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (PlannerProjectCanvasEntry $entry) {
                return [
                    'id' => $entry->id,
                    'uuid' => $entry->uuid,
                    'title' => $entry->title,
                    'content' => $entry->content,
                    'entry_type' => $entry->entry_type,
                    'position' => $entry->position,
                    'metadata' => $entry->metadata,
                    'created_by_user_id' => $entry->created_by_user_id,
                    'created_at' => $entry->created_at?->toISOString(),
                    'updated_at' => $entry->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'block_id' => $blockId,
                'block_type' => $block->block_type,
                'guiding_questions' => $block->getGuidingQuestions(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Entries: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvas', 'entries', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
