<?php

use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;
use Illuminate\Http\Middleware\FrameGuard;

// Embedded Routes für Microsoft Teams Tab Apps
// Diese Routes laufen OHNE Laravel Auth-Middleware

// Embedded (Teams/iframe) – Projekt-Ansicht
Route::get('/embedded/planner/projects/{plannerProject}', function (PlannerProject $plannerProject) {
    $response = response()->view('planner::embedded.project', compact('plannerProject'));
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class])->name('planner.embedded.project');

// Embedded Task-Ansicht (Teams)
Route::get('/embedded/planner/tasks/{plannerTask}', function (PlannerTask $plannerTask) {
    $response = response()->view('planner::embedded.task', compact('plannerTask'));
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class])->name('planner.embedded.task');

// Embedded Test: Teams Tab Konfigurations-Check
Route::get('/embedded/teams/config', function () {
    $response = response()->view('planner::embedded.teams-config-new');
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class])->name('planner.embedded.teams.config');

// Rückwärtskompatibel: alte URL auf die neue weiterleiten
Route::get('/embedded/planner/teams/config', function () {
    return redirect()->route('planner.embedded.teams.config');
});

// Öffentliche Einbettungsprobe ohne Auth – isolierter Test
Route::get('/embedded/test', function () {
    $response = response('<!doctype html><html><body style="font-family:system-ui;padding:16px">Embedded Test OK</body></html>');
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([FrameGuard::class])->name('embedded.test');

// Teams Debug Route - zeigt alle Request-Informationen
Route::get('/embedded/debug', function () {
    $request = request();
    $debug = [
        'path' => $request->getPathInfo(),
        'method' => $request->getMethod(),
        'headers' => $request->headers->all(),
        'query' => $request->query->all(),
        'post' => $request->post(),
        'cookies' => $request->cookies->all(),
        'user_agent' => $request->userAgent(),
        'ip' => $request->ip(),
        'referer' => $request->header('referer'),
        'x_teams_embedded' => $request->header('X-Teams-Embedded'),
        'teams_context' => $request->query('teams_context'),
    ];
    
    $response = response()->json($debug, 200, [
        'Content-Security-Policy' => "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com"
    ]);
    return $response;
})->withoutMiddleware([FrameGuard::class])->name('embedded.debug');

// Debug: Alle Projekte in der Datenbank anzeigen
Route::get('/embedded/debug-projects', function () {
    $allProjects = PlannerProject::all();
    $projectsByTeam = PlannerProject::select('team_id', \DB::raw('count(*) as count'))
        ->groupBy('team_id')
        ->get();
    
    $debug = [
        'total_projects' => $allProjects->count(),
        'all_projects' => $allProjects->toArray(),
        'projects_by_team' => $projectsByTeam->toArray(),
        'teams_table_exists' => \Schema::hasTable('teams'),
        'planner_projects_table_exists' => \Schema::hasTable('planner_projects'),
    ];
    
    return response()->json($debug, 200, [
        'Content-Security-Policy' => "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com"
    ]);
})->withoutMiddleware([FrameGuard::class])->name('embedded.debug.projects');

// API: Projekte für Teams-Konfiguration (minimal, JSON)
Route::get('/embedded/planner/api/projects', function () {
    $teamId = request()->query('teamId');
    
    // Debug: Log die Anfrage
    \Log::info('Teams API Projects Request', [
        'teamId' => $teamId,
        'query' => request()->query->all()
    ]);
    
    $query = PlannerProject::query()->select(['id', 'name', 'team_id'])->orderBy('name');
    if ($teamId) {
        $query->where('team_id', $teamId);
    }
    
    $projects = $query->limit(200)->get();
    
    // Debug: Log die Ergebnisse
    \Log::info('Teams API Projects Response', [
        'count' => $projects->count(),
        'projects' => $projects->toArray()
    ]);
    
    return response()->json([
        'data' => $projects,
        'debug' => [
            'teamId' => $teamId,
            'count' => $projects->count(),
            'query' => request()->query->all()
        ]
    ])->header('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
})->withoutMiddleware([FrameGuard::class])->name('planner.embedded.api.projects');

// Auth-API: Projekte des eingeloggten Nutzers (für echte Konfiguration)
Route::get('/embedded/planner/api/my-projects', function () {
    // Teams User-Info aus Request holen (ohne Laravel Auth)
    $teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser(request());
    
    if (!$teamsUser) {
        return response()->json(['data' => []])
            ->header('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    }

    // User aus Teams Context finden oder erstellen
    $user = \Platform\Planner\Livewire\Embedded\Project::findOrCreateUserFromTeams($teamsUser);
    
    if (!$user) {
        return response()->json(['data' => []])
            ->header('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    }

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
})->withoutMiddleware([FrameGuard::class])->name('planner.embedded.api.my-projects');
