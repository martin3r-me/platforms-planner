<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\Filesystem;

class AiBuildManifests extends Command
{
    protected $signature = 'planner:ai:build-manifests {--force : Overwrite existing manifests}';
    protected $description = 'Build AI capability and entity manifests for the Planner module';

    public function handle(): int
    {
        $this->info('Planner AI manifest build started');

        $fs = new Filesystem();
        $baseDir = storage_path('app/ai');
        $moduleDir = $baseDir . '/entities';
        if (!is_dir($moduleDir)) {
            $fs->makeDirectory($moduleDir, 0755, true);
        }

        // Discover models in Planner module
        $modelsPath = __DIR__ . '/../../Models';
        $baseNs = 'Platform\\Planner\\Models\\';
        $entities = [];

        if (is_dir($modelsPath)) {
            foreach (scandir($modelsPath) as $file) {
                if (!str_ends_with($file, '.php')) { continue; }
                $class = $baseNs . pathinfo($file, PATHINFO_FILENAME);
                if (!class_exists($class)) { continue; }

                try {
                    $model = new $class();
                    if (!method_exists($model, 'getTable')) { continue; }
                    $table = $model->getTable();
                    if (!Schema::hasTable($table)) { continue; }

                    // Derive entity key from table name: planner_tasks -> planner.task
                    $moduleKey = 'planner';
                    $entityKey = trim(str_replace($moduleKey . '_', '', $table));
                    if ($entityKey === $table) {
                        // fallback: use class short name kebab
                        $entityKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', class_basename($class)));
                    }
                    $fullEntityKey = $moduleKey . '.' . $entityKey;

                    // Columns and fillable
                    $columns = Schema::getColumnListing($table);
                    $fillable = property_exists($model, 'fillable') ? $model->getFillable() : [];
                    $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];
                    $usesSoftDeletes = in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive($model), true);

                    $manifest = [
                        'module' => $moduleKey,
                        'entity' => $fullEntityKey,
                        'model' => $class,
                        'table' => $table,
                        'team_scoped' => in_array('team_id', $columns, true),
                        'user_scoped' => in_array('user_id', $columns, true),
                        'soft_deletes' => $usesSoftDeletes,
                        'fields' => $this->buildFields($columns, $casts, $fillable),
                        'relations' => [],
                        'operations' => ['read','write'],
                        'defaults' => [
                            'sort' => $this->suggestSort($columns),
                            'filters' => $this->suggestFilters($columns),
                        ],
                        'write_schemas' => $this->suggestWriteSchemas($fullEntityKey, $columns),
                        'built_at' => now()->toISOString(),
                        'schema_version' => '1.0.0',
                    ];

                    $entityFile = $moduleDir . '/'. str_replace('.', '-', $fullEntityKey) . '.json';
                    if (!file_exists($entityFile) || $this->option('force')) {
                        $fs->put($entityFile, json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                        $this->line(" - wrote entity manifest: " . $entityFile);
                    } else {
                        $this->line(" - skipped existing: " . $entityFile);
                    }

                    $entities[] = [
                        'key' => $fullEntityKey,
                        'model' => $class,
                        'operations' => ['describe','list','get','search'],
                    ];
                } catch (\Throwable $e) {
                    $this->warn('Skipping model '.$class.': '.$e->getMessage());
                    continue;
                }
            }
        }

        // Capability index for this module
        $index = [
            'module' => 'planner',
            'built_at' => now()->toISOString(),
            'entities' => $entities,
            'schema_version' => '1.0.0',
        ];

        $indexFile = $baseDir . '/manifests/planner.index.json';
        $fs->ensureDirectoryExists(dirname($indexFile));
        $fs->put($indexFile, json_encode($index, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $this->info('Planner capability index written: '.$indexFile);

        $this->info('Planner AI manifest build finished');
        return self::SUCCESS;
    }

    private function buildFields(array $columns, array $casts, array $fillable): array
    {
        $fields = [];
        foreach ($columns as $col) {
            $fields[$col] = [
                'type' => $this->guessType($col, $casts[$col] ?? null),
                'fillable' => in_array($col, $fillable, true),
                'pii' => in_array($col, ['email','password'], true),
                'readonly' => in_array($col, ['id','created_at','updated_at','deleted_at'], true),
            ];
        }
        return $fields;
    }

    private function guessType(string $column, ?string $cast): string
    {
        if ($cast) { return $cast; }
        if (str_contains($column, 'date')) { return 'date'; }
        if (str_contains($column, '_at')) { return 'datetime'; }
        if (str_ends_with($column, '_id')) { return 'integer'; }
        return 'string';
    }

    private function suggestSort(array $columns): array
    {
        if (in_array('due_date', $columns, true)) {
            return [ ['field' => 'due_date', 'dir' => 'asc'], ['field' => 'created_at', 'dir' => 'desc'] ];
        }
        return [ ['field' => 'created_at', 'dir' => 'desc'] ];
    }

    private function suggestFilters(array $columns): array
    {
        $filters = [];
        if (in_array('is_done', $columns, true)) {
            $filters[] = ['field' => 'is_done', 'op' => 'eq', 'value' => false];
        }
        return $filters;
    }

    private function suggestWriteSchemas(string $entityKey, array $columns): array
    {
        // Minimal MVP for planner.tasks
        if ($entityKey === 'planner.tasks' || $entityKey === 'planner.task' || str_contains($entityKey, 'tasks')) {
            return [
                'create' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'is_frog' => ['type' => 'boolean'],
                    ],
                    'required' => ['title']
                ],
                'update' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'is_done' => ['type' => 'boolean']
                    ],
                    'required' => []
                ],
            ];
        }
        return [];
    }
}
