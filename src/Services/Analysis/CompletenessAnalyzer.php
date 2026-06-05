<?php

namespace Platform\Planner\Services\Analysis;

use Platform\Planner\Models\PlannerProjectCanvas;

class CompletenessAnalyzer implements CanvasAnalysisStrategyInterface
{
    public function analyze(PlannerProjectCanvas $canvas, array $config = []): array
    {
        $blockDefs = config('planner.canvas_block_types', []);
        $totalBlocks = count($blockDefs);
        $filledBlocks = 0;
        $totalEntries = 0;
        $blockStats = [];

        foreach ($canvas->blocks as $block) {
            $entryCount = $block->entries->count();
            $totalEntries += $entryCount;

            if ($entryCount > 0) {
                $filledBlocks++;
            }

            $blockStats[$block->block_type] = [
                'label' => $block->label,
                'entry_count' => $entryCount,
                'is_filled' => $entryCount > 0,
                'guiding_questions' => $block->getGuidingQuestions(),
                'guiding_questions_count' => count($block->getGuidingQuestions()),
            ];
        }

        $completeness = $totalBlocks > 0 ? round(($filledBlocks / $totalBlocks) * 100, 1) : 0;

        $thresholds = $config['thresholds'] ?? ['good' => 80, 'partial' => 50, 'minimal' => 1];
        $health = match (true) {
            $completeness >= $thresholds['good'] => 'good',
            $completeness >= $thresholds['partial'] => 'partial',
            $completeness > 0 => 'minimal',
            default => 'empty',
        };

        $missingBlocks = [];
        foreach ($blockDefs as $key => $def) {
            if (!isset($blockStats[$key]) || !$blockStats[$key]['is_filled']) {
                $missingBlocks[] = [
                    'block_key' => $key,
                    'label' => $def['label'],
                    'guiding_questions' => $def['guiding_questions'] ?? [],
                ];
            }
        }

        $recommendations = $this->generateRecommendations($blockStats, $missingBlocks);

        return [
            'strategy' => 'completeness',
            'canvas_id' => $canvas->id,
            'canvas_name' => $canvas->name,
            'completeness_percent' => $completeness,
            'health' => $health,
            'filled_blocks' => $filledBlocks,
            'total_blocks' => $totalBlocks,
            'total_entries' => $totalEntries,
            'block_stats' => $blockStats,
            'missing_blocks' => $missingBlocks,
            'recommendations' => $recommendations,
        ];
    }

    private function generateRecommendations(array $blockStats, array $missingBlocks): array
    {
        $recommendations = [];

        if (!empty($missingBlocks)) {
            $labels = array_column($missingBlocks, 'label');
            $recommendations[] = 'Noch nicht ausgefuellt: ' . implode(', ', $labels) . '.';
        }

        foreach ($blockStats as $type => $stats) {
            if ($stats['entry_count'] === 1) {
                $recommendations[] = "'{$stats['label']}' hat nur 1 Eintrag - mehr Details wuerden das Canvas verbessern.";
            }
        }

        return $recommendations;
    }
}
