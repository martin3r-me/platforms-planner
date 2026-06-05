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

class CreateEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.entries.POST';
    }

    public function getDescription(): string
    {
        return 'POST /planner/canvas/entries - Erstellt einen neuen Entry in einem Building Block. ERFORDERLICH: block_id, title. Optional: content, entry_type (text/date/person/amount), position, metadata.';
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
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel des Entries (ERFORDERLICH).',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Optional: Inhalt/Beschreibung.',
                ],
                'entry_type' => [
                    'type' => 'string',
                    'enum' => ['text', 'date', 'person', 'amount'],
                    'description' => 'Optional: Typ (default: text).',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => 'Optional: Position (default: ans Ende).',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Zusaetzliche Metadaten (JSON).',
                ],
            ],
            'required' => ['block_id', 'title'],
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

            $title = trim((string) ($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }

            $entryService = new ProjectCanvasEntryService();
            $entry = $entryService->createEntry($block, [
                'title' => $title,
                'content' => $arguments['content'] ?? null,
                'entry_type' => $arguments['entry_type'] ?? 'text',
                'position' => $arguments['position'] ?? null,
                'metadata' => $arguments['metadata'] ?? null,
                'created_by_user_id' => $context->user->id,
            ]);

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'title' => $entry->title,
                'content' => $entry->content,
                'entry_type' => $entry->entry_type,
                'position' => $entry->position,
                'block_id' => $blockId,
                'block_type' => $block->block_type,
                'message' => 'Entry erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Entry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'entries', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
