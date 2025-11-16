<?php

use Platform\Planner\Livewire\Dashboard;
use Platform\Planner\Livewire\MyTasks;
use Platform\Planner\Livewire\DelegatedTasks;
use Platform\Planner\Livewire\CompletedTasks;
use Platform\Planner\Livewire\CreateProject;
use Platform\Planner\Livewire\Project;
use Platform\Planner\Livewire\Task;
use Platform\Planner\Models\PlannerProject;
use Illuminate\Http\Middleware\FrameGuard;

Route::get('/', Dashboard::class)->name('planner.dashboard');
Route::get('/my-tasks', MyTasks::class)->name('planner.my-tasks');
Route::get('/delegated-tasks', DelegatedTasks::class)->name('planner.delegated-tasks');
Route::get('/completed-tasks', CompletedTasks::class)->name('planner.completed-tasks');

// Model-Binding: Parameter == Modelname in camelCase
Route::get('/projects/{plannerProject}', Project::class)
    ->name('planner.projects.show');

Route::get('/tasks/{plannerTask}', Task::class)
    ->name('planner.tasks.show');

// Embedded Routes wurden in routes/embedded.php verschoben
