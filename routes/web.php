<?php

use Platform\Planner\Livewire\Dashboard;
use Platform\Planner\Livewire\MyTasks;
use Platform\Planner\Livewire\DelegatedTasks;
use Platform\Planner\Livewire\CompletedTasks;
use Platform\Planner\Livewire\FrogTasks;
use Platform\Planner\Livewire\Hygiene;
use Platform\Planner\Livewire\CreateProject;
use Platform\Planner\Livewire\Export;
use Platform\Planner\Livewire\Project;
use Platform\Planner\Livewire\Task;
use Platform\Planner\Models\PlannerProject;
use Illuminate\Http\Middleware\FrameGuard;

Route::get('/', Dashboard::class)->name('planner.dashboard');
Route::get('/my-tasks', MyTasks::class)->name('planner.my-tasks');
Route::get('/delegated-tasks', DelegatedTasks::class)->name('planner.delegated-tasks');
Route::get('/completed-tasks', CompletedTasks::class)->name('planner.completed-tasks');
Route::get('/frog-tasks', FrogTasks::class)->name('planner.frog-tasks');
Route::get('/hygiene', Hygiene::class)->name('planner.hygiene');
Route::get('/export', Export::class)->name('planner.export');

// Model-Binding: Parameter == Modelname in camelCase
Route::get('/projects/{plannerProject}', Project::class)
    ->name('planner.projects.show');

Route::get('/tasks/{plannerTask}', Task::class)
    ->name('planner.tasks.show');

// Project Health (Snapshot-Detail-Sicht pro Projekt)
Route::get('/projects/{plannerProject}/health', \Platform\Planner\Livewire\ProjectHealth::class)
    ->name('planner.projects.health');

// Health-Index (teamweite Snapshot-Aggregat-Sicht)
Route::get('/health-index', \Platform\Planner\Livewire\HealthIndex::class)
    ->name('planner.health-index');

// Project Canvas Routes
Route::get('/projects/{plannerProject}/canvas', \Platform\Planner\Livewire\ProjectCanvas\Index::class)
    ->name('planner.projects.canvas.index');

Route::get('/projects/{plannerProject}/canvas/{canvas}', \Platform\Planner\Livewire\ProjectCanvas\Show::class)
    ->name('planner.projects.canvas.show');

Route::get('/projects/{plannerProject}/canvas/{canvas}/pdf', \Platform\Planner\Http\Controllers\ProjectCanvasPdfController::class)
    ->name('planner.projects.canvas.pdf');

// Embedded Routes wurden in routes/embedded.php verschoben
