<?php

namespace Platform\Planner;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Registry\CommandRegistry;
use Platform\Core\Routing\ModuleRouter;

// Optional: Models und Policies absichern
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Policies\PlannerTaskPolicy;
use Platform\Planner\Policies\PlannerProjectPolicy;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PlannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Reserve für zukünftige Command-Registrierung
    }

    public function boot(): void
    {
        // Modul-Registrierung nur, wenn Config & Tabelle vorhanden
        if (
            config()->has('planner.routing') &&
            config()->has('planner.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'planner',
                'title'      => 'Planner',
                'routing'    => config('planner.routing'),
                'guard'      => config('planner.guard'),
                'navigation' => config('planner.navigation'),
                'sidebar'    => config('planner.sidebar'),
                'billables'  => config('planner.billables'),
            ]);
        }

        // Routen nur laden, wenn das Modul registriert wurde
        if (PlatformCore::getModule('planner')) {
            ModuleRouter::group('planner', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            ModuleRouter::group('planner', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // Config veröffentlichen & zusammenführen
        $this->publishes([
            __DIR__.'/../config/planner.php' => config_path('planner.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../config/planner.php', 'planner');

        // Migrations, Views, Livewire-Komponenten
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'planner');
        $this->registerLivewireComponents();

        // Policies nur registrieren, wenn Klassen vorhanden sind
        if (class_exists(PlannerTask::class) && class_exists(PlannerTaskPolicy::class)) {
            Gate::policy(PlannerTask::class, PlannerTaskPolicy::class);
        }

        if (class_exists(PlannerProject::class) && class_exists(PlannerProjectPolicy::class)) {
            Gate::policy(PlannerProject::class, PlannerProjectPolicy::class);
        }

        // Modelle automatisch scannen und registrieren
        $this->registerPlannerModels();
        
        // Meta-Daten präzisieren (falls Auto-Registrar funktioniert hat)
        \Platform\Core\Schema\ModelSchemaRegistry::updateMeta('planner.tasks', [
            'show_route' => 'planner.tasks.show',
            'route_param' => 'plannerTask',
        ]);
        \Platform\Core\Schema\ModelSchemaRegistry::updateMeta('planner.projects', [
            'show_route' => 'planner.projects.show',
            'route_param' => 'plannerProject',
        ]);

        // Kommandos (MVP) registrieren
        CommandRegistry::register('planner', [
            [
                'key' => 'planner.query',
                'description' => 'Generische Abfrage für Aufgaben/Projekte.',
                'parameters' => [
                    ['name' => 'model', 'type' => 'string', 'required' => true, 'description' => 'tasks|projects'],
                    ['name' => 'q', 'type' => 'string', 'required' => false],
                    ['name' => 'filters', 'type' => 'object', 'required' => false],
                    ['name' => 'sort', 'type' => 'string', 'required' => false],
                    ['name' => 'order', 'type' => 'string', 'required' => false],
                    ['name' => 'limit', 'type' => 'integer', 'required' => false],
                    ['name' => 'fields', 'type' => 'string', 'required' => false],
                ],
                'impact' => 'low',
                'confirmRequired' => false,
                'autoAllowed' => true,
                'phrases' => [
                    'suche {model} {q}',
                    'zeige {model}',
                    'übersicht {model}',
                    'meine aufgaben',
                    'übersicht aufgaben',
                    'zeige meine aufgaben',
                ],
                'slots' => [ ['name' => 'model'], ['name' => 'q'] ],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerCommandService::class.'@query'],
                'scope' => 'read:planner',
                'examples' => [
                    ['desc' => 'Meine Aufgaben', 'slots' => ['model' => 'planner.tasks']],
                    ['desc' => 'Projektübersicht', 'slots' => ['model' => 'planner.projects']],
                    ['desc' => 'Aufgaben mit Stichwort', 'slots' => ['model' => 'planner.tasks', 'q' => 'Rechnung']],
                ],
            ],
            [
                'key' => 'planner.open',
                'description' => 'Generisches Öffnen (Navigation) für Aufgaben/Projekte.',
                'parameters' => [
                    ['name' => 'model', 'type' => 'string', 'required' => true, 'description' => 'task|project'],
                    ['name' => 'id', 'type' => 'integer', 'required' => false],
                    ['name' => 'uuid', 'type' => 'string', 'required' => false],
                    ['name' => 'name', 'type' => 'string', 'required' => false],
                ],
                'impact' => 'low',
                'confirmRequired' => false,
                'autoAllowed' => true,
                'phrases' => [
                    'öffne {model} {id}',
                    'öffne {model} {name}',
                    'zeige {model} {name}',
                    'gehe zu {model} {name}',
                ],
                'slots' => [ ['name' => 'model'], ['name' => 'id'], ['name' => 'name'] ],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerCommandService::class.'@open'],
                'scope' => 'read:planner',
                'examples' => [
                    ['desc' => 'Projekt öffnen', 'slots' => ['model' => 'planner.projects', 'name' => 'Alpha']],
                    ['desc' => 'Aufgabe öffnen', 'slots' => ['model' => 'planner.tasks', 'name' => 'Login']],
                ],
            ],
            [
                'key' => 'planner.create',
                'description' => 'Generisches Anlegen (schema-validiert).',
                'parameters' => [
                    ['name' => 'model', 'type' => 'string', 'required' => true],
                    ['name' => 'data', 'type' => 'object', 'required' => true],
                ],
                'impact' => 'medium',
                'confirmRequired' => true,
                'autoAllowed' => false,
                'phrases' => [ 'erstelle {model}', 'lege {model} an' ],
                'slots' => [ ['name' => 'model'], ['name' => 'data'] ],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerCommandService::class.'@create'],
                'scope' => 'write:planner.tasks',
                'examples' => [
                    ['desc' => 'Task anlegen', 'slots' => ['model' => 'planner.tasks', 'data' => ['title' => 'Rechnung erstellen']]],
                ],
            ],
            [
                'key' => 'planner.update',
                'description' => 'Generisches Aktualisieren für Aufgaben/Projekte.',
                'parameters' => [
                    ['name' => 'model', 'type' => 'string', 'required' => true],
                    ['name' => 'id', 'type' => 'integer', 'required' => true],
                    ['name' => 'data', 'type' => 'object', 'required' => true],
                ],
                'impact' => 'medium',
                'confirmRequired' => true,
                'autoAllowed' => false,
                'phrases' => [ 'aktualisiere {model} {id}', 'bearbeite {model} {id}', 'ändere {model} {id}' ],
                'slots' => [ ['name' => 'model'], ['name' => 'id'], ['name' => 'data'] ],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerCommandService::class.'@update'],
                'scope' => 'write:planner',
                'examples' => [
                    ['desc' => 'Aufgabe bearbeiten', 'slots' => ['model' => 'planner.tasks', 'id' => 123, 'data' => ['title' => 'Neuer Titel']]],
                    ['desc' => 'Projekt bearbeiten', 'slots' => ['model' => 'planner.projects', 'id' => 456, 'data' => ['name' => 'Neuer Name']]],
                ],
            ],
            [
                'key' => 'planner.delete',
                'description' => 'Generisches Löschen für Aufgaben/Projekte.',
                'parameters' => [
                    ['name' => 'model', 'type' => 'string', 'required' => true],
                    ['name' => 'id', 'type' => 'integer', 'required' => false],
                    ['name' => 'name', 'type' => 'string', 'required' => false],
                ],
                'impact' => 'high',
                'confirmRequired' => true,
                'autoAllowed' => false,
                'phrases' => [ 'lösche {model} {id}', 'entferne {model} {name}', 'aufgabe löschen', 'projekt löschen' ],
                'slots' => [ ['name' => 'model'], ['name' => 'id'], ['name' => 'name'] ],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerCommandService::class.'@delete'],
                'scope' => 'delete:planner',
                'examples' => [
                    ['desc' => 'Aufgabe löschen', 'slots' => ['model' => 'planner.tasks', 'id' => 123]],
                    ['desc' => 'Projekt löschen', 'slots' => ['model' => 'planner.projects', 'name' => 'Alpha']],
                ],
            ],
        ]);

        // Dynamische Routen als Tools exportieren (GET, benannte Routen mit Prefix planner.)
        \Platform\Core\Services\RouteToolExporter::registerModuleRoutes('planner');
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Planner\\Livewire';
        $prefix = 'planner';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            // crm.contact.index aus crm + contact/index.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }

    protected function registerPlannerModels(): void
    {
        $baseNs = 'Platform\\Planner\\Models\\';
        $baseDir = __DIR__ . '/Models';
        if (!is_dir($baseDir)) {
            return;
        }
        foreach (scandir($baseDir) as $file) {
            if (!str_ends_with($file, '.php')) continue;
            $class = $baseNs . pathinfo($file, PATHINFO_FILENAME);
            if (!class_exists($class)) continue;
            try {
                $model = new $class();
                if (!method_exists($model, 'getTable')) continue;
                $table = $model->getTable();
                if (!\Illuminate\Support\Facades\Schema::hasTable($table)) continue;
                $moduleKey = \Illuminate\Support\Str::before($table, '_');
                $entityKey = \Illuminate\Support\Str::after($table, '_');
                if ($moduleKey !== 'planner' || $entityKey === '') continue;
                $modelKey = $moduleKey.'.'.$entityKey;
                $this->registerModel($modelKey, $class);
            } catch (\Throwable $e) {
                \Log::info('PlannerServiceProvider: Scan-Registrierung übersprungen für '.$class.': '.$e->getMessage());
                continue;
            }
        }
    }

    protected function registerModel(string $modelKey, string $eloquentClass): void
    {
        if (!class_exists($eloquentClass)) {
            \Log::info("PlannerServiceProvider: Klasse {$eloquentClass} existiert nicht");
            return;
        }

        $model = new $eloquentClass();
        $table = $model->getTable();
        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
            \Log::info("PlannerServiceProvider: Tabelle {$table} existiert nicht");
            return;
        }

        // Basis-Daten
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
        $fields = array_values($columns);
        
        // Debug: Log alle verfügbaren Felder
        \Log::info("PlannerServiceProvider: Verfügbare Felder für {$modelKey}: " . implode(', ', $fields));
        
        // Standard-Logik für alle Modelle
        $selectable = array_values(array_slice($fields, 0, 6));
        $labelKey = in_array('name', $fields, true) ? 'name' : (in_array('title', $fields, true) ? 'title' : 'id');
        
        $writable = $model->getFillable();
        
        $sortable = array_values(array_intersect($fields, ['id','name','title','created_at','updated_at']));
        $filterable = array_values(array_intersect($fields, ['id','uuid','name','title','team_id','user_id','status','is_done']));

        // Required-Felder per Doctrine DBAL
        $required = [];
        try {
            $connection = \DB::connection();
            $schemaManager = method_exists($connection, 'getDoctrineSchemaManager')
                ? $connection->getDoctrineSchemaManager()
                : ($connection->getDoctrineSchemaManager ?? null);
            if ($schemaManager) {
                $doctrineTable = $schemaManager->listTableDetails($table);
                foreach ($doctrineTable->getColumns() as $col) {
                    $name = $col->getName();
                    if ($name === 'id' || $col->getAutoincrement()) continue;
                    $notNull = !$col->getNotnull(); // Doctrine returns true for nullable
                    $hasDefault = $col->getDefault() !== null;
                    if ($notNull && !$hasDefault) {
                        $required[] = $name;
                    }
                }
                $required = array_values(array_intersect($required, $fields));
            }
        } catch (\Throwable $e) {
            $required = [];
        }

        // Relations (belongsTo) per Reflection
        $relations = [];
        $foreignKeys = [];
        try {
            $ref = new \ReflectionClass($eloquentClass);
            foreach ($ref->getMethods() as $method) {
                if (!$method->isPublic() || $method->isStatic()) continue;
                if ($method->getNumberOfParameters() > 0) continue;
                if ($method->getDeclaringClass()->getName() !== $eloquentClass) continue;
                $name = $method->getName();

                // DocComment für belongsTo-Relationen parsen
                $docComment = $method->getDocComment();
                if ($docComment && preg_match('/@return \\s*\\\\\Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo<([^>]+)>/', $docComment, $matches)) {
                    $targetClass = $matches[1];
                    if (class_exists($targetClass)) {
                        $targetModel = new $targetClass();
                        $targetTable = $targetModel->getTable();
                        $targetModuleKey = \Illuminate\Support\Str::before($targetTable, '_');
                        $targetEntityKey = \Illuminate\Support\Str::after($targetTable, '_');
                        $targetModelKey = $targetModuleKey . '.' . $targetEntityKey;

                        // Versuche, foreign_key und owner_key zu erraten
                        $fk = \Illuminate\Support\Str::snake($name) . '_id';
                        $ownerKey = 'id';

                        // Überprüfung, ob die Spalte im aktuellen Modell existiert
                        if (in_array($fk, $fields, true)) {
                            $relations[$name] = [
                                'type' => 'belongsTo',
                                'target' => $targetModelKey,
                                'foreign_key' => $fk,
                                'owner_key' => $ownerKey,
                                'fields' => ['id', \Platform\Core\Schema\ModelSchemaRegistry::meta($targetModelKey, 'label_key') ?: 'name'],
                            ];
                            $foreignKeys[$fk] = [
                                'references' => $targetModelKey,
                                'field' => $ownerKey,
                                'label_key' => \Platform\Core\Schema\ModelSchemaRegistry::meta($targetModelKey, 'label_key') ?: 'name',
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::info("PlannerServiceProvider: Fehler beim Ermitteln der Relationen für {$eloquentClass}: " . $e->getMessage());
        }

        // Enums und sprachmodell-relevante Daten
        $enums = [];
        $descriptions = [];
        try {
            $ref = new \ReflectionClass($eloquentClass);
            foreach ($ref->getProperties() as $property) {
                $docComment = $property->getDocComment();
                if ($docComment) {
                    // Enum-Definitionen finden
                    if (preg_match('/@var\s+([A-Za-z0-9\\\\]+)/', $docComment, $matches)) {
                        $type = $matches[1];
                        if (str_contains($type, 'Enum') || str_contains($type, 'Status')) {
                            $enums[$property->getName()] = $type;
                        }
                    }
                    // Beschreibungen finden
                    if (preg_match('/@description\s+(.+)/', $docComment, $matches)) {
                        $descriptions[$property->getName()] = $matches[1];
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        \Platform\Core\Schema\ModelSchemaRegistry::register($modelKey, [
            'fields' => $fields,
            'filterable' => $filterable,
            'sortable' => $sortable,
            'selectable' => $selectable,
            'relations' => $relations,
            'required' => $required,
            'writable' => $writable,
            'foreign_keys' => $foreignKeys,
            'enums' => $enums,
            'descriptions' => $descriptions,
            'meta' => [
                'eloquent' => $eloquentClass,
                'show_route' => null,
                'route_param' => null,
                'label_key' => $labelKey,
            ],
        ]);

        \Log::info("PlannerServiceProvider: Modell {$modelKey} registriert mit " . count($relations) . " Relationen und " . count($enums) . " Enums");
        \Log::info("PlannerServiceProvider: Selectable Felder für {$modelKey}: " . implode(', ', $selectable));
    }
}