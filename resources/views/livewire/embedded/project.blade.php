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
                            {{-- Debug Box --}}
                            <div class="mt-4 p-3 bg-gray-50 rounded border text-xs">
                                <div class="font-bold mb-2">🔍 Debug Info</div>
                                <div id="teams-sdk-status">Teams SDK: Lade...</div>
                                <div id="teams-context-status">Teams Context: Lade...</div>
                                <div id="teams-user-status">Teams User: Lade...</div>
                                <div id="auth-status">Laravel Auth: Lade...</div>
                                <div class="mt-2">
                                    <div>Laravel Auth: {{ auth()->check() ? '✅ Angemeldet' : '❌ Nicht angemeldet' }}</div>
                                    <div>User: {{ auth()->user() ? auth()->user()->email : 'Kein User' }}</div>
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

    @push('scripts')
    <script>
    (function() {
        function updateDebugInfo(id, content) {
            const el = document.getElementById(id);
            if (el) el.innerHTML = content;
        }

        function debugAuth() {
            console.log('🔍 Debug Auth Status:');
            console.log('- window.__laravelAuthed:', window.__laravelAuthed);
            console.log('- sessionStorage teams-auth-running:', sessionStorage.getItem('teams-auth-running'));
            console.log('- sessionStorage teams-auth-completed:', sessionStorage.getItem('teams-auth-completed'));
            console.log('- sessionStorage teams-auth-retries:', sessionStorage.getItem('teams-auth-retries'));
            
            // Teams SDK Status
            if (window.microsoftTeams && window.microsoftTeams.app) {
                updateDebugInfo('teams-sdk-status', 'Teams SDK: ✅ Verfügbar');
                
                // Teams SDK initialisieren falls noch nicht geschehen
                window.microsoftTeams.app.initialize().then(function() {
                    console.log('✅ Teams SDK initialisiert');
                    updateDebugInfo('teams-sdk-status', 'Teams SDK: ✅ Initialisiert');
                    
                    // Jetzt Context abrufen
                    return window.microsoftTeams.app.getContext();
                }).then(function(context) {
                    console.log('✅ Teams Context erhalten:', context);
                    updateDebugInfo('teams-context-status', 'Teams Context: ✅ Verfügbar<br>Team: ' + (context.team?.displayName || 'N/A'));
                    
                    const email = context.user?.userPrincipalName || context.user?.loginHint;
                    const name = context.user?.displayName;
                    
                    if (email) {
                        updateDebugInfo('teams-user-status', 'Teams User: ✅ ' + email + '<br>Name: ' + (name || 'N/A'));
                        
                        // Versuche Auth manuell
                        fetch('/planner/embedded/teams/auth', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ email: email, name: name || '' })
                        }).then(function(response) {
                            console.log('Auth Response:', response.status);
                            if (response.ok) {
                                updateDebugInfo('auth-status', 'Laravel Auth: ✅ Authentifiziert');
                                setTimeout(() => location.reload(), 100);
                            } else {
                                response.text().then(text => {
                                    updateDebugInfo('auth-status', 'Laravel Auth: ❌ Fehler (' + response.status + '): ' + text);
                                });
                            }
                        }).catch(function(error) {
                            updateDebugInfo('auth-status', 'Laravel Auth: ❌ Request Fehler: ' + error.message);
                        });
                    } else {
                        updateDebugInfo('teams-user-status', 'Teams User: ❌ Kein Email im Context');
                    }
                }).catch(function(error) {
                    console.error('❌ Teams SDK/Context Fehler:', error);
                    updateDebugInfo('teams-context-status', 'Teams Context: ❌ Fehler: ' + error.message);
                });
            } else {
                updateDebugInfo('teams-sdk-status', 'Teams SDK: ❌ Nicht verfügbar');
            }
        }

        // Sofort debuggen
        debugAuth();
        
        // Alle 2 Sekunden erneut prüfen
        setInterval(debugAuth, 2000);
    })();
    </script>
    @endpush
