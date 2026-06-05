<?php

namespace Platform\Planner\Services;

use Platform\Planner\Models\PlannerProjectCanvas;

class ProjectCanvasStatusService
{
    /**
     * Assess the project status using traffic light (Ampel) logic.
     *
     * Green: Project on track
     * Yellow: Some concerns
     * Red: Critical issues
     */
    public function assessStatus(PlannerProjectCanvas $canvas): array
    {
        $canvas->loadMissing(['blocks.entries']);

        $blockTypes = config('planner.canvas_block_types', []);
        $totalBlocks = count($blockTypes);
        $filledBlocks = 0;
        $totalEntries = 0;
        $blockStats = [];
        $warnings = [];

        $riskCount = 0;
        $overdueCount = 0;

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

            if ($block->block_type === 'risks') {
                $riskCount = $entryCount;
            }

            if ($block->block_type === 'milestones') {
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

        // Calculate completeness
        $completeness = $totalBlocks > 0 ? round(($filledBlocks / $totalBlocks) * 100, 1) : 0;

        // Build warnings
        if ($riskCount > 5) {
            $warnings[] = "Hohe Anzahl an Risiken ({$riskCount}). Risikominimierung pruefen.";
        }
        if ($overdueCount > 0) {
            $warnings[] = "{$overdueCount} ueberfaellige Meilenstein(e). Zeitplan pruefen.";
        }
        if ($completeness < 50) {
            $warnings[] = "Canvas ist weniger als 50% ausgefuellt. Weitere Planung erforderlich.";
        }

        // Missing critical blocks
        $criticalBlocks = ['project_goal', 'scope', 'milestones', 'risks'];
        foreach ($criticalBlocks as $criticalType) {
            if (!isset($blockStats[$criticalType]) || !$blockStats[$criticalType]['is_filled']) {
                $label = $blockTypes[$criticalType]['label'] ?? $criticalType;
                $warnings[] = "Kritischer Block '{$label}' ist leer.";
            }
        }

        // Calculate score (0-100)
        $score = $this->calculateScore($completeness, $riskCount, $overdueCount, $blockStats, $criticalBlocks);

        // Determine traffic light color
        $color = match (true) {
            $score >= 70 => 'green',
            $score >= 40 => 'yellow',
            default => 'red',
        };

        return [
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
        array $criticalBlocks
    ): int {
        $score = 0;

        // Completeness contributes up to 40 points
        $score += (int) ($completeness * 0.4);

        // Critical blocks filled contribute up to 30 points
        $filledCritical = 0;
        foreach ($criticalBlocks as $type) {
            if (isset($blockStats[$type]) && $blockStats[$type]['is_filled']) {
                $filledCritical++;
            }
        }
        $score += (int) (($filledCritical / count($criticalBlocks)) * 30);

        // Low risk count contributes up to 15 points
        $score += match (true) {
            $riskCount === 0 => 10,
            $riskCount <= 3 => 15,
            $riskCount <= 5 => 10,
            default => 5,
        };

        // No overdue milestones contributes up to 15 points
        $score += match (true) {
            $overdueCount === 0 => 15,
            $overdueCount <= 2 => 8,
            default => 0,
        };

        return min(100, max(0, $score));
    }
}
