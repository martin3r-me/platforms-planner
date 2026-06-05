<?php

namespace Platform\Planner\Services;

use Platform\Planner\Models\PlannerProjectCanvasBlock;
use Platform\Planner\Models\PlannerProjectCanvasEntry;

class ProjectCanvasEntryService
{
    /**
     * Create a single entry in a building block.
     */
    public function createEntry(PlannerProjectCanvasBlock $block, array $data): PlannerProjectCanvasEntry
    {
        if (!isset($data['position'])) {
            $data['position'] = ($block->entries()->max('position') ?? 0) + 1;
        }

        return $block->entries()->create($data);
    }

    /**
     * Bulk create entries in a building block.
     *
     * @return array<PlannerProjectCanvasEntry>
     */
    public function bulkCreateEntries(PlannerProjectCanvasBlock $block, array $entriesData, int $userId): array
    {
        $maxPosition = $block->entries()->max('position') ?? 0;
        $created = [];

        foreach ($entriesData as $data) {
            $maxPosition++;
            $created[] = $block->entries()->create([
                'title' => $data['title'],
                'content' => $data['content'] ?? null,
                'entry_type' => $data['entry_type'] ?? 'text',
                'position' => $data['position'] ?? $maxPosition,
                'metadata' => $data['metadata'] ?? null,
                'created_by_user_id' => $userId,
            ]);
        }

        return $created;
    }

    /**
     * Reorder entries within a building block.
     *
     * @param array<int> $entryIds Ordered list of entry IDs
     */
    public function reorderEntries(PlannerProjectCanvasBlock $block, array $entryIds): void
    {
        foreach ($entryIds as $position => $entryId) {
            $block->entries()
                ->where('id', $entryId)
                ->update(['position' => $position + 1]);
        }
    }
}
