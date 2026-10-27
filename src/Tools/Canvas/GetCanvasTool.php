<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class GetCanvasTool implements ToolContract, ToolMetadataContract
{
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.GET';
    }

    public function getDescription(): string
    {
        return 'Liest das Projekt-Canvas: Kickoff und aktueller Zustand des Projekts (Ziel, Beteiligte, Nutzen, Rahmen, Risiken …) mit allen Blöcken, Einträgen und Leitfragen. '
            . 'Jedes Projekt hat genau ein Canvas → project_id genügt. Enthält Kickoff-Status und Versionsstand. '
            . 'Zustand = Canvas (wird überschrieben, Versionen automatisch); Verlauf/Begründungen = planner.project.logbook.*; Arbeit = planner.tasks.*.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->canvasTargetSchema(),
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'view');
            if ($error) {
                return $error;
            }
            $canvas->loadMissing(['blocks.entries', 'createdByUser']);

            $canvasData = $canvas->toCanvasArray();
            $blockTypes = config('planner.canvas_block_types', []);

            // Guiding questions hinzufuegen
            foreach ($canvasData['blocks'] as $type => &$block) {
                $block['guiding_questions'] = $blockTypes[$type]['guiding_questions'] ?? [];
                $block['description'] = $blockTypes[$type]['description'] ?? '';
            }

            $latest = $canvas->snapshots()->orderByDesc('version')->first();
            $kickoff = $canvas->snapshots()->where('source', \Platform\Planner\Services\ProjectCanvasVersioning::SOURCE_KICKOFF)->orderBy('version')->first();

            return ToolResult::success(array_merge($canvasData, [
                'kickoff' => [
                    'completed' => (bool) $canvas->kickoff_completed_at,
                    'completed_at' => $canvas->kickoff_completed_at?->toISOString(),
                    'version' => $kickoff?->version,
                    'hint' => $canvas->kickoff_completed_at ? null : 'Kickoff offen: Canvas füllen, dann planner.canvas.kickoff.POST.',
                ],
                'versions' => [
                    'count' => $canvas->snapshots()->count(),
                    'latest' => $latest?->version,
                    'latest_at' => $latest?->created_at?->toISOString(),
                ],
            ]));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Canvas: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvas', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
