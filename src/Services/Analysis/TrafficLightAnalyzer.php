<?php

namespace Platform\Planner\Services\Analysis;

use Platform\Planner\Models\PlannerProjectCanvas;

class TrafficLightAnalyzer implements CanvasAnalysisStrategyInterface
{
    public function analyze(PlannerProjectCanvas $canvas, array $config = []): array
    {
        $blockTypes = config('planner.canvas_block_types', []);
        $totalBlocks = count($blockTypes);
        $filledBlocks = 0;
        $totalEntries = 0;
        $blockStats = [];
        $warnings = [];

        $riskCount = 0;
        $overdueCount = 0;
        $riskBlock = $config['risk_block'] ?? 'risks';
        $milestoneBlock = $config['milestone_block'] ?? 'milestones';
        $criticalBlocks = $config['critical_blocks'] ?? [];

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
            ];

            if ($block->block_type === $riskBlock) {
                $riskCount = $entryCount;
            }

            if ($block->block_type === $milestoneBlock) {
                foreach ($block->entries as $entry) {
                    $meta = $entry->metadata ?? [];
                    if (isset($meta['due_date'])) {
                        try {
                            $dueDate = \Carbon\Carbon::parse($meta['due_date']);
                            if ($dueDate->isPast() && empty($meta['completed'])) {
                                $overdueCount++;
                            }
                        } catch (\Throwable $e) {
                            // Skip invalid dates
                        }
                    }
                }
            }
        }

        $completeness = $totalBlocks > 0 ? round(($filledBlocks / $totalBlocks) * 100, 1) : 0;

        if ($riskCount > 5) {
            $warnings[] = "Hohe Anzahl an Risiken ({$riskCount}). Risikominimierung pruefen.";
        }
        if ($overdueCount > 0) {
            $warnings[] = "{$overdueCount} ueberfaellige Meilenstein(e). Zeitplan pruefen.";
        }
        if ($completeness < 50) {
            $warnings[] = 'Canvas ist weniger als 50% ausgefuellt. Weitere Planung erforderlich.';
        }

        foreach ($criticalBlocks as $criticalKey) {
            if (!isset($blockStats[$criticalKey]) || !$blockStats[$criticalKey]['is_filled']) {
                $label = $blockTypes[$criticalKey]['label'] ?? $criticalKey;
                $warnings[] = "Kritischer Block '{$label}' ist leer.";
            }
        }

        $score = $this->calculateScore($completeness, $riskCount, $overdueCount, $blockStats, $criticalBlocks, $config);

        $thresholds = $config['thresholds'] ?? ['green' => 70, 'yellow' => 40];
        $color = match (true) {
            $score >= $thresholds['green'] => 'green',
            $score >= $thresholds['yellow'] => 'yellow',
            default => 'red',
        };

        return [
            'strategy' => 'traffic_light',
            'canvas_id' => $canvas->id,
            'canvas_name' => $canvas->name,
            'color' => $color,
            'score' => $score,
            'completeness_percent' => $completeness,
            'filled_blocks' => $filledBlocks,
            'total_blocks' => $totalBlocks,
            'total_entries' => $totalEntries,
            'risk_count' => $riskCount,
            'overdue_milestones' => $overdueCount,
            'warnings' => $warnings,
            'block_stats' => $blockStats,
        ];
    }

    private function calculateScore(
        float $completeness,
        int $riskCount,
        int $overdueCount,
        array $blockStats,
        array $criticalBlocks,
        array $config
    ): int {
        $weights = $config['weights'] ?? [
            'completeness' => 40,
            'critical_blocks' => 30,
            'risk_assessment' => 15,
            'milestone_health' => 15,
        ];

        $score = 0;

        $score += (int) ($completeness / 100 * $weights['completeness']);

        $filledCritical = 0;
        foreach ($criticalBlocks as $type) {
            if (isset($blockStats[$type]) && $blockStats[$type]['is_filled']) {
                $filledCritical++;
            }
        }
        $criticalCount = count($criticalBlocks);
        if ($criticalCount > 0) {
            $score += (int) (($filledCritical / $criticalCount) * $weights['critical_blocks']);
        }

        $score += match (true) {
            $riskCount === 0 => (int) ($weights['risk_assessment'] * 0.67),
            $riskCount <= 3 => $weights['risk_assessment'],
            $riskCount <= 5 => (int) ($weights['risk_assessment'] * 0.67),
            default => (int) ($weights['risk_assessment'] * 0.33),
        };

        $score += match (true) {
            $overdueCount === 0 => $weights['milestone_health'],
            $overdueCount <= 2 => (int) ($weights['milestone_health'] * 0.53),
            default => 0,
        };

        return min(100, max(0, $score));
    }
}
