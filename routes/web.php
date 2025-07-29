<?php

use Platform\Planner\Livewire\Dashboard;
use Platform\Planner\Livewire\CreateProject;
use Platform\Planner\Livewire\Project;
use Platform\Planner\Livewire\Task;

Route::get('/', Dashboard::class)->name('planner.dashboard');
Route::get('/projects/create', CreateProject::class)->name('planner.projects.create');

// Model-Binding: Parameter == Modelname in camelCase
Route::get('/projects/{plannerProject}', Project::class)
    ->name('planner.projects.show');

Route::get('/tasks/{plannerTask}', Task::class)
    ->name('planner.tasks.show');
