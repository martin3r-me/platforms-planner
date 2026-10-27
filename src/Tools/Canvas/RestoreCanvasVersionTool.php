<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;
use Platform\Planner\Services\ProjectCanvasVersioning;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class RestoreCanvasVersionTool implements ToolContract, ToolMetadataContract
{
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.restore.POST';
    }

    public function getDescription(): string
    {
        return 'Stellt einen früheren Stand des Projekt-Canvas wieder her. Nichts geht verloren: der aktuelle Stand wird vorher als Version gesichert, '
            . 'der wiederhergestellte danach als neue Version. Pflicht: project_id oder canvas_id, version. Vorher mit planner.canvas.changes.GET zeigen, was sich ändert, und beim Nutzer bestätigen lassen (confirm=true).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->canvasTargetSchema(),
                'version' => ['type' => 'integer', 'description' => 'Versionsnummer, die wiederhergestellt werden soll (ERFORDERLICH).'],
                'confirm' => ['type' => 'boolean', 'description' => 'Muss true sein — ohne confirm kommt nur eine Vorschau der Änderungen.'],
            ],
            'required' => ['version'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'update');
            if ($error) {
                return $error;
            }

            $snapshot = PlannerProjectCanvasSnapshot::where('canvas_id', $canvas->id)
                ->where('version', (int) ($arguments['version'] ?? 0))
                ->first();
            if (! $snapshot) {
                return ToolResult::error('NOT_FOUND', 'Version nicht gefunden. Versionen: planner.canvas.snapshots.GET.');
            }

            $versioning = app(ProjectCanvasVersioning::class);

            if (empty($arguments['confirm'])) {
                return ToolResult::success([
                    'requires_confirmation' => true,
                    'preview' => $versioning->diff($canvas->toCanvasArray(), $snapshot->snapshot_data ?? []),
                    'message' => "Vorschau: so würde sich das Canvas durch Wiederherstellen von v{$snapshot->version} ändern. Mit confirm=true ausführen.",
                ]);
            }

            $new = $versioning->restore($snapshot, $context->user->id);

            return ToolResult::success([
                'restored_from' => $versioning->snapshotMeta($snapshot),
                'new_version' => $versioning->snapshotMeta($new),
                'message' => "v{$snapshot->version} wiederhergestellt (jetzt v{$new->version}). Der vorherige Stand bleibt als Version erhalten. "
                    . 'Falls das eine Entscheidung ist: planner.project.logbook.POST mit Begründung.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Wiederherstellen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'versions', 'restore'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => false,
        ];
    }
}
