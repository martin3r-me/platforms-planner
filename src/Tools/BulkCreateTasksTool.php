<?php

namespace Platform\Planner\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Create: mehrere Tasks in einem Call anlegen.
 *
 * Sinn: reduziert Toolcalls/Iterationen (LLM kann 10-50 Tasks in einem Schritt erstellen).
 * REST-Idee: POST /planner/tasks/bulk
 */
class BulkCreateTasksTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.tasks.bulk.POST';
    }

    public function getDescription(): string
    {
        // NOTE: OpenAI tool descriptions are truncated to ~150 chars in OpenAiService.
        // Keep the critical contract early: body must be an object with tasks[].
        return 'POST /planner/tasks/bulk - Body MUSS {tasks:[{title,description,dod,...}], project_id?, project_slot_id?, defaults?} enthalten. Erstellt viele Tasks. project_id/project_slot_id können top-level oder in defaults oder pro Task gesetzt werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Creates in einer DB-Transaktion ausgeführt (bei einem Fehler wird alles zurückgerollt, keine Teil-Tasks angelegt). Standard: true.',
                ],
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Projekt-ID für alle Tasks (wird als Default verwendet, kann pro Task oder via defaults überschrieben werden).',
                ],
                'project_slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Slot-ID für alle Tasks (wird als Default verwendet, kann pro Task oder via defaults überschrieben werden).',
                ],
                'defaults' => [
                    'type' => 'object',
                    'description' => 'Optional: Default-Werte, die auf jedes Item angewendet werden (können pro Item überschrieben werden). Überschreibt top-level project_id/project_slot_id.',
                    'properties' => [
                        'project_id' => ['type' => 'integer'],
                        'project_slot_id' => ['type' => 'integer'],
                        'user_in_charge_id' => ['type' => 'integer'],
                        'planned_minutes' => ['type' => 'integer'],
                        'due_date' => ['type' => 'string'],
                        'story_points' => [
                            'type' => 'string',
                            'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                        ],
                    ],
                    'required' => [],
                ],
                'tasks' => [
                    'type' => 'array',
                    'description' => 'Liste von Tasks. Jedes Element entspricht den Parametern von planner.tasks.POST (mindestens title).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'dod' => ['type' => 'string', 'description' => 'DoD als JSON-String oder Plaintext'],
                            'dod_items' => [
                                'type' => 'array',
                                'description' => 'DoD als Array von {text, checked} Items',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'text' => ['type' => 'string'],
                                        'checked' => ['type' => 'boolean']
                                    ],
                                    'required' => ['text']
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
                        ],
                        'required' => ['title'],
                    ],
                ],
            ],
            'required' => ['tasks'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $tasks = $arguments['tasks'] ?? null;
            if (!is_array($tasks) || empty($tasks)) {
                return ToolResult::error('INVALID_ARGUMENT', 'tasks muss ein nicht-leeres Array sein.');
            }

            $defaults = $arguments['defaults'] ?? [];
            if (!is_array($defaults)) {
                $defaults = [];
            }

            // Top-Level project_id/project_slot_id als Basis-Defaults übernehmen
            // Priorität: Task-Item > defaults > Top-Level
            foreach (['project_id', 'project_slot_id'] as $field) {
                if (isset($arguments[$field]) && !array_key_exists($field, $defaults)) {
                    $defaults[$field] = $arguments[$field];
                }
            }

            // atomic ist standardmäßig true (alles oder nichts), um inkonsistente Zustände zu vermeiden
            $atomic = (bool)($arguments['atomic'] ?? true);
            $singleTool = new CreateTaskTool();

            $run = function() use ($tasks, $defaults, $singleTool, $context, $atomic) {
                $results = [];
                $okCount = 0;
                $failCount = 0;

                foreach ($tasks as $idx => $t) {
                    if (!is_array($t)) {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => false,
                            'error' => ['code' => 'INVALID_ITEM', 'message' => 'Task-Item muss ein Objekt sein.'],
                        ];

                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Task an Index {$idx}: Task-Item muss ein Objekt sein.",
                                'failed_index' => $idx,
                                'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        continue;
                    }

                    // Defaults anwenden, ohne explizite Werte zu überschreiben
                    $payload = $defaults;
                    foreach ($t as $k => $v) {
                        $payload[$k] = $v;
                    }

                    $res = $singleTool->execute($payload, $context);
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
                            $taskTitle = $t['title'] ?? '(kein Titel)';
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Task an Index {$idx} ('{$taskTitle}'): {$res->error}",
                                'failed_index' => $idx,
                                'error_code' => $res->errorCode,
                                'error_message' => $res->error,
                                'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($tasks),
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Create der Tasks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'bulk',
            'tags' => ['planner', 'tasks', 'bulk', 'batch', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'medium',
            'idempotent' => false,
        ];
    }
}


