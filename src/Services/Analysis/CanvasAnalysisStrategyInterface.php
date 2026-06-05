<?php

namespace Platform\Planner\Services\Analysis;

use Platform\Planner\Models\PlannerProjectCanvas;

interface CanvasAnalysisStrategyInterface
{
    public function analyze(PlannerProjectCanvas $canvas, array $config = []): array;
}
