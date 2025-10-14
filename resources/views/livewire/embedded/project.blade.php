<div class="h-full">
    @php 
        $completedTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
        $stats = [
            [
                'title' => 'Story Points (offen)',
                'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'chart-bar',
                'variant' => 'warning'
            ],
            [
                'title' => 'Story Points (erledigt)',
                'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'check-circle',
                'variant' => 'success'
            ],
            [
                'title' => 'Offen',
                'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count()),
                'icon' => 'clock',
                'variant' => 'warning'
            ],
            [
                'title' => 'Gesamt',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->count(),
                'icon' => 'document-text',
                'variant' => 'secondary'
            ],
            [
                'title' => 'Erledigt',
                'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count()),
                'icon' => 'check-circle',
                'variant' => 'success'
            ]
        ];
    @endphp

    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list">
                <x-slot name="titleActions">
                    <x-ui-button variant="secondary-ghost" size="sm" rounded="full" iconOnly="true" x-data @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })" title="Einstellungen">
                        @svg('heroicon-o-cog-6-tooth','w-4 h-4')
                    </x-ui-button>
                </x-slot>
                <x-ui-button variant="secondary" size="sm" wire:click="createProjectSlot">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-square-2-stack','w-4 h-4')
                        <span class="hidden sm:inline">Spalte</span>
                    </span>
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" wire:click="createTask()">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-plus','w-4 h-4')
                        <span class="hidden sm:inline">Aufgabe</span>
                    </span>
                </x-ui-button>
            </x-ui-page-navbar>
        </x-slot>

        {{-- Kanban-Container: automatischer Board/List-Wechsel --}}
            <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
                {{-- Backlog (nicht sortierbar als Gruppe) --}}
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                        @foreach($backlog->tasks as $task)
                            <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.embedded.task', $task)" wire:key="task-{{ $task->id }}">
                                <div class="text-xs text-[var(--ui-muted)]">
                                @if($task->due_date)
                                        Fällig: {{ $task->due_date->format('d.m.Y') }}
                                    @else
                                        Keine Fälligkeit
                                @endif
                                </div>
                            </x-ui-kanban-card>
                        @endforeach
                    </x-ui-kanban-column>
                @endif

                {{-- Mittlere Spalten (sortierbar) --}}
                @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false)) as $column)
                    <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" wire:key="column-{{ $column->id }}">
                        <x-slot name="headerActions">
                            <button 
                                wire:click="createTask('{{ $column->id }}')" 
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                        <button 
                            @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $column->id }} })"
                            class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                            title="Einstellungen"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        </x-slot>

                        @foreach($column->tasks as $task)
                            <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.embedded.task', $task)" wire:key="task-{{ $task->id }}">
                                <div class="text-xs text-[var(--ui-muted)]">
                                @if($task->due_date)
                                        Fällig: {{ $task->due_date->format('d.m.Y') }}
                                    @else
                                        Keine Fälligkeit
                                @endif
                                </div>
                            </x-ui-kanban-card>
                        @endforeach
                    </x-ui-kanban-column>
                @endforeach

                {{-- Erledigt (nicht sortierbar als Gruppe) --}}
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                        @foreach($done->tasks as $task)
                            <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.embedded.task', $task)" wire:key="task-{{ $task->id }}">
                                <div class="text-xs text-[var(--ui-muted)]">
                                @if($task->due_date)
                                        Fällig: {{ $task->due_date->format('d.m.Y') }}
                                    @else
                                        Keine Fälligkeit
                                @endif
                                </div>
                            </x-ui-kanban-card>
                        @endforeach
                    </x-ui-kanban-column>
                @endif
            </x-ui-kanban-container>

        {{-- Linke Sidebar --}}
        <x-slot name="sidebar">
            <x-ui-page-sidebar title="Projekt-Übersicht" width="w-80" :defaultOpen="true">
                <div class="p-4 space-y-4">
                            <!-- DEBUG: Detailliertes Teams SDK Debugging -->
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">🔍 Detailliertes Debug</h3>
                                <div class="space-y-2">
                                    @php
                                        $teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser(request());
                                        $teamsContext = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsContext(request());
                                        $authUser = auth()->user();
                                        $request = request();
                                    @endphp
                                    
                                    <!-- Teams SDK Frontend Status -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Teams SDK Frontend</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            <div id="teams-sdk-status">Lade...</div>
                                            <div id="teams-sdk-context">Lade...</div>
                                            <div id="teams-sdk-user">Lade...</div>
                                            <div id="teams-sdk-auth-token">Lade...</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Teams User Details -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Teams User (Backend)</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            @if($teamsUser)
                                                ✅ <strong>{{ $teamsUser['email'] ?? 'Keine Email' }}</strong><br>
                                                Name: {{ $teamsUser['name'] ?? 'Kein Name' }}<br>
                                                ID: {{ $teamsUser['id'] ?? 'Keine ID' }}<br>
                                                Tenant: {{ $teamsUser['tenant_id'] ?? 'Kein Tenant' }}
                                            @else
                                                ❌ Nicht gefunden
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Teams Context Details -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Teams Context</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            @if($teamsContext)
                                                ✅ Verfügbar<br>
                                                Keys: {{ implode(', ', array_keys($teamsContext)) }}<br>
                                                User: {{ isset($teamsContext['user']) ? 'Ja' : 'Nein' }}
                                            @else
                                                ❌ Nicht verfügbar
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Laravel Auth Details -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Laravel Auth</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            @if($authUser)
                                                ✅ <strong>{{ $authUser->email }}</strong><br>
                                                ID: {{ $authUser->id }}<br>
                                                Name: {{ $authUser->name }}<br>
                                                Team: {{ $authUser->currentTeam?->name ?? 'Kein Team' }}
                                            @else
                                                ❌ Nicht angemeldet
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Request Headers -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Request Headers</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            Authorization: {{ $request->header('Authorization') ? 'Ja' : 'Nein' }}<br>
                                            X-Teams-Token: {{ $request->header('X-Teams-Token') ? 'Ja' : 'Nein' }}<br>
                                            X-User-Email: {{ $request->header('X-User-Email') ?: 'Nein' }}<br>
                                            X-User-Name: {{ $request->header('X-User-Name') ?: 'Nein' }}<br>
                                            X-Teams-Embedded: {{ $request->header('X-Teams-Embedded') ?: 'Nein' }}
                                        </div>
                                    </div>
                                    
                                    <!-- Request Details -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Request Info</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            Path: {{ $request->getPathInfo() }}<br>
                                            Method: {{ $request->getMethod() }}<br>
                                            Referer: {{ $request->header('referer', 'Kein Referer') }}<br>
                                            User-Agent: {{ substr($request->header('user-agent', ''), 0, 50) }}...
                                        </div>
                                    </div>
                                    
                                    <!-- Query Parameters -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Query Params</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            @if($request->query->count() > 0)
                                                @foreach($request->query->all() as $key => $value)
                                                    {{ $key }}: {{ is_string($value) ? $value : 'Array' }}<br>
                                                @endforeach
                                            @else
                                                Keine Query Parameter
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Middleware Status -->
                                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)]">Middleware</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            Route: {{ $request->route()?->getName() ?? 'Unbekannt' }}<br>
                                            Middleware: {{ implode(', ', $request->route()?->middleware() ?? []) }}<br>
                                            Teams Request: {{ str_contains($request->getPathInfo(), '/embedded/') ? 'Ja' : 'Nein' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                    <!-- Projekt-Statistiken -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                        <div class="space-y-2">
                            @foreach($stats as $stat)
                                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-' . $stat['icon'], 'w-4 h-4 text-[var(--ui-' . $stat['variant'] . ')]')
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ $stat['title'] }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-[var(--ui-' . $stat['variant'] . ')]">
                                        {{ $stat['count'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Projekt-Details -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Projekt</h3>
                        <div class="space-y-2">
                            <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $project->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $project->project_type?->value ?? 'Intern' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Aktionen</h3>
                        <div class="space-y-2">
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="createTask()" class="w-full">
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Neue Aufgabe
                                </span>
                            </x-ui-button>
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="createProjectSlot" class="w-full">
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                                    Neue Spalte
                                </span>
                            </x-ui-button>
                        </div>
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>

        {{-- Rechte Sidebar --}}
        <x-slot name="activity">
            <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
                <div class="p-4 space-y-4">
                    <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                    <div class="space-y-3 text-sm">
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Projekt geöffnet</div>
                            <div class="text-[var(--ui-muted)]">Gerade eben</div>
                        </div>
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Teams Tab erstellt</div>
                            <div class="text-[var(--ui-muted)]">Vor 5 Minuten</div>
                        </div>
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>
    </x-ui-page>

    {{-- Modals für embedded Projekt-View --}}
    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>
</div>

<script>
// Einfache Teams Authentication mit Debug-Info
(function() {
    console.log('🔍 Teams Authentication - Vereinfacht');
    
    // Prüfen ob bereits authentifiziert
    if (sessionStorage.getItem('teams-auth-completed') === 'true') {
        console.log('✅ Teams Auth bereits abgeschlossen - überspringe');
        return;
    }
    
    // Debug-Update-Funktion
    function updateDebugInfo(elementId, content) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = content;
            console.log(`🔍 Debug Update: ${elementId}`, content);
        } else {
            console.warn(`⚠️ Element nicht gefunden: ${elementId}`);
        }
    }
    
    // Sofortige Debug-Info setzen
    updateDebugInfo('teams-sdk-status', '🔍 Initialisiere...');
    updateDebugInfo('teams-sdk-context', '🔍 Initialisiere...');
    updateDebugInfo('teams-sdk-user', '🔍 Initialisiere...');
    updateDebugInfo('teams-sdk-auth-token', '🔍 Initialisiere...');
    
    // Teams SDK initialisieren und User einloggen
    if (window.microsoftTeams) {
        updateDebugInfo('teams-sdk-status', '✅ Teams SDK verfügbar');
        
        window.microsoftTeams.app.initialize().then(function() {
            console.log('✅ Teams SDK initialisiert');
            updateDebugInfo('teams-sdk-status', '✅ Teams SDK initialisiert');
            
            // Teams Context abrufen
            window.microsoftTeams.app.getContext().then(function(context) {
                console.log('🔍 Teams Context:', context);
                updateDebugInfo('teams-sdk-context', 
                    `✅ Context verfügbar<br>
                    User: ${context.user?.userPrincipalName || 'Unbekannt'}<br>
                    Team: ${context.team?.displayName || 'Unbekannt'}<br>
                    Channel: ${context.channel?.displayName || 'Unbekannt'}`
                );
                
                // User über Teams Context einloggen
                if (context.user?.userPrincipalName) {
                    console.log('🔍 User gefunden:', context.user.userPrincipalName);
                    updateDebugInfo('teams-sdk-user', 
                        `✅ User verfügbar<br>
                        Email: ${context.user.userPrincipalName}<br>
                        Name: ${context.user.displayName || context.user.userPrincipalName}`
                    );
                    
                    updateDebugInfo('teams-sdk-auth-token', '🔍 Authentifiziere User...');
                    
                    // Einfacher fetch um User zu authentifizieren
                    fetch('/planner/embedded/teams/auth', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        },
                        body: JSON.stringify({
                            email: context.user.userPrincipalName,
                            name: context.user.displayName || context.user.userPrincipalName,
                            team: context.team?.displayName,
                            channel: context.channel?.displayName
                        })
                    }).then(response => {
                        console.log('🔍 Auth Response Status:', response.status);
                        console.log('🔍 Auth Response Headers:', response.headers);
                        
                        if (response.ok) {
                            console.log('✅ User erfolgreich authentifiziert');
                            updateDebugInfo('teams-sdk-auth-token', '✅ User authentifiziert - Lade Seite neu...');
                            // Session markieren als abgeschlossen
                            sessionStorage.setItem('teams-auth-completed', 'true');
                            // Seite neu laden um Auth zu aktivieren
                            window.location.reload();
                        } else {
                            // Response-Text für Details abrufen
                            response.text().then(text => {
                                console.error('❌ Auth Response Error:', text);
                                updateDebugInfo('teams-sdk-auth-token', `❌ Authentication fehlgeschlagen (${response.status}): ${text}`);
                            });
                        }
                    }).catch(error => {
                        console.error('❌ Authentication Fehler:', error);
                        updateDebugInfo('teams-sdk-auth-token', `❌ Authentication Fehler: ${error.message}`);
                    });
                } else {
                    updateDebugInfo('teams-sdk-user', '❌ Kein User im Context gefunden');
                }
            }).catch(function(error) {
                console.error('❌ Teams Context Fehler:', error);
                updateDebugInfo('teams-sdk-context', `❌ Context Fehler: ${error.message}`);
            });
        }).catch(function(error) {
            console.error('❌ Teams SDK Initialisierung Fehler:', error);
            updateDebugInfo('teams-sdk-status', `❌ SDK Initialisierung Fehler: ${error.message}`);
        });
    } else {
        updateDebugInfo('teams-sdk-status', '❌ Teams SDK nicht verfügbar');
        console.warn('⚠️ Microsoft Teams SDK nicht verfügbar');
    }
})();
</script>
@push('scripts')
<script>
// Sicherstellen, dass der schlanke Auth-Bootstrap läuft, falls vorher blockiert
(function(){
    try {
        if (sessionStorage.getItem('teams-auth-completed') === 'true') return;
        if (window.microsoftTeams?.app) {
            window.microsoftTeams.app.initialize().then(function(){
                return window.microsoftTeams.app.getContext();
            }).then(function(ctx){
                const email = ctx?.user?.userPrincipalName || '';
                const name = ctx?.user?.displayName || '';
                if (!email) return;
                fetch('/planner/embedded/teams/auth', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ email: email, name: name })
                }).then(function(res){
                    if (res.ok) {
                        sessionStorage.setItem('teams-auth-completed', 'true');
                        location.reload();
                    }
                }).catch(function(){});
            }).catch(function(){});
        }
    } catch(_) {}
})();
</script>
@endpush
