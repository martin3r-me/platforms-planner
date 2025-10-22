<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

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

                    // Derive entity key from table name: planner_tasks -> planner.task(s)
                    $moduleKey = 'planner';
                    $entityKey = trim(str_replace($moduleKey . '_', '', $table));
                    if ($entityKey === $table) {
                        $entityKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', class_basename($class)));
                    }
                    $fullEntityKey = $moduleKey . '.' . $entityKey;

                    // Columns and fillable
                    $columns = Schema::getColumnListing($table);
                    $columnMeta = $this->describeColumns($table);
                    $fillable = property_exists($model, 'fillable') ? $model->getFillable() : [];
                    $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];
                    $usesSoftDeletes = in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive($model), true);
                    $foreignKeys = $this->getForeignKeys($table);
                    $relations = $this->detectRelations($class);

                    $manifest = [
                        'module' => $moduleKey,
                        'entity' => $fullEntityKey,
                        'model' => $class,
                        'table' => $table,
                        'team_scoped' => in_array('team_id', $columns, true),
                        'user_scoped' => in_array('user_id', $columns, true),
                        'soft_deletes' => $usesSoftDeletes,
                        'fields' => $this->buildFields($columns, $casts, $fillable, $columnMeta),
                        'relations' => $this->buildRelations($relations, $foreignKeys),
                        'operations' => ['read','write'],
                        'defaults' => [
                            'sort' => $this->suggestSort($columns),
                            'filters' => $this->suggestFilters($columns),
                        ],
                        'write_schemas' => $this->suggestWriteSchemasDynamic($fullEntityKey, $columns, $columnMeta),
                        'built_at' => now()->toISOString(),
                        'schema_version' => '1.1.0',
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
                        'operations' => ['describe','list','get','search','create','update','delete'],
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
            'schema_version' => '1.1.0',
        ];

        $indexFile = $baseDir . '/manifests/planner.index.json';
        $fs->ensureDirectoryExists(dirname($indexFile));
        $fs->put($indexFile, json_encode($index, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $this->info('Planner capability index written: '.$indexFile);

        $this->info('Planner AI manifest build finished');
        return self::SUCCESS;
    }

    private function buildFields(array $columns, array $casts, array $fillable, array $columnMeta): array
    {
        $fields = [];
        foreach ($columns as $col) {
            $meta = $columnMeta[$col] ?? [];
            $fields[$col] = [
                'type' => $this->guessType($col, $casts[$col] ?? ($meta['data_type'] ?? null)),
                'nullable' => (bool)($meta['nullable'] ?? true),
                'default' => $meta['default'] ?? null,
                'fillable' => in_array($col, $fillable, true),
                'pii' => in_array($col, ['email','password'], true),
                'readonly' => in_array($col, ['id','created_at','updated_at','deleted_at'], true),
            ];
        }
        return $fields;
    }

    private function buildRelations(array $relations, array $foreignKeys): array
    {
        $out = [];
        foreach ($relations as $rel) { $out[] = $rel; }
        foreach ($foreignKeys as $fk) {
            $out[] = [
                'name' => $fk['column'],
                'type' => 'belongsTo',
                'target_table' => $fk['referenced_table'],
                'target_column' => $fk['referenced_column'],
            ];
        }
        return $out;
    }

    private function guessType(string $column, ?string $cast): string
    {
        if ($cast) { return (string)$cast; }
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

    private function suggestWriteSchemasDynamic(string $entityKey, array $columns, array $columnMeta): array
    {
        // Required = non-nullable without default (excluding PK/timestamps)
        $required = [];
        foreach ($columns as $c) {
            if (in_array($c, ['id','created_at','updated_at','deleted_at'])) { continue; }
            $meta = $columnMeta[$c] ?? [];
            $nullable = (bool)($meta['nullable'] ?? true);
            $hasDefault = array_key_exists('default', $meta) && $meta['default'] !== null;
            if (!$nullable && !$hasDefault) { $required[] = $c; }
        }
        // For tasks, limit to sensible fields
        if (str_contains($entityKey, 'tasks')) {
            $required = array_values(array_intersect($required, ['title']));
        }
        $createProps = [];
        foreach ($columns as $c) {
            $T = strtolower((string)($columnMeta[$c]['data_type'] ?? 'string'));
            $type = in_array($T, ['int','integer','bigint','smallint']) ? 'integer' : (in_array($T, ['bool','boolean']) ? 'boolean' : 'string');
            $createProps[$c] = ['type' => $type];
        }
        $updateProps = $createProps;
        return [
            'create' => [ 'type' => 'object', 'properties' => $createProps, 'required' => $required ],
            'update' => [ 'type' => 'object', 'properties' => $updateProps, 'required' => [] ],
        ];
    }

    private function describeColumns(string $table): array
    {
        $db = DB::getDatabaseName();
        $rows = DB::select('SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$db, $table]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r->COLUMN_NAME] = [
                'nullable' => ($r->IS_NULLABLE === 'YES'),
                'default' => $r->COLUMN_DEFAULT,
                'data_type' => $r->DATA_TYPE,
            ];
        }
        return $out;
    }

    private function getForeignKeys(string $table): array
    {
        $db = DB::getDatabaseName();
        $rows = DB::select('SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$db, $table]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'column' => $r->COLUMN_NAME,
                'referenced_table' => $r->REFERENCED_TABLE_NAME,
                'referenced_column' => $r->REFERENCED_COLUMN_NAME,
            ];
        }
        return $out;
    }

    private function detectRelations(string $modelClass): array
    {
        $out = [];
        try {
            $model = new $modelClass();
            $ref = new ReflectionClass($modelClass);
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->isStatic() || $m->getNumberOfParameters() > 0) { continue; }
                if ($m->class !== $modelClass) { continue; }
                try {
                    $res = $m->invoke($model);
                    if ($res instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        $type = class_basename($res);
                        $target = get_class($res->getRelated());
                        $name = $m->getName();
                        $fk = method_exists($res, 'getForeignKeyName') ? $res->getForeignKeyName() : null;
                        $out[] = [ 'name' => $name, 'type' => $type, 'target' => $target, 'foreign_key' => $fk ];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        } catch (\Throwable $e) {}
        return $out;
    }
}
