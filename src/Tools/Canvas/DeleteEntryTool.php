<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectCanvasEntry;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

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
        return 'DELETE /planner/canvas/entries/{id} - Loescht einen Entry (Standard: Soft-Delete). ERFORDERLICH: entry_id. Mit force=true wird unwiderruflich hart gelöscht (Purge) — nur für Owner/Admin des zugehörigen Projekts, erfordert confirm=true.';
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
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Hard-Delete/Purge statt Soft-Delete. Löscht den Entry unwiderruflich (kein Restore möglich). Nur für Owner/Admin des Projekts. Erfordert zwingend confirm=true.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Bei force=true IMMER erforderlich: explizite Bestätigung der unwiderruflichen Löschung.',
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

            $force = (bool) ($arguments['force'] ?? false);

            $query = PlannerProjectCanvasEntry::query()
                ->whereHas('block.canvas', fn ($q) => $q->where('team_id', $teamId));
            if ($force) {
                // Bei force=true auch bereits soft-gelöschte Entries finden — genau diese sind
                // der Hauptfall für Purge.
                $query->withTrashed();
            }
            $entry = $query->find($entryId);

            if (!$entry) {
                return ToolResult::error('NOT_FOUND', 'Entry nicht gefunden (oder kein Zugriff).');
            }

            if ($force) {
                // Hard-Delete/Purge: nur Owner/Admin des zugehörigen Projekts (gleiche Schwelle
                // wie planner.projects.DELETE force=true), da Entry/Canvas keine eigene Policy hat.
                $project = $entry->block?->canvas?->project;
                if (!$project) {
                    return ToolResult::error('PROJECT_NOT_FOUND', 'Das zugehörige Projekt wurde nicht gefunden.');
                }
                try {
                    Gate::forUser($context->user)->authorize('forceDelete', $project);
                } catch (AuthorizationException $e) {
                    return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Entry nicht unwiderruflich löschen (nur Owner/Admin des Projekts).');
                }

                if (!($arguments['confirm'] ?? false)) {
                    return ToolResult::error('CONFIRMATION_REQUIRED', 'Hard-Delete/Purge des Entry ist UNWIDERRUFLICH und nicht wiederherstellbar. Bitte bestätige explizit mit \'confirm: true\'.');
                }

                if (method_exists($entry, 'logActivity')) {
                    $entry->logActivity("Entry hart gelöscht (Purge, force=true) durch {$context->user->name}.");
                }
                $entry->forceDelete();

                return ToolResult::success([
                    'id' => $entryId,
                    'force' => true,
                    'message' => 'Entry wurde UNWIDERRUFLICH aus der Datenbank gelöscht (Purge).',
                ]);
            }

            $entry->delete();

            return ToolResult::success([
                'id' => $entryId,
                'force' => false,
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
