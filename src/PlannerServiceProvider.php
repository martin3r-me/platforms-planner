<?php

namespace Platform\Planner;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
// CommandRegistry entfernt
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
        // Commands registrieren
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Planner\Console\Commands\MigrateSprintSlotsToProjectSlots::class,
            ]);
        }
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

            // Embedded Routes OHNE Modul-Routing (keine Auth-Middleware)
            Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
                ->middleware('web') // Nur web middleware, keine auth
                ->prefix('planner')
                ->group(__DIR__.'/../routes/embedded.php');
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
        
        // Embedded Komponenten manuell registrieren
        Livewire::component('planner.embedded.project', \Platform\Planner\Livewire\Embedded\Project::class);
        Livewire::component('planner.embedded.task', \Platform\Planner\Livewire\Embedded\Task::class);

        // Policies mit standardisierter Registrierung
        $this->registerPolicies();

        // Modelle-Scan & Schema-Registry-Meta entfernt (war für Agent)

        // Commands entfernt - Sidebar soll leer sein

        // RouteToolExporter entfernt - Sidebar soll leer sein
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
        
        // Debug-Logs entfernt
        
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
                if ($docComment && preg_match('/@return \s*\\\\Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo<([^>]+)>/', $docComment, $matches)) {
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
                // HasMany erkennen
                if ($docComment && preg_match('/@return \s*\\\\Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\HasMany<([^>]+)>/', $docComment, $m2)) {
                    $tClass = $m2[1];
                    if (class_exists($tClass)) {
                        $tModel = new $tClass();
                        $tTable = $tModel->getTable();
                        $tMod = \Illuminate\Support\Str::before($tTable, '_');
                        $tEnt = \Illuminate\Support\Str::after($tTable, '_');
                        $tKey = $tMod.'.'.$tEnt;
                        $relations[$name] = [ 'type' => 'hasMany', 'target' => $tKey ];
                    }
                }
                // BelongsToMany erkennen
                if ($docComment && preg_match('/@return \s*\\\\Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsToMany<([^>]+)>/', $docComment, $m3)) {
                    $tClass = $m3[1];
                    if (class_exists($tClass)) {
                        $tModel = new $tClass();
                        $tTable = $tModel->getTable();
                        $tMod = \Illuminate\Support\Str::before($tTable, '_');
                        $tEnt = \Illuminate\Support\Str::after($tTable, '_');
                        $tKey = $tMod.'.'.$tEnt;
                        $relations[$name] = [ 'type' => 'belongsToMany', 'target' => $tKey ];
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

        // Debug-Logs entfernt
    }

    /**
     * Registriert Policies für das Planner-Modul
     */
    protected function registerPolicies(): void
    {
        // Standardisierte Policy-Registrierung
        $policies = [
            PlannerTask::class => PlannerTaskPolicy::class,
            PlannerProject::class => PlannerProjectPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            if (class_exists($model) && class_exists($policy)) {
                Gate::policy($model, $policy);
            }
        }
    }
}