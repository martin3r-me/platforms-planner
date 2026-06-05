<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectCanvasBlock;
use Platform\Planner\Services\ProjectCanvasEntryService;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class BulkCreateEntriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.entries.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /planner/canvas/entries/bulk - Erstellt mehrere Entries auf einmal. ERFORDERLICH: block_id, entries (Array mit title, optional content/entry_type/metadata).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'block_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Building Blocks (ERFORDERLICH).',
                ],
                'entries' => [
                    'type' => 'array',
                    'description' => 'Array von Entries (ERFORDERLICH). Jeder Entry: { title, content?, entry_type?, metadata? }',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                            'entry_type' => ['type' => 'string', 'enum' => ['text', 'date', 'person', 'amount']],
                            'metadata' => ['type' => 'object'],
                        ],
                        'required' => ['title'],
                    ],
                ],
            ],
            'required' => ['block_id', 'entries'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

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

            $entriesData = $arguments['entries'] ?? [];
            if (!is_array($entriesData) || empty($entriesData)) {
                return ToolResult::error('VALIDATION_ERROR', 'entries Array ist erforderlich und darf nicht leer sein.');
            }

            $entryService = new ProjectCanvasEntryService();
            $created = $entryService->bulkCreateEntries($block, $entriesData, $context->user->id);

            return ToolResult::success([
                'block_id' => $blockId,
                'created_count' => count($created),
                'entries' => array_map(fn ($e) => [
                    'id' => $e->id,
                    'uuid' => $e->uuid,
                    'title' => $e->title,
                    'entry_type' => $e->entry_type,
                    'position' => $e->position,
                ], $created),
                'message' => count($created) . ' Entries erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Erstellen der Entries: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'entries', 'bulk', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
