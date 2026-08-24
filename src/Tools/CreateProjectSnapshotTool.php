<?php

namespace Platform\Planner\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ProjectSnapshotService;

/**
 * POST erzwingt einen neuen Snapshot eines Projekts (manueller Trigger).
 * Ausgelagert aus planner.project_snapshots.GET, damit GET rein lesend bleibt
 * (fresh=true dort war ein Schreib-Nebeneffekt auf einem als GET benannten Tool).
 */
class CreateProjectSnapshotTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.project_snapshots.POST';
    }

    public function getDescription(): string
    {
        return 'POST /project-snapshots - Erzwingt die Neuberechnung des Snapshots eines Projekts (Trigger=manual) und gibt ihn zurueck. Schreibender Vorgang: legt fuer den heutigen Tag einen Snapshot an bzw. ueberschreibt den bestehenden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Projekt-ID (ERFORDERLICH). Nutze planner.projects.GET um Projekte zu finden.',
                ],
            ],
            'required' => ['project_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['project_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'project_id ist erforderlich.');
            }

            $project = PlannerProject::find((int) $arguments['project_id']);
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('view', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Lesezugriff auf das Projekt.');
            }

            $snapshot = app(ProjectSnapshotService::class)->snapshot($project, 'manual');
            $snapshot->load(['slots', 'frogs', 'people']);

            return ToolResult::success([
                'project_id' => $project->id,
                'project_title' => $project->title,
                'snapshot' => GetProjectSnapshotTool::serializeSnapshot($snapshot),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['planner', 'project', 'snapshot', 'health', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true, // max 1 Snapshot/Tag/Projekt — ueberschreibt deterministisch
        ];
    }
}
