<?php

namespace Platform\Planner\Services;

use Platform\Planner\Models\PlannerProjectCanvas;

class ProjectCanvasStatusService
{
    /**
     * Assess the project status using traffic light (Ampel) logic.
     *
     * Delegates to the new AnalysisService internally while keeping
     * the same public interface for existing MCP tool consumers.
     */
    public function assessStatus(PlannerProjectCanvas $canvas): array
    {
        return (new ProjectCanvasAnalysisService())->analyze($canvas);
    }
}
