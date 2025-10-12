# Teams SDK Auth Migration - Planner Modul

## Durchgeführte Änderungen

### 1. Routes aktualisiert

Alle embedded Routes verwenden jetzt die neue `teams.sdk.auth` Middleware:

- ✅ `/embedded/planner/projects/{plannerProject}` - Projekt-Ansicht
- ✅ `/embedded/planner/tasks/{plannerTask}` - Task-Ansicht  
- ✅ `/embedded/teams/config` - Teams Tab Konfiguration
- ✅ `/embedded/planner/api/projects` - Projekte API
- ✅ `/embedded/planner/api/my-projects` - User-Projekte API

### 2. Middleware-Konfiguration

```php
// Alte Konfiguration
->middleware(['teams.sso'])->withoutMiddleware([FrameGuard::class])

// Neue Konfiguration  
->middleware(['teams.sdk.auth'])->withoutMiddleware([
    FrameGuard::class, 
    'auth', 
    'detect.module.guard', 
    'check.module.permission'
])
```

### 3. Views aktualisiert

**teams-config.blade.php:**
```php
// Alt
@php($u = auth()->user())
@if($u)
    Hallo, {{ $u->name ?? $u->email ?? 'User' }}

// Neu
@php($teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser(request()))
@if($teamsUser)
    Hallo, {{ $teamsUser['name'] ?? $teamsUser['email'] ?? 'User' }}
```

### 4. Livewire-Komponenten aktualisiert

**Embedded/Project.php:**
- `Auth::user()` → `TeamsAuthHelper::getTeamsUser(request())`
- `findOrCreateUserFromTeams()` Methode als statisch verfügbar gemacht
- User-Finding/Erstellung über Teams Context

### 5. API-Routes aktualisiert

**my-projects API:**
- Verwendet jetzt `TeamsAuthHelper::getTeamsUser(request())`
- User-Finding über `Project::findOrCreateUserFromTeams()`
- Keine Laravel Auth-Abhängigkeit mehr

## Vorteile

1. **Keine Laravel Auth** - Teams SDK übernimmt Authentifizierung
2. **Bessere Performance** - Weniger Middleware-Stack
3. **Teams-native** - Nutzt Teams Context direkt
4. **Konsistent** - Alle embedded Routes verwenden gleiche Auth-Methode

## Nächste Schritte

1. **Testen** - Teams Tab Apps in Microsoft Teams testen
2. **Andere Module** - Gleiche Migration für andere Module durchführen
3. **Dokumentation** - Teams SDK Integration dokumentieren

## Verwendung in anderen Modulen

```php
// Route-Konfiguration
Route::get('/embedded/module/example', function() {
    // ...
})->middleware(['teams.sdk.auth'])->withoutMiddleware([
    FrameGuard::class, 
    'auth', 
    'detect.module.guard', 
    'check.module.permission'
]);

// In Livewire-Komponenten
$teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser(request());
$user = YourModule::findOrCreateUserFromTeams($teamsUser);

// In Blade-Templates
@php($teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser(request()))
@if($teamsUser)
    Hallo, {{ $teamsUser['name'] ?? $teamsUser['email'] }}
@endif
```
