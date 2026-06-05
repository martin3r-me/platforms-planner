<?php

namespace Platform\Planner\Services;

use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Services\Analysis\CanvasAnalysisStrategyInterface;
use Platform\Planner\Services\Analysis\BasicAnalyzer;
use Platform\Planner\Services\Analysis\CompletenessAnalyzer;
use Platform\Planner\Services\Analysis\TrafficLightAnalyzer;

class ProjectCanvasAnalysisService
{
    public function analyze(PlannerProjectCanvas $canvas): array
    {
        $canvas->loadMissing(['blocks.entries']);

        $analysisConfig = config('planner.canvas_analysis_config', []);
        $strategy = $analysisConfig['strategy'] ?? null;

        $analyzer = $this->resolveAnalyzer($strategy);

        return $analyzer->analyze($canvas, $analysisConfig);
    }

    protected function resolveAnalyzer(?string $strategy): CanvasAnalysisStrategyInterface
    {
        return match ($strategy) {
            'completeness' => new CompletenessAnalyzer(),
            'traffic_light' => new TrafficLightAnalyzer(),
            default => new BasicAnalyzer(),
        };
    }
}
