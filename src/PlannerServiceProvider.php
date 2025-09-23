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

        // Kommandos (MVP) registrieren
        CommandRegistry::register('planner', [
            [
                'key' => 'planner.open_dashboard',
                'description' => 'Öffnet das Planner-Dashboard.',
                'parameters' => [],
                'impact' => 'low',
                'phrases' => [
                    'öffne planner',
                    'planner öffnen',
                    'zeige planner dashboard',
                ],
                'slots' => [],
                'guard' => 'web',
                'handler' => ['route', 'planner.dashboard'],
            ],
            [
                'key' => 'planner.create_project_form',
                'description' => 'Öffnet das Formular zum Anlegen eines Projekts.',
                'parameters' => [
                    ['name' => 'name', 'type' => 'string', 'required' => false, 'description' => 'Projektname'],
                ],
                'impact' => 'low',
                'phrases' => [
                    'lege projekt {name} an',
                    'erstelle projekt {name}',
                    'projekt {name} anlegen'
                ],
                'slots' => [ ['name' => 'name'] ],
                'guard' => 'web',
                // MVP: Navigation zum Formular; spätere Version callt Service und legt direkt an
                'handler' => ['route', 'planner.projects.create'],
            ],
            [
                'key' => 'planner.list_my_tasks',
                'description' => 'Listet Aufgaben des aktuellen Nutzers (Top 20).',
                'parameters' => [],
                'impact' => 'low',
                'confirmRequired' => false,
                'autoAllowed' => true,
                'phrases' => [
                    'meine aufgaben',
                    'zeige meine aufgaben',
                    'was habe ich offen',
                ],
                'slots' => [],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerTaskService::class.'@listMyTasks'],
            ],
            [
                'key' => 'planner.list_projects',
                'description' => 'Listet Projekte (Top 50, alphabetisch).',
                'parameters' => [],
                'impact' => 'low',
                'confirmRequired' => false,
                'autoAllowed' => true,
                'phrases' => [ 'zeige projekte', 'liste projekte', 'projekte anzeigen' ],
                'slots' => [],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerProjectService::class.'@listProjects'],
            ],
            [
                'key' => 'planner.list_project_tasks',
                'description' => 'Listet Aufgaben eines Projekts (Top 50).',
                'parameters' => [
                    ['name' => 'project_id', 'type' => 'integer', 'required' => false, 'description' => 'Projekt-ID'],
                    ['name' => 'project_uuid', 'type' => 'string', 'required' => false, 'description' => 'Projekt-UUID'],
                    ['name' => 'project_name', 'type' => 'string', 'required' => false, 'description' => 'Projektname (unscharf)'],
                ],
                'impact' => 'low',
                'confirmRequired' => false,
                'autoAllowed' => true,
                'phrases' => [
                    'zeige aufgaben in projekt {project_name}',
                    'liste aufgaben projekt {project_name}',
                    'aufgaben im projekt {project_name}',
                ],
                'slots' => [ ['name' => 'project_id'], ['name' => 'project_uuid'], ['name' => 'project_name'] ],
                'guard' => 'web',
                'handler' => ['service', \Platform\Planner\Services\PlannerTaskService::class.'@listProjectTasks'],
            ],
            [
                'key' => 'planner.open_project',
                'description' => 'Öffnet ein Projekt per ID oder UUID.',
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => false, 'description' => 'Projekt-ID'],
                    ['name' => 'uuid', 'type' => 'string', 'required' => false, 'description' => 'Projekt-UUID'],
                    ['name' => 'name', 'type' => 'string', 'required' => false, 'description' => 'Projektname (unscharf)'],
                ],
                'impact' => 'low',
                'phrases' => [
                    'öffne projekt {id}',
                    'projekt {id} öffnen',
                    'wechsle in projekt {name}',
                    'öffne projekt {name}',
                    'gehe zum projekt {name}',
                ],
                'slots' => [ ['name' => 'id'], ['name' => 'name'] ],
                'guard' => 'web',
                // Reine Navigation: direkt zur Show-Route; Parameter heißt 'plannerProject'
                'handler' => ['route', 'planner.projects.show', ['plannerProject' => 'id']],
            ],
            [
                'key' => 'planner.open_task',
                'description' => 'Öffnet eine Aufgabe per ID oder UUID.',
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => false, 'description' => 'Task-ID'],
                    ['name' => 'uuid', 'type' => 'string', 'required' => false, 'description' => 'Task-UUID'],
                    ['name' => 'title', 'type' => 'string', 'required' => false, 'description' => 'Task-Titel (unscharf)'],
                ],
                'impact' => 'low',
                'confirmRequired' => false,
                'autoAllowed' => true,
                'phrases' => [
                    'öffne aufgabe {id}',
                    'aufgabe {id} öffnen',
                    'öffne task {id}',
                ],
                'slots' => [ ['name' => 'id'], ['name' => 'uuid'], ['name' => 'title'] ],
                'guard' => 'web',
                // Reine Navigation: direkt zur Task-Show-Route; Parameter heißt 'plannerTask'
                'handler' => ['route', 'planner.tasks.show', ['plannerTask' => 'id']],
            ],
        ]);
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