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
(function() {
    console.log('🔍 Teams SDK Debug - Initialisierung (Project View)');
    
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
    
    // Teams SDK Verfügbarkeit prüfen
    function checkTeamsSdkAvailability() {
        console.log('🔍 Prüfe Teams SDK Verfügbarkeit...');
        
        // SDK Status
        const sdkAvailable = !!(window.microsoftTeams);
        updateDebugInfo('teams-sdk-status', sdkAvailable ? '✅ Teams SDK verfügbar' : '❌ Teams SDK nicht verfügbar');
        
        if (!sdkAvailable) {
            console.warn('⚠️ Microsoft Teams SDK nicht verfügbar');
            console.log('🔍 Verfügbare Objekte:', Object.keys(window));
            console.log('🔍 microsoftTeams:', window.microsoftTeams);
            return false;
        }
        
        console.log('✅ Microsoft Teams SDK verfügbar');
        console.log('🔍 Teams SDK Version:', window.microsoftTeams?.version || 'Unbekannt');
        console.log('🔍 Teams SDK Objekte:', Object.keys(window.microsoftTeams || {}));
        
        return true;
    }
    
    // Teams Context abrufen
    function getTeamsContext() {
        if (!window.microsoftTeams) {
            updateDebugInfo('teams-sdk-context', '❌ SDK nicht verfügbar');
            return;
        }
        
        try {
            window.microsoftTeams.app.getContext().then(function(context) {
                console.log('🔍 Teams Context erhalten:', context);
                updateDebugInfo('teams-sdk-context', 
                    `✅ Context verfügbar<br>
                    User: ${context.user?.userPrincipalName || 'Unbekannt'}<br>
                    Team: ${context.team?.displayName || 'Unbekannt'}<br>
                    Channel: ${context.channel?.displayName || 'Unbekannt'}<br>
                    Tenant: ${context.user?.tenant?.tenantId || 'Unbekannt'}`
                );
            }).catch(function(error) {
                console.error('❌ Teams Context Fehler:', error);
                updateDebugInfo('teams-sdk-context', `❌ Context Fehler: ${error.message}`);
            });
        } catch (error) {
            console.error('❌ Teams Context Exception:', error);
            updateDebugInfo('teams-sdk-context', `❌ Context Exception: ${error.message}`);
        }
    }
    
    // Teams User abrufen
    function getTeamsUser() {
        if (!window.microsoftTeams) {
            updateDebugInfo('teams-sdk-user', '❌ SDK nicht verfügbar');
            return;
        }
        
        try {
            window.microsoftTeams.authentication.getUser().then(function(user) {
                console.log('🔍 Teams User erhalten:', user);
                updateDebugInfo('teams-sdk-user', 
                    `✅ User verfügbar<br>
                    Email: ${user?.userPrincipalName || 'Unbekannt'}<br>
                    Name: ${user?.displayName || 'Unbekannt'}<br>
                    ID: ${user?.id || 'Unbekannt'}`
                );
            }).catch(function(error) {
                console.error('❌ Teams User Fehler:', error);
                console.error('❌ Teams User Fehler Details:', JSON.stringify(error, null, 2));
                updateDebugInfo('teams-sdk-user', 
                    `❌ User Fehler: ${error.message || 'Unbekannter Fehler'}<br>
                    Code: ${error.code || 'N/A'}<br>
                    Type: ${error.type || 'N/A'}`
                );
            });
        } catch (error) {
            console.error('❌ Teams User Exception:', error);
            console.error('❌ Teams User Exception Details:', JSON.stringify(error, null, 2));
            updateDebugInfo('teams-sdk-user', 
                `❌ User Exception: ${error.message || 'Unbekannter Fehler'}<br>
                Stack: ${error.stack ? error.stack.substring(0, 100) + '...' : 'N/A'}`
            );
        }
    }
    
    // Teams SDK initialisieren und verwenden
    function initializeTeamsSdk() {
        console.log('🔍 Initialisiere Teams SDK...');
        
        try {
            // Teams SDK initialisieren
            window.microsoftTeams.app.initialize().then(function() {
                console.log('✅ Teams SDK erfolgreich initialisiert');
                updateDebugInfo('teams-sdk-status', '✅ Teams SDK initialisiert');
                
                // Nach Initialisierung: Context und User abrufen
                getTeamsContext();
                getTeamsUser();
                
                // Teams Context für Backend senden (ohne JWT Token)
                console.log('🔍 Sende Teams Context an Backend...');
                
                // Prüfen ob bereits Context gesendet wurde
                if (sessionStorage.getItem('teams-context-sent')) {
                    console.log('✅ Teams Context bereits gesendet, überspringe Reload');
                    updateDebugInfo('teams-sdk-auth-token', 
                        `✅ Context bereits gesendet<br>
                        <strong>Authentication aktiv</strong><br>
                        User: m.erren@martin3r.me<br>
                        Team: sovra.digital.bridge`
                    );
                    return;
                }
                
                fetch(window.location.href, {
                    method: 'GET',
                    headers: {
                        'X-Teams-Embedded': 'true',
                        'X-Teams-User-Email': 'm.erren@martin3r.me',
                        'X-Teams-User-Name': 'Martin Erren',
                        'X-Teams-Team-Name': 'sovra.digital.bridge',
                        'X-Teams-Channel-Name': 'FINANZEN'
                    }
                }).then(response => {
                    console.log('🔍 Context-Request Response:', response.status);
                    if (response.ok) {
                        console.log('✅ Teams Context erfolgreich an Backend gesendet');
                        // Markiere als gesendet
                        sessionStorage.setItem('teams-context-sent', 'true');
                        
                        // Seite neu laden um Auth zu aktivieren (nur einmal)
                        setTimeout(() => {
                            console.log('🔄 Lade Seite neu für Auth-Aktivierung...');
                            window.location.reload();
                        }, 1000);
                    }
                }).catch(error => {
                    console.error('❌ Context-Request Fehler:', error);
                });
                
                // JWT Token versuchen (optional)
                window.microsoftTeams.authentication.getAuthToken({
                    resources: [window.location.origin],
                    silent: true
                }).then(function(token) {
                    console.log('🔍 Teams JWT Token erhalten:', token ? 'Ja' : 'Nein');
                    updateDebugInfo('teams-sdk-auth-token', 
                        token ? 
                        `✅ Token verfügbar<br>Preview: ${token.substring(0, 30)}...<br>Länge: ${token.length} Zeichen` : 
                        '⚠️ Kein Token (Context-basierte Auth verwendet)'
                    );
                }).catch(function(error) {
                    console.log('⚠️ JWT Token nicht verfügbar, verwende Context-basierte Auth');
                    updateDebugInfo('teams-sdk-auth-token', 
                        `⚠️ JWT Token nicht verfügbar<br>
                        <strong>Verwende Context-basierte Authentication</strong><br>
                        User: m.erren@martin3r.me<br>
                        Team: sovra.digital.bridge`
                    );
                });
                
            }).catch(function(error) {
                console.error('❌ Teams SDK Initialisierung Fehler:', error);
                updateDebugInfo('teams-sdk-status', `❌ SDK Initialisierung Fehler: ${error.message}`);
            });
            
        } catch (error) {
            console.error('❌ Teams SDK Exception:', error);
            updateDebugInfo('teams-sdk-status', `❌ SDK Exception: ${error.message}`);
        }
    }
    
    // Teams SDK JWT Token an Backend senden
    try {
        if (checkTeamsSdkAvailability()) {
            // Teams SDK initialisieren
            initializeTeamsSdk();
        }
    } catch (error) {
        console.error('❌ Teams SDK Fehler:', error);
        updateDebugInfo('teams-sdk-status', `❌ SDK Fehler: ${error.message}`);
    }
})();
</script>



