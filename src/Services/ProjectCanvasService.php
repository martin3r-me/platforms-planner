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

    /** Das (einzige) Canvas eines Projekts anlegen — passiert automatisch beim Projekt-Anlegen. */
    public function createForProject(\Platform\Planner\Models\PlannerProject $project, ?int $userId): PlannerProjectCanvas
    {
        return $this->createCanvas([
            'project_id' => $project->id,
            'team_id' => $project->team_id,
            'name' => 'Projekt-Canvas',
            'status' => PlannerProjectCanvas::STATUS_OPEN,
            'created_by_user_id' => $userId,
        ]);
    }

    /** Das Canvas eines Projekts (eins pro Projekt; bei Altbestand mit mehreren das aelteste aktive). */
    public function forProject(\Platform\Planner\Models\PlannerProject $project): ?PlannerProjectCanvas
    {
        return PlannerProjectCanvas::forProject($project->id)
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [PlannerProjectCanvas::STATUS_DISCARDED])
            ->orderBy('id')
            ->first();
    }

    /** Canvas des Projekts holen, notfalls anlegen (Reparatur fuer Altbestand). */
    public function ensureForProject(\Platform\Planner\Models\PlannerProject $project, ?int $userId): PlannerProjectCanvas
    {
        return $this->forProject($project) ?? $this->createForProject($project, $userId);
    }

    /**
     * Stand festhalten (manuell). Delegiert an die Versionierung.
     */
    public function createSnapshot(PlannerProjectCanvas $canvas, ?int $userId, ?string $label = null, string $source = ProjectCanvasVersioning::SOURCE_MANUAL): PlannerProjectCanvasSnapshot
    {
        return app(ProjectCanvasVersioning::class)->checkpoint($canvas, $source, $userId, $label, force: true);
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
