<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesCanvasTeam;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteCanvasTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesCanvasTeam;

    public function getName(): string
    {
        return 'planner.canvas.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /planner/canvas/{id} - Loescht einen Project Canvas (Standard: Soft-Delete). ERFORDERLICH: canvas_id. Mit force=true wird unwiderruflich hart gelöscht (Purge) — nur für Owner/Admin des zugehörigen Projekts, erfordert confirm=true.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'canvas_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Canvas (ERFORDERLICH).',
                ],
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Hard-Delete/Purge statt Soft-Delete. Löscht den Canvas unwiderruflich (kein Restore möglich). Nur für Owner/Admin des Projekts. Erfordert zwingend confirm=true.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Bei force=true IMMER erforderlich: explizite Bestätigung der unwiderruflichen Löschung.',
                ],
            ],
            'required' => ['canvas_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $canvasId = (int) ($arguments['canvas_id'] ?? 0);
            if ($canvasId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'canvas_id ist erforderlich.');
            }

            $force = (bool) ($arguments['force'] ?? false);

            // Bei force=true auch bereits soft-gelöschte Canvases finden — genau diese sind der
            // Hauptfall für Purge.
            $query = PlannerProjectCanvas::query()->where('team_id', $teamId);
            if ($force) {
                $query->withTrashed();
            }
            $canvas = $query->find($canvasId);

            if (!$canvas) {
                return ToolResult::error('NOT_FOUND', 'Canvas nicht gefunden (oder kein Zugriff).');
            }

            if ($force) {
                // Hard-Delete/Purge: nur Owner/Admin des zugehörigen Projekts (gleiche Schwelle
                // wie planner.projects.DELETE force=true), da Canvas keine eigene Policy hat.
                $project = $canvas->project;
                if (!$project) {
                    return ToolResult::error('PROJECT_NOT_FOUND', 'Das zugehörige Projekt wurde nicht gefunden.');
                }
                try {
                    Gate::forUser($context->user)->authorize('forceDelete', $project);
                } catch (AuthorizationException $e) {
                    return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Canvas nicht unwiderruflich löschen (nur Owner/Admin des Projekts).');
                }

                if (!($arguments['confirm'] ?? false)) {
                    return ToolResult::error('CONFIRMATION_REQUIRED', 'Hard-Delete/Purge des Canvas ist UNWIDERRUFLICH und nicht wiederherstellbar (inkl. aller Blöcke/Einträge). Bitte bestätige explizit mit \'confirm: true\'.');
                }

                if (method_exists($canvas, 'logActivity')) {
                    $canvas->logActivity("Canvas hart gelöscht (Purge, force=true) durch {$context->user->name}.");
                }
                $canvas->forceDelete();

                return ToolResult::success([
                    'id' => $canvasId,
                    'force' => true,
                    'message' => 'Canvas wurde UNWIDERRUFLICH aus der Datenbank gelöscht (Purge).',
                ]);
            }

            $canvas->delete();

            return ToolResult::success([
                'id' => $canvasId,
                'force' => false,
                'message' => 'Canvas geloescht (Soft-Delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen des Canvas: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'delete'],
            'risk_level' => 'destructive',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
