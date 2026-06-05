<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectCanvasEntry;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class UpdateEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.entry.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /planner/canvas/entries/{id} - Aktualisiert einen Entry. ERFORDERLICH: entry_id. Optional: title, content, entry_type, position, metadata.';
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
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Titel.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Inhalt.',
                ],
                'entry_type' => [
                    'type' => 'string',
                    'enum' => ['text', 'date', 'person', 'amount'],
                    'description' => 'Optional: Neuer Typ.',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Position.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Neue Metadaten (JSON).',
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

            $updated = [];
            foreach (['title', 'content', 'entry_type', 'position', 'metadata'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $entry->{$field} = $arguments[$field];
                    $updated[] = $field;
                }
            }

            if (empty($updated)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren angegeben.');
            }

            $entry->save();

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'title' => $entry->title,
                'content' => $entry->content,
                'entry_type' => $entry->entry_type,
                'position' => $entry->position,
                'updated_fields' => $updated,
                'message' => 'Entry aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Entry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'entry', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
