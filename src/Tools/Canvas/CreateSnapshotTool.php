<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class CreateSnapshotTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.snapshots.POST';
    }

    public function getDescription(): string
    {
        return 'Hält den aktuellen Stand des Projekt-Canvas als benannte Version fest — optional zusammen mit einem Logbuch-Eintrag (Entscheidung/Meilenstein/Risiko), '
            . 'der auf genau diesen Stand verweist. Pflicht: project_id oder canvas_id. Hinweis: Versionen entstehen ohnehin automatisch je Bearbeitungssitzung; '
            . 'festhalten lohnt sich, wenn der Stand eine Bedeutung hat (z.B. nach Kundentermin, vor großem Umbau).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                ...$this->canvasTargetSchema(),
                'label' => ['type' => 'string', 'description' => 'Optional: Bezeichnung des Stands, z.B. "Nach Abstimmung mit Kunde".'],
                'logbook' => [
                    'type' => 'object',
                    'description' => 'Optional: Logbuch-Eintrag mit Verweis auf diesen Stand — das Warum zur Änderung.',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['decision', 'milestone', 'risk']],
                        'title' => ['type' => 'string', 'description' => 'Was (Pflicht, wenn logbook angegeben).'],
                        'rationale' => ['type' => 'string', 'description' => 'Warum: Auslöser, Abwägung, verworfene Alternativen.'],
                    ],
                ],
            ],
            'required' => [],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'update');
            if ($error) {
                return $error;
            }

            $logbook = is_array($arguments['logbook'] ?? null) ? $arguments['logbook'] : null;
            if ($logbook && trim((string) ($logbook['title'] ?? '')) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'logbook.title ist erforderlich, wenn ein Logbuch-Eintrag angelegt werden soll.');
            }
            if ($logbook) {
                $logbook['source'] = 'agent';
            }

            try {
                $result = app(\Platform\Planner\Services\ProjectCanvasVersioning::class)->capture(
                    $canvas,
                    $context->user,
                    trim((string) ($arguments['label'] ?? '')) ?: null,
                    $logbook,
                    \Platform\Planner\Services\ProjectCanvasVersioning::SOURCE_AGENT,
                );
            } catch (\InvalidArgumentException $e) {
                return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
            }
            $snapshot = $result['snapshot'];

            return ToolResult::success([
                'id' => $snapshot->id,
                'canvas_id' => $snapshot->canvas_id,
                'version' => $snapshot->version,
                'label' => $snapshot->label,
                'source' => $snapshot->source,
                'created_at' => $snapshot->created_at?->toISOString(),
                'logbook_entry' => $result['entry']?->toToolArray(),
                'message' => "Stand als v{$snapshot->version} festgehalten" . ($result['entry'] ? ' und im Logbuch verknüpft.' : '.'),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Snapshots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'snapshots', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => false,
        ];
    }
}
