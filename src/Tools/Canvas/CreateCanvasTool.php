<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;

class CreateCanvasTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvases.POST';
    }

    public function getDescription(): string
    {
        return 'POST /planner/canvases - Erstellt einen neuen Project Canvas (initialisiert automatisch 9 Building Blocks). ERFORDERLICH: project_id, name. Optional: description, status (default: draft).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Projekt-ID (ERFORDERLICH).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Canvas (ERFORDERLICH).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'active', 'archived'],
                    'description' => 'Optional: Status (draft, active, archived). Default: draft.',
                ],
            ],
            'required' => ['project_id', 'name'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $projectId = (int) ($arguments['project_id'] ?? 0);
            if ($projectId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'project_id ist erforderlich.');
            }

            $project = PlannerProject::where('team_id', $teamId)->find($projectId);
            if (!$project) {
                return ToolResult::error('NOT_FOUND', 'Projekt nicht gefunden (oder kein Zugriff).');
            }

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $canvasService = new ProjectCanvasService();
            $canvas = $canvasService->createCanvas([
                'project_id' => $projectId,
                'name' => $name,
                'description' => $arguments['description'] ?? null,
                'status' => $arguments['status'] ?? 'draft',
                'team_id' => $teamId,
                'created_by_user_id' => $context->user->id,
            ]);

            return ToolResult::success([
                'id' => $canvas->id,
                'uuid' => $canvas->uuid,
                'name' => $canvas->name,
                'status' => $canvas->status,
                'project_id' => $canvas->project_id,
                'blocks_count' => $canvas->blocks->count(),
                'team_id' => $canvas->team_id,
                'message' => 'Canvas erstellt mit 9 Building Blocks.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Canvas: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvases', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
