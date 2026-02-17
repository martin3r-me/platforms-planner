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
        return 'PUT /planner/tasks/bulk - Aktualisiert mehrere Tasks in einem Request. '
            . 'Zwei Modi: (1) task_ids + data: gleiche Änderung für mehrere Tasks '
            . '(z.B. {"task_ids": [2,3,4], "data": {"project_id": 5}}). '
            . '(2) updates: individuelle Änderungen pro Task '
            . '(z.B. {"updates": [{"task_id": 2, "title": "..."}, {"task_id": 3, "title": "..."}]}). '
            . 'Genau einer der beiden Modi muss verwendet werden.';
    }

    public function getSchema(): array
    {
        // Gemeinsame Task-Felder, die in beiden Modi verwendet werden können
        $taskFields = [
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
        ];

        return [
            'type' => 'object',
            'description' => 'Zwei Modi verfügbar (genau einer muss gewählt werden): '
                . 'Modus 1 (task_ids + data): Gleiche Änderung für mehrere Tasks. '
                . 'Beispiel: {"task_ids": [2, 3, 4], "data": {"project_id": 5, "project_slot_id": 5}}. '
                . 'Modus 2 (updates): Individuelle Änderungen pro Task. '
                . 'Beispiel: {"updates": [{"task_id": 2, "title": "Neu"}, {"task_id": 3, "is_done": true}]}.',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Updates in einer DB-Transaktion ausgeführt (bei einem Fehler wird alles zurückgerollt, keine Teil-Updates durchgeführt). Standard: true.',
                ],

                // Modus 1: task_ids + data (gleiche Änderung für mehrere Tasks)
                'task_ids' => [
                    'type' => 'array',
                    'description' => 'Modus 1: Liste von Task-IDs, die alle die gleiche Änderung erhalten. Muss zusammen mit "data" verwendet werden. Kann NICHT zusammen mit "updates" verwendet werden.',
                    'items' => ['type' => 'integer'],
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Modus 1: Die Änderungen, die auf alle task_ids angewendet werden. Entspricht den Feldern von planner.tasks.PUT (ohne task_id). Muss zusammen mit "task_ids" verwendet werden.',
                    'properties' => $taskFields,
                ],

                // Modus 2: updates (individuelle Änderungen pro Task)
                'updates' => [
                    'type' => 'array',
                    'description' => 'Modus 2: Liste von individuellen Updates. Jedes Element entspricht den Parametern von planner.tasks.PUT. Kann NICHT zusammen mit "task_ids"/"data" verwendet werden.',
                    'items' => [
                        'type' => 'object',
                        'properties' => array_merge(
                            ['task_id' => ['type' => 'integer']],
                            $taskFields
                        ),
                        'required' => ['task_id'],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $hasUpdates = isset($arguments['updates']);
            $hasTaskIds = isset($arguments['task_ids']);
            $hasData = isset($arguments['data']);

            // Validierung: Genau ein Modus muss verwendet werden
            if ($hasUpdates && ($hasTaskIds || $hasData)) {
                return ToolResult::error(
                    'INVALID_ARGUMENT',
                    'Entweder "updates" (Modus 2) ODER "task_ids" + "data" (Modus 1) verwenden, nicht beides gleichzeitig.'
                );
            }

            if ($hasTaskIds && !$hasData) {
                return ToolResult::error(
                    'INVALID_ARGUMENT',
                    '"task_ids" erfordert auch "data" mit den anzuwendenden Änderungen.'
                );
            }

            if ($hasData && !$hasTaskIds) {
                return ToolResult::error(
                    'INVALID_ARGUMENT',
                    '"data" erfordert auch "task_ids" mit den zu aktualisierenden Task-IDs.'
                );
            }

            if (!$hasUpdates && !$hasTaskIds) {
                return ToolResult::error(
                    'INVALID_ARGUMENT',
                    'Entweder "task_ids" + "data" (gleiche Änderung für mehrere Tasks) oder "updates" (individuelle Änderungen pro Task) muss angegeben werden.'
                );
            }

            // Modus 1: task_ids + data → in updates-Array umwandeln
            if ($hasTaskIds && $hasData) {
                $taskIds = $arguments['task_ids'];
                $data = $arguments['data'];

                if (!is_array($taskIds) || empty($taskIds)) {
                    return ToolResult::error('INVALID_ARGUMENT', 'task_ids muss ein nicht-leeres Array von Task-IDs sein.');
                }

                if (!is_array($data) || empty($data)) {
                    return ToolResult::error('INVALID_ARGUMENT', 'data muss ein nicht-leeres Objekt mit den anzuwendenden Änderungen sein.');
                }

                $updates = [];
                foreach ($taskIds as $taskId) {
                    if (!is_int($taskId) && !is_numeric($taskId)) {
                        return ToolResult::error('INVALID_ARGUMENT', "Ungültige Task-ID in task_ids: " . json_encode($taskId));
                    }
                    $updates[] = array_merge(['task_id' => (int)$taskId], $data);
                }
            } else {
                // Modus 2: updates-Array direkt verwenden
                $updates = $arguments['updates'] ?? null;
                if (!is_array($updates) || empty($updates)) {
                    return ToolResult::error('INVALID_ARGUMENT', 'updates muss ein nicht-leeres Array sein.');
                }
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


