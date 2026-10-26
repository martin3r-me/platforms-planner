<?php

namespace Platform\Planner\Tools\Logbook\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;

trait ResolvesLogbookProject
{
    /** @return PlannerProject|ToolResult Projekt oder fertige Fehlerantwort */
    protected function resolveProject(array $arguments, ToolContext $context, string $ability): PlannerProject|ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
        }

        $projectId = (int) ($arguments['project_id'] ?? 0);
        if ($projectId <= 0) {
            return ToolResult::error('VALIDATION_ERROR', 'project_id ist erforderlich. Nutze "planner.projects.GET" um Projekte zu finden.');
        }

        $project = PlannerProject::withStale()->find($projectId);
        if (! $project) {
            return ToolResult::error('PROJECT_NOT_FOUND', "Projekt {$projectId} nicht gefunden.");
        }

        try {
            Gate::forUser($context->user)->authorize($ability, $project);
        } catch (AuthorizationException) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Projekt (Policy).');
        }

        return $project;
    }
}
