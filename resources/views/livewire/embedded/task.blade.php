    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check">
            <div class="mt-1 text-sm text-[var(--ui-muted)] flex items-center gap-2">
                <span class="flex items-center gap-1">
                        @svg('heroicon-o-home', 'w-4 h-4')
                        Teams
                    </span>
                    @if($task->project)
                    <span>›</span>
                        <a href="{{ route('planner.embedded.project', $task->project) }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                            @svg('heroicon-o-folder', 'w-4 h-4')
                            {{ $task->project->name }}
                        </a>
                    @endif
                <span>›</span>
                <span class="flex items-center gap-1">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                    Aufgabe
                    </span>
                </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Navigation & Details" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-3 text-sm">
                @if($task->project)
                    <x-ui-button variant="secondary-outline" :href="route('planner.embedded.project', $task->project)" class="w-full">
                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4 mr-1')
                        Zur Projektübersicht
                    </x-ui-button>
                @endif
                <div class="text-[var(--ui-muted)]">Task-ID: {{ $task->id }}</div>
                
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
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" side="right" storeKey="activityOpen">
            <div class="p-4 space-y-2 text-sm">
                <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                    <div class="font-medium text-[var(--ui-secondary)] truncate">Aufgabe geöffnet</div>
                    <div class="text-[var(--ui-muted)]">Gerade eben</div>
                </div>
            </div>
        </x-ui-page-sidebar>
        </x-slot>

    <div class="p-4 space-y-4">
            <div class="bg-white rounded-lg border p-4">
            <div class="text-sm text-[var(--ui-muted)] mb-2">Task-ID: {{ $task->id }}</div>
                        <x-ui-input-text
                            name="task.title"
                            label="Titel"
                wire:model.live.debounce.500ms="task.title"
                            placeholder="Aufgabentitel eingeben..."
                            required
                            :errorKey="'task.title'"
                        />
                    </div>
        <div class="bg-white rounded-lg border p-4">
                        <x-ui-input-select
                            name="task.priority"
                            label="Priorität"
                            :options="\Platform\Planner\Enums\TaskPriority::cases()"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="false"
                            wire:model.live="task.priority"
                        />
                    </div>
        <div class="bg-white rounded-lg border p-4">
            <div class="space-y-2">
                <label class="block text-sm font-medium text-[var(--ui-secondary)]">Fälligkeitsdatum</label>
                <input 
                    type="datetime-local" 
                    class="w-full rounded border border-[var(--ui-border)]/60 px-3 py-2 text-sm"
                    wire:model.live="dueDateInput"
                    @keydown.enter.prevent
                />
            </div>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <x-ui-input-select
                name="task.story_points"
                label="Story Points"
                :options="\Platform\Planner\Enums\TaskStoryPoints::cases()"
                optionValue="value"
                optionLabel="label"
                            :nullable="true"
                nullLabel="– Story Points auswählen –"
                wire:model.live="task.story_points"
                        />
                    </div>
        <div class="bg-white rounded-lg border p-4">
                        <x-ui-input-select
                            name="task.user_in_charge_id"
                            label="Verantwortlicher"
                :options="$teamUsers"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Verantwortlichen auswählen –"
                            wire:model.live="task.user_in_charge_id"
                        />
                    </div>

        {{-- Definition of Done als interaktive Checkliste --}}
        <div class="bg-white rounded-lg border p-4">
            <div class="mb-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-[var(--ui-secondary)]">Definition of Done</label>
                        <span class="text-xs text-[var(--ui-muted)] px-1.5 py-0.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded">
                            Verschlüsselt
                        </span>
                    </div>
                    @if(count($dodItems) > 0)
                        <span class="text-xs text-[var(--ui-muted)]">
                            {{ $this->dodProgress['checked'] }}/{{ $this->dodProgress['total'] }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- DoD Items --}}
            <div class="space-y-2">
                @forelse($dodItems as $index => $item)
                    <div
                        class="group flex items-center gap-2 p-2 rounded border border-[var(--ui-border)]/40 {{ $item['checked'] ? 'bg-green-50' : 'bg-white' }}"
                        wire:key="dod-item-{{ $index }}"
                    >
                        <button
                            type="button"
                            wire:click="toggleDodItem({{ $index }})"
                            class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center {{ $item['checked'] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-blue-500' }}"
                        >
                            @if($item['checked'])
                                @svg('heroicon-s-check', 'w-3 h-3')
                            @endif
                        </button>
                        <span class="flex-1 text-sm {{ $item['checked'] ? 'line-through text-gray-400' : 'text-gray-700' }}">
                            {{ $item['text'] }}
                        </span>
                        <button
                            type="button"
                            wire:click="removeDodItem({{ $index }})"
                            class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500"
                        >
                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                        </button>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-4">Keine DoD-Kriterien</p>
                @endforelse
            </div>

            {{-- Neues Item --}}
            <div class="mt-3" x-data="{ newText: '', adding: false }">
                <template x-if="!adding">
                    <button
                        type="button"
                        @click="adding = true; $nextTick(() => $refs.input?.focus())"
                        class="w-full flex items-center gap-2 p-2 border border-dashed border-gray-300 rounded text-gray-400 hover:border-blue-400 hover:text-blue-500"
                    >
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span class="text-sm">Hinzufügen</span>
                    </button>
                </template>
                <template x-if="adding">
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            x-ref="input"
                            x-model="newText"
                            @keydown.enter.prevent="if(newText.trim()) { $wire.addDodItem(newText); newText = ''; }"
                            @keydown.escape="adding = false"
                            class="flex-1 p-2 border border-gray-300 rounded text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            placeholder="DoD-Kriterium..."
                        />
                        <button
                            type="button"
                            @click="if(newText.trim()) { $wire.addDodItem(newText); newText = ''; } adding = false;"
                            class="p-2 text-blue-500 hover:bg-blue-50 rounded"
                        >
                            @svg('heroicon-o-check', 'w-5 h-5')
                        </button>
                    </div>
                </template>
            </div>
        </div>
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
            
            // Guards gegen mehrfache Auth-Versuche
            if (window.__laravelAuthed === true) {
                updateDebugInfo('auth-status', 'Laravel Auth: ✅ Bereits angemeldet');
                return;
            }
            
            if (sessionStorage.getItem('teams-auth-completed') === 'true') {
                updateDebugInfo('auth-status', 'Laravel Auth: ✅ Auth bereits abgeschlossen');
                return;
            }
            
            // Wenn Auth läuft, aber schon länger als 10 Sekunden, dann zurücksetzen
            if (sessionStorage.getItem('teams-auth-running') === 'true') {
                const authStartTime = sessionStorage.getItem('teams-auth-start-time');
                if (authStartTime && (Date.now() - parseInt(authStartTime)) > 10000) {
                    console.log('🔄 Auth läuft zu lange, setze zurück');
                    sessionStorage.removeItem('teams-auth-running');
                    sessionStorage.removeItem('teams-auth-start-time');
                } else {
                    updateDebugInfo('auth-status', 'Laravel Auth: 🔄 Auth läuft bereits...');
                    return;
                }
            }
            
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
                        
                        // Auth-Flag setzen um mehrfache Versuche zu verhindern
                        sessionStorage.setItem('teams-auth-running', 'true');
                        sessionStorage.setItem('teams-auth-start-time', Date.now().toString());
                        
                        // Versuche Auth manuell
                        fetch('/planner/embedded/teams/auth', {
                            method: 'POST',
                            credentials: 'include',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'X-Teams-Embedded': '1'
                            },
                            body: JSON.stringify({ email: email, name: name || '' })
                        }).then(function(response) {
                            console.log('Auth Response:', response.status);
                            if (response.ok) {
                                updateDebugInfo('auth-status', 'Laravel Auth: ✅ Authentifiziert');
                                sessionStorage.setItem('teams-auth-completed', 'true');
                                sessionStorage.removeItem('teams-auth-running');
                                setTimeout(() => location.reload(), 100);
                            } else {
                                sessionStorage.removeItem('teams-auth-running');
                                response.text().then(text => {
                                    updateDebugInfo('auth-status', 'Laravel Auth: ❌ Fehler (' + response.status + '): ' + text);
                                });
                            }
                        }).catch(function(error) {
                            sessionStorage.removeItem('teams-auth-running');
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
        
        // Nur einmalig prüfen (kein dauerhaftes Polling)
        // Zusätzlich: Server-Ping für Auth-Status und Cookies
        function pingServer(){
            fetch('/planner/embedded/teams/ping', { credentials: 'include' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    const authed = data?.auth?.checked ? '✅' : '❌';
                    const email = data?.auth?.user?.email || 'Kein User';
                    updateDebugInfo('auth-status', `Laravel Auth: ${authed} ${email}`);
                })
                .catch(function(err){ /* ignore */ });
        }
        pingServer();
    })();
    </script>
    @endpush
</x-ui-page>

