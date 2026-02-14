<?php

namespace Platform\Planner\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Update: mehrere Tasks in einem Call aktualisieren.
 *
 * Sinn: reduziert Toolcalls/Iterationen (LLM kann 10+ Updates in einem Schritt erledigen).
 * REST-Idee: PUT /tasks/bulk
 */
class BulkUpdateTasksTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.tasks.bulk.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /planner/tasks/bulk - Aktualisiert mehrere Tasks in einem Request. Nützlich für Batch-Operationen (z.B. mehrere Tasks abschließen/verschieben/story_points setzen) ohne viele Toolcalls.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Updates in einer DB-Transaktion ausgeführt (bei einem Fehler wird alles zurückgerollt, keine Teil-Updates durchgeführt). Standard: true.',
                ],
                'updates' => [
                    'type' => 'array',
                    'description' => 'Liste von Updates. Jedes Element entspricht den Parametern von planner.tasks.PUT.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'task_id' => ['type' => 'integer'],
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'dod' => ['type' => 'string', 'description' => 'DoD als JSON-String oder Plaintext (überschreibt alle)'],
                            'dod_items' => [
                                'type' => 'array',
                                'description' => 'DoD als Array von {text, checked} Items (überschreibt alle)',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'text' => ['type' => 'string'],
                                        'checked' => ['type' => 'boolean']
                                    ],
                                    'required' => ['text']
                                ]
                            ],
                            'dod_items_update' => [
                                'type' => 'object',
                                'description' => 'Granulare DoD-Updates (toggle, set_checked, add, remove, update_text)',
                                'properties' => [
                                    'toggle' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                    'set_checked' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'index' => ['type' => 'integer'],
                                                'checked' => ['type' => 'boolean'],
                                            ],
                                            'required' => ['index', 'checked'],
                                        ],
                                    ],
                                    'add' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'text' => ['type' => 'string'],
                                                'checked' => ['type' => 'boolean'],
                                            ],
                                            'required' => ['text'],
                                        ],
                                    ],
                                    'remove' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                    'update_text' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'index' => ['type' => 'integer'],
                                                'text' => ['type' => 'string'],
                                            ],
                                            'required' => ['index', 'text'],
                                        ],
                                    ],
                                ]
                            ],
                            'due_date' => ['type' => 'string'],
                            'project_id' => ['type' => 'integer'],
                            'project_slot_id' => ['type' => 'integer'],
                            'user_in_charge_id' => ['type' => 'integer'],
                            'planned_minutes' => ['type' => 'integer'],
                            'story_points' => [
                                'type' => 'string',
                                'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                            ],
                            'is_done' => ['type' => 'boolean'],
                        ],
                        'required' => ['task_id'],
                    ],
                ],
            ],
            'required' => ['updates'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $updates = $arguments['updates'] ?? null;
            if (!is_array($updates) || empty($updates)) {
                return ToolResult::error('INVALID_ARGUMENT', 'updates muss ein nicht-leeres Array sein.');
            }

            // atomic ist standardmäßig true (alles oder nichts), um inkonsistente Zustände zu vermeiden
            $atomic = (bool)($arguments['atomic'] ?? true);
            $singleTool = new UpdateTaskTool();

            $run = function() use ($updates, $singleTool, $context, $atomic) {
                $results = [];
                $okCount = 0;
                $failCount = 0;

                foreach ($updates as $idx => $u) {
                    if (!is_array($u)) {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => false,
                            'error' => ['code' => 'INVALID_ITEM', 'message' => 'Update-Item muss ein Objekt sein.'],
                        ];

                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Update an Index {$idx}: Update-Item muss ein Objekt sein.",
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        continue;
                    }

                    $res = $singleTool->execute($u, $context);
                    if ($res->success) {
                        $okCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => true,
                            'data' => $res->data,
                        ];
                    } else {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => false,
                            'error' => [
                                'code' => $res->errorCode,
                                'message' => $res->error,
                            ],
                        ];

                        // Bei atomic: Fehler sofort abbrechen und Transaktion zurückrollen
                        if ($atomic) {
                            $taskId = $u['task_id'] ?? $u['id'] ?? '(keine ID)';
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Update an Index {$idx} (Task-ID {$taskId}): {$res->error}",
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($updates),
                        'ok' => $okCount,
                        'failed' => $failCount,
                    ],
                ];
            };

            if ($atomic) {
                try {
                    $payload = DB::transaction(fn() => $run());
                } catch (\RuntimeException $e) {
                    $errorData = json_decode($e->getMessage(), true);
                    if (is_array($errorData) && isset($errorData['code'])) {
                        return ToolResult::error($errorData['code'], $errorData['message']);
                    }
                    throw $e; // Unbekannte RuntimeException weiterwerfen
                }
            } else {
                $payload = $run();
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Update der Tasks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'bulk',
            'tags' => ['planner', 'tasks', 'bulk', 'batch', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'medium',
            'idempotent' => false,
        ];
    }
}


