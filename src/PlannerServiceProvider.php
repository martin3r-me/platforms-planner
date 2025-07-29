<?php

namespace Platform\Planner;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate; // <--- Wichtig!

// Importiere Models und Policies für das Modul
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Policies\PlannerTaskPolicy;
use Platform\Planner\Policies\PlannerProjectPolicy;

class PlannerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Config publishen, damit sie in der Haupt-App überschrieben werden kann
        $this->publishes([
            __DIR__.'/../config/planner.php' => config_path('planner.php'),
        ], 'config');

        // Config mergen (App kann überschreiben)
        $this->mergeConfigFrom(__DIR__.'/../config/planner.php', 'planner');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Modul registrieren (zieht alles aus Config)
        PlatformCore::registerModule([
            'key' => 'planner',
            'title' => 'Planner',
            'routing' => config('planner.routing'),
            'guard' => config('planner.guard'),
            'navigation' => config('planner.navigation'),
            'sidebar' => config('planner.sidebar'),
            'billables' => config('planner.billables'),
        ]);

        // Views und Livewire-Komponenten laden
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'planner');
        $this->registerLivewireComponents();

        // ==== Policy für PlannerTask registrieren ====
        Gate::policy(PlannerTask::class, PlannerTaskPolicy::class);
        Gate::policy(PlannerProject::class, PlannerProjectPolicy::class);

        // Modul-Routen
        // Öffentliche Seiten (guest.php)
        ModuleRouter::group('planner', function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
        }, requireAuth: false);

        // Geschützte Seiten (web.php)
        ModuleRouter::group('planner', function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    public function register(): void
    {
        //
    }

    protected function registerLivewireComponents(): void
    {
        $componentPath = __DIR__ . '/Livewire';
        $namespace = 'Platform\\Planner\\Livewire';
        $prefix = 'planner'; // Modul-Präfix

        if (!is_dir($componentPath)) {
            return;
        }

        foreach (scandir($componentPath) as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $class = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($class)) {
                $alias = $prefix . '.' . Str::kebab(pathinfo($file, PATHINFO_FILENAME));
                Livewire::component($alias, $class);
            }
        }
    }
}