<?php

namespace Platform\Planner\Services;

use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;

class ProjectCanvasService
{
    /**
     * Create a new canvas with all 9 Project Canvas building blocks.
     */
    public function createCanvas(array $data): PlannerProjectCanvas
    {
        $canvas = PlannerProjectCanvas::create($data);
        $canvas->initializeBlocks();

        return $canvas->load('blocks');
    }

    /**
     * Create a snapshot of the current canvas state.
     */
    public function createSnapshot(PlannerProjectCanvas $canvas, int $userId): PlannerProjectCanvasSnapshot
    {
        $canvasData = $canvas->toCanvasArray();

        $latestVersion = $canvas->snapshots()->max('version') ?? 0;

        return $canvas->snapshots()->create([
            'version' => $latestVersion + 1,
            'snapshot_data' => $canvasData,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Compare two snapshots and return a diff.
     */
    public function compareSnapshots(PlannerProjectCanvasSnapshot $snapshotA, PlannerProjectCanvasSnapshot $snapshotB): array
    {
        $dataA = $snapshotA->snapshot_data;
        $dataB = $snapshotB->snapshot_data;

        $diff = [];
        $allBlockTypes = array_unique(array_merge(
            array_keys($dataA['blocks'] ?? []),
            array_keys($dataB['blocks'] ?? [])
        ));

        foreach ($allBlockTypes as $blockType) {
            $entriesA = collect($dataA['blocks'][$blockType]['entries'] ?? []);
            $entriesB = collect($dataB['blocks'][$blockType]['entries'] ?? []);

            $idsA = $entriesA->pluck('uuid')->toArray();
            $idsB = $entriesB->pluck('uuid')->toArray();

            $added = $entriesB->filter(fn ($e) => !in_array($e['uuid'], $idsA))->values()->toArray();
            $removed = $entriesA->filter(fn ($e) => !in_array($e['uuid'], $idsB))->values()->toArray();

            $modified = [];
            foreach ($entriesB as $entryB) {
                $entryA = $entriesA->firstWhere('uuid', $entryB['uuid']);
                if ($entryA && ($entryA['title'] !== $entryB['title'] || $entryA['content'] !== $entryB['content'])) {
                    $modified[] = [
                        'uuid' => $entryB['uuid'],
                        'before' => ['title' => $entryA['title'], 'content' => $entryA['content']],
                        'after' => ['title' => $entryB['title'], 'content' => $entryB['content']],
                    ];
                }
            }

            if (!empty($added) || !empty($removed) || !empty($modified)) {
                $diff[$blockType] = [
                    'added' => $added,
                    'removed' => $removed,
                    'modified' => $modified,
                ];
            }
        }

        return [
            'snapshot_a' => ['version' => $snapshotA->version, 'created_at' => $snapshotA->created_at?->toISOString()],
            'snapshot_b' => ['version' => $snapshotB->version, 'created_at' => $snapshotB->created_at?->toISOString()],
            'changes' => $diff,
            'has_changes' => !empty($diff),
        ];
    }

    /**
     * Export canvas as structured data.
     */
    public function exportCanvas(PlannerProjectCanvas $canvas): array
    {
        $canvasData = $canvas->toCanvasArray();
        $blockTypes = config('planner.canvas_block_types', []);

        $sections = [];
        foreach ($blockTypes as $type => $definition) {
            $block = $canvasData['blocks'][$type] ?? null;
            $sections[] = [
                'block_type' => $type,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'entries' => $block ? $block['entries'] : [],
                'entry_count' => $block ? count($block['entries']) : 0,
            ];
        }

        return [
            'canvas' => $canvasData['canvas'],
            'sections' => $sections,
            'summary' => [
                'total_entries' => array_sum(array_column($sections, 'entry_count')),
                'filled_blocks' => count(array_filter($sections, fn ($s) => $s['entry_count'] > 0)),
                'total_blocks' => count($sections),
            ],
        ];
    }
}
