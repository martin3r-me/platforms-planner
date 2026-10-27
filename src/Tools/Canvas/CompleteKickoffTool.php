<?php

namespace Platform\Planner\Tools\Canvas;

use InvalidArgumentException;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Services\ProjectCanvasVersioning;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class CompleteKickoffTool implements ToolContract, ToolMetadataContract
{
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.kickoff.POST';
    }

    public function getDescription(): string
    {
        return 'Schließt den Kickoff eines Projekts ab: sichert den aktuellen Canvas-Stand als Kickoff-Version (Anker für alle späteren Vergleiche) '
            . 'und schreibt den Meilenstein "Kickoff abgeschlossen" (mit Projektziel) ins Logbuch. Nur einmal pro Projekt. Pflicht: project_id oder canvas_id. '
            . 'Vorher sicherstellen, dass mindestens Ziel, Beteiligte und Risiken im Canvas stehen (planner.canvas.GET / planner.canvas.status.GET).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->canvasTargetSchema(),
                'note' => ['type' => 'string', 'description' => 'Optional: Notiz zum Kickoff (wer war dabei, was bleibt bewusst offen) — landet in der Begründung des Logbuch-Eintrags.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'update');
            if ($error) {
                return $error;
            }

            $versioning = app(ProjectCanvasVersioning::class);
            try {
                $result = $versioning->completeKickoff($canvas, $context->user, trim((string) ($arguments['note'] ?? '')) ?: null, ProjectCanvasVersioning::SOURCE_KICKOFF);
            } catch (InvalidArgumentException $e) {
                return ToolResult::error('ALREADY_DONE', $e->getMessage());
            }

            return ToolResult::success([
                'canvas_id' => $canvas->id,
                'kickoff_version' => $versioning->snapshotMeta($result['snapshot']),
                'logbook_entry' => $result['entry']->toToolArray(),
                'message' => 'Kickoff abgeschlossen: Stand gesichert und als Meilenstein im Logbuch festgehalten.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Kickoff: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'kickoff', 'logbook'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => false,
        ];
    }
}
