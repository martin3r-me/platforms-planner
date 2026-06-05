<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectCanvasEntry;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class DeleteEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.entry.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /planner/canvas/entries/{id} - Loescht einen Entry (Soft-Delete). ERFORDERLICH: entry_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'entry_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Entry (ERFORDERLICH).',
                ],
            ],
            'required' => ['entry_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $entryId = (int) ($arguments['entry_id'] ?? 0);
            if ($entryId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entry_id ist erforderlich.');
            }

            $entry = PlannerProjectCanvasEntry::query()
                ->whereHas('block.canvas', fn ($q) => $q->where('team_id', $teamId))
                ->find($entryId);

            if (!$entry) {
                return ToolResult::error('NOT_FOUND', 'Entry nicht gefunden (oder kein Zugriff).');
            }

            $entry->delete();

            return ToolResult::success([
                'id' => $entryId,
                'message' => 'Entry geloescht (Soft-Delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen des Entry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'entry', 'delete'],
            'risk_level' => 'destructive',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
