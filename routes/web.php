<?php

use Platform\Planner\Livewire\Dashboard;
use Platform\Planner\Livewire\MyTasks;
use Platform\Planner\Livewire\CreateProject;
use Platform\Planner\Livewire\Project;
use Platform\Planner\Livewire\Task;
use Platform\Planner\Models\PlannerProject;
use Illuminate\Http\Middleware\FrameGuard;

Route::get('/', Dashboard::class)->name('planner.dashboard');
Route::get('/my-tasks', MyTasks::class)->name('planner.my-tasks');

// Model-Binding: Parameter == Modelname in camelCase
Route::get('/projects/{plannerProject}', Project::class)
    ->name('planner.projects.show');

Route::get('/tasks/{plannerTask}', Task::class)
    ->name('planner.tasks.show');

// Embedded Routes wurden in routes/embedded.php verschoben
