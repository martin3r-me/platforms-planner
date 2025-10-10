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

// Embedded (Teams/iframe) – Teams SSO für automatische Anmeldung
Route::get('/embedded/planner/projects/{plannerProject}', function (PlannerProject $plannerProject) {
    $response = response()->view('planner::embedded.project', compact('plannerProject'));
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->middleware(['teams.sso'])->withoutMiddleware([FrameGuard::class])->name('planner.embedded.project');

// Embedded Task-Ansicht (Teams) – Teams SSO für automatische Anmeldung
Route::get('/embedded/planner/tasks/{plannerTask}', function (\Platform\Planner\Models\PlannerTask $plannerTask) {
    $response = response()->view('planner::embedded.task', compact('plannerTask'));
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->middleware(['teams.sso'])->withoutMiddleware([FrameGuard::class])->name('planner.embedded.task');

// Embedded Test: Teams Tab Konfigurations-Check (neue, saubere URL)
Route::get('/embedded/teams/config', function () {
    return response('<!DOCTYPE html><html><head><title>Teams Config Test</title></head><body><h1>Teams Config Test</h1><p>User: ' . (auth()->user() ? auth()->user()->name : 'Nicht eingeloggt') . '</p><p>Diese Seite sollte keine Redirects haben.</p></body></html>')
        ->header('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
})->withoutMiddleware([FrameGuard::class, 'auth', 'detect.module.guard', 'check.module.permission', 'teams.sso'])->name('planner.embedded.teams.config');

// Rückwärtskompatibel: alte URL auf die neue weiterleiten
Route::get('/embedded/planner/teams/config', function () {
    return redirect()->route('planner.embedded.teams.config');
});

// Öffentliche Einbettungsprobe ohne Auth – isolierter Test, ob Teams im Tab bleibt
Route::get('/embedded/test', function () {
    $response = response('<!doctype html><html><body style="font-family:system-ui;padding:16px">Embedded Test OK</body></html>');
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class, 'auth', 'detect.module.guard', 'check.module.permission'])->name('embedded.test');

// API: Projekte für Teams-Konfiguration (minimal, JSON)
Route::get('/embedded/planner/api/projects', function () {
    $teamId = request()->query('teamId');
    $query = PlannerProject::query()->select(['id', 'name', 'team_id'])->orderBy('name');
    if ($teamId) {
        $query->where('team_id', $teamId);
    }
    return response()->json([
        'data' => $query->limit(200)->get(),
    ])->header('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
})->withoutMiddleware([FrameGuard::class, 'auth', 'detect.module.guard', 'check.module.permission'])->name('planner.embedded.api.projects');

// Auth-API: Projekte des eingeloggten Nutzers (für echte Konfiguration)
Route::get('/embedded/planner/api/my-projects', function () {
    $user = auth()->user();

    $teamIds = collect([]);
    if ($user && method_exists($user, 'teams')) {
        try {
            $teamIds = $teamIds->merge($user->teams()->pluck('teams.id'));
        } catch (\Throwable $e) {
            // ignore
        }
    }
    if ($user && property_exists($user, 'currentTeam') && $user->currentTeam) {
        $teamIds->push($user->currentTeam->id);
    } elseif ($user && method_exists($user, 'currentTeam')) {
        try { $teamIds->push(optional($user->currentTeam())->id); } catch (\Throwable $e) {}
    }
    $teamIds = $teamIds->filter()->unique();

    $query = PlannerProject::query()->select(['id', 'name', 'team_id'])->orderBy('name');
    if ($teamIds->isNotEmpty()) {
        $query->whereIn('team_id', $teamIds);
    } else {
        $query->whereRaw('1=0');
    }

    return response()->json(['data' => $query->limit(200)->get()])
        ->header('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
})->name('planner.embedded.api.my-projects');
