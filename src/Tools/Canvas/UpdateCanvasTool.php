<?php

namespace Platform\Planner\Tools\Canvas;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

class UpdateCanvasTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.PUT';
    }

    public function getDescription(): string
    {
        return 'Aktualisiert einen Project Canvas. ERFORDERLICH: canvas_id. Optional: name, description, status.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                ...$this->canvasTargetSchema(),
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => PlannerProjectCanvas::STATUSES,
                    'description' => 'Optional: Neuer Status.',
                ],
            ],
            'required' => [],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'update');
            if ($error) {
                return $error;
            }

            $updated = [];
            foreach (['name', 'description', 'status'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $canvas->{$field} = $arguments[$field];
                    $updated[] = $field;
                }
            }

            if (empty($updated)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren angegeben.');
            }

            $canvas->save();

            return ToolResult::success([
                'id' => $canvas->id,
                'uuid' => $canvas->uuid,
                'name' => $canvas->name,
                'status' => $canvas->status,
                'updated_fields' => $updated,
                'message' => 'Canvas aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Canvas: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['planner', 'canvas', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
