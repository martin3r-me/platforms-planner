<?php

namespace Platform\Planner\Services\Analysis;

use Platform\Planner\Models\PlannerProjectCanvas;

class BasicAnalyzer implements CanvasAnalysisStrategyInterface
{
    public function analyze(PlannerProjectCanvas $canvas, array $config = []): array
    {
        $totalBlocks = $canvas->blocks->count();
        $filledBlocks = 0;
        $totalEntries = 0;

        foreach ($canvas->blocks as $block) {
            $entryCount = $block->entries->count();
            $totalEntries += $entryCount;
            if ($entryCount > 0) {
                $filledBlocks++;
            }
        }

        $completeness = $totalBlocks > 0 ? round(($filledBlocks / $totalBlocks) * 100, 1) : 0;

        return [
            'strategy' => 'basic',
            'canvas_id' => $canvas->id,
            'canvas_name' => $canvas->name,
            'filled_blocks' => $filledBlocks,
            'total_blocks' => $totalBlocks,
            'total_entries' => $totalEntries,
            'completeness_percent' => $completeness,
        ];
    }
}
