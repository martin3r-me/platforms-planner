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

        // Model-Schemata automatisch registrieren lassen
        (new \Platform\Core\Services\ModelAutoRegistrar())->scanAndRegister();
        
        // Fallback: Falls Auto-Registrar nicht funktioniert, manuell registrieren
        if (!\Platform\Core\Schema\ModelSchemaRegistry::get('planner.projects')) {
            \Platform\Core\Schema\ModelSchemaRegistry::register('planner.projects', [
                'fields' => ['id','uuid','name','team_id'],
                'filterable' => ['id','uuid','name'],
                'sortable' => ['id','name'],
                'selectable' => ['id','uuid','name'],
                'relations' => [],
                'required' => ['name'],
                'writable' => ['name'],
                'meta' => [
                    'eloquent' => \Platform\Planner\Models\PlannerProject::class,
                    'show_route' => 'planner.projects.show',
                    'route_param' => 'plannerProject',
                    'label_key' => 'name',
                ],
            ]);
        }
        
        if (!\Platform\Core\Schema\ModelSchemaRegistry::get('planner.tasks')) {
            \Platform\Core\Schema\ModelSchemaRegistry::register('planner.tasks', [
                'fields' => ['id','uuid','title','description','due_date','status','is_done','is_frog','story_points','order','sprint_slot_order','project_id','sprint_slot_id','task_group_id','user_id','user_in_charge_id'],
                'filterable' => ['project_id','user_id','user_in_charge_id','status','is_done','due_date'],
                'sortable' => ['id','due_date','title','story_points'],
                'selectable' => ['id','uuid','title','due_date','is_done','story_points','project_id','sprint_slot_id','status','user_in_charge_id'],
                'relations' => [ 'project' => ['fields' => ['id','name']] ],
                'required' => ['title'],
                'writable' => ['title','description','due_date','status','is_done','is_frog','story_points','project_id','sprint_slot_id','task_group_id','user_in_charge_id'],
                'foreign_keys' => [
                    'project_id' => ['references' => 'planner.projects', 'field' => 'id', 'label_key' => 'name'],
                    'sprint_slot_id' => ['references' => 'planner.sprint_slots', 'field' => 'id', 'label_key' => 'name'],
                ],
                'meta' => [
                    'eloquent' => \Platform\Planner\Models\PlannerTask::class,
                    'show_route' => 'planner.tasks.show',
                    'route_param' => 'plannerTask',
                    'label_key' => 'title',
                ],
            ]);
        }
        
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
        \Log::info('Planner ServiceProvider: Commands registrieren...');
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
}