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

// Embedded (Teams/iframe) – Auth via Core Middleware-Kette (global)
Route::get('/embedded/planner/projects/{plannerProject}', function (PlannerProject $plannerProject) {
    $response = response()->view('planner::embedded.project', compact('plannerProject'));
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class, 'auth', 'detect.module.guard', 'check.module.permission'])->name('planner.embedded.project');

// Embedded Test: Teams Tab Konfigurations-Check
Route::get('/embedded/planner/teams/config', function () {
    $response = response()->view('planner::embedded.teams-config');
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class, 'auth', 'detect.module.guard', 'check.module.permission'])->name('planner.embedded.teams.config');

// Öffentliche Einbettungsprobe ohne Auth – isolierter Test, ob Teams im Tab bleibt
Route::get('/embedded/test', function () {
    $response = response('<!doctype html><html><body style="font-family:system-ui;padding:16px">Embedded Test OK</body></html>');
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class, 'auth', 'detect.module.guard', 'check.module.permission'])->name('embedded.test');
