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
        return 'POST /planner/tasks/bulk - Erstellt mehrere Tasks in einem Request. Nützlich für Batch-Anlage (z.B. 15 Aufgaben für ein Projekt) ohne viele Toolcalls. Optional mit atomic=true in Transaktion.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Creates in einer DB-Transaktion ausgeführt (bei einem Fehler wird alles zurückgerollt). Standard: false.',
                ],
                'defaults' => [
                    'type' => 'object',
                    'description' => 'Optional: Default-Werte, die auf jedes Item angewendet werden (können pro Item überschrieben werden).',
                    'properties' => [
                        'project_id' => ['type' => 'integer'],
                        'project_slot_id' => ['type' => 'integer'],
                        'user_in_charge_id' => ['type' => 'integer'],
                        'planned_minutes' => ['type' => 'integer'],
                        'due_date' => ['type' => 'string'],
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
                            'dod' => ['type' => 'string'],
                            'due_date' => ['type' => 'string'],
                            'project_id' => ['type' => 'integer'],
                            'project_slot_id' => ['type' => 'integer'],
                            'user_in_charge_id' => ['type' => 'integer'],
                            'planned_minutes' => ['type' => 'integer'],
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

            $atomic = (bool)($arguments['atomic'] ?? false);
            $singleTool = new CreateTaskTool();

            $run = function() use ($tasks, $defaults, $singleTool, $context) {
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

            $payload = $atomic ? DB::transaction(fn() => $run()) : $run();

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


