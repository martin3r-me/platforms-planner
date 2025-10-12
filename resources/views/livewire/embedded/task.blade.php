<div class="h-full">
    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar title="Aufgabe" icon="heroicon-o-clipboard-document-check">
                {{-- Simple Breadcrumbs für Embedded --}}
                <div class="flex items-center space-x-2 text-sm">
                    <span class="text-[var(--ui-muted)] flex items-center gap-1">
                        @svg('heroicon-o-home', 'w-4 h-4')
                        Teams
                    </span>
                    @if($task->project)
                        <span class="text-[var(--ui-muted)]">›</span>
                        <a href="{{ route('planner.embedded.project', $task->project) }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                            @svg('heroicon-o-folder', 'w-4 h-4')
                            {{ $task->project->name }}
                        </a>
                    @endif
                    <span class="text-[var(--ui-muted)]">›</span>
                    <span class="text-[var(--ui-muted)] flex items-center gap-1">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                        {{ $task->title }}
                    </span>
                </div>
                
                @if($printingAvailable)
                    <x-ui-button variant="secondary" size="sm" wire:click="printTask()">
                        @svg('heroicon-o-printer', 'w-4 h-4')
                        <span class="hidden sm:inline ml-1">Drucken</span>
                    </x-ui-button>
                @endif
                <x-ui-confirm-button 
                    action="deleteTaskAndReturnToProject" 
                    text="Löschen" 
                    confirmText="Wirklich löschen?" 
                    variant="danger"
                    :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                />
            </x-ui-page-navbar>
        </x-slot>

        <x-ui-page-container spacing="space-y-4" class="p-4">
            <div class="bg-white rounded-lg border p-4">
                <x-ui-form-grid :cols="1" :gap="4" class="md:grid-cols-2">
                    <div>
                        <x-ui-input-text
                            name="task.title"
                            label="Titel"
                            wire:model.live.debounce.1000ms="task.title"
                            placeholder="Aufgabentitel eingeben..."
                            required
                            :errorKey="'task.title'"
                        />
                    </div>
                    <div>
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
                    <div>
                        <x-ui-input-datetime
                            name="dueDateInput"
                            label="Fälligkeitsdatum"
                            :value="$dueDateInput"
                            wire:model="dueDateInput"
                            placeholder="Fälligkeitsdatum auswählen..."
                            :nullable="true"
                            :errorKey="'dueDateInput'"
                        />
                    </div>
                    <div>
                        <x-ui-input-select
                            name="task.user_in_charge_id"
                            label="Verantwortlicher"
                            :options="($teamUsers ?? collect([]))"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Verantwortlichen auswählen –"
                            wire:model.live="task.user_in_charge_id"
                        />
                    </div>
                </x-ui-form-grid>

                <div class="mt-6 pt-6 border-t">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui-input-checkbox
                            model="task.is_done"
                            checked-label="Erledigt"
                            unchecked-label="Als erledigt markieren"
                            size="md"
                            block="true"
                        />
                        <x-ui-input-checkbox
                            model="task.is_frog"
                            checked-label="Frosch (wichtig & unangenehm)"
                            unchecked-label="Als Frosch markieren"
                            size="md"
                            block="true"
                        />
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t">
                    <x-ui-input-textarea
                        name="task.description"
                        label="Beschreibung"
                        wire:model.live.debounce.1000ms="task.description"
                        placeholder="Aufgabenbeschreibung (optional)"
                        rows="3"
                        :errorKey="'task.description'"
                    />
                </div>
            </div>
        </x-ui-page-container>

        {{-- Linke Sidebar --}}
        <x-slot name="sidebar">
            <x-ui-page-sidebar title="Navigation & Details" width="w-80" :defaultOpen="true">
                <div class="p-6 space-y-6">
                    {{-- Navigation --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Navigation</h3>
                        <div class="space-y-2">
                            @if($task->project)
                                <x-ui-button
                                    variant="secondary-outline"
                                    size="sm"
                                    :href="route('planner.embedded.project', $task->project)"
                                    wire:navigate
                                    class="w-full"
                                >
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-folder', 'w-4 h-4')
                                        Zum Projekt
                                    </span>
                                </x-ui-button>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Stats --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Status</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Erledigt</span>
                                <x-ui-input-checkbox
                                    model="task.is_done"
                                    checked-label=""
                                    unchecked-label=""
                                    size="sm"
                                    block="false"
                                />
                            </div>
                            <div class="flex items-center justify-between py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Frosch</span>
                                <x-ui-input-checkbox
                                    model="task.is_frog"
                                    checked-label=""
                                    unchecked-label=""
                                    size="sm"
                                    block="false"
                                />
                            </div>
                        </div>
                    </div>

                    {{-- Metrics --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Metriken</h3>
                        <div class="space-y-3">
                            @if($task->userInCharge)
                                <div class="py-3 px-4 bg-[var(--ui-info-5)] rounded-lg border-l-4 border-[var(--ui-info)]">
                                    <div class="text-xs text-[var(--ui-info)] font-medium uppercase tracking-wide">Verantwortlicher</div>
                                    <div class="text-lg font-bold text-[var(--ui-info)]">{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</div>
                                </div>
                            @endif
                            <div class="py-3 px-4 bg-[var(--ui-primary-5)] rounded-lg border-l-4 border-[var(--ui-primary)]">
                                <div class="text-xs text-[var(--ui-primary)] font-medium uppercase tracking-wide">Priorität</div>
                                <div class="text-lg font-bold text-[var(--ui-primary)]">{{ $task->priority?->label ?? 'Nicht gesetzt' }}</div>
                            </div>
                            @if($task->due_date)
                                <div class="py-3 px-4 bg-[var(--ui-warning-5)] rounded-lg border-l-4 border-[var(--ui-warning)]">
                                    <div class="text-xs text-[var(--ui-warning)] font-medium uppercase tracking-wide">Fälligkeitsdatum</div>
                                    <div class="text-lg font-bold text-[var(--ui-warning)]">{{ $task->due_date->format('d.m.Y') }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Detailliertes Debug Info für Teams --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">🔍 Detailliertes Debug</h3>
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
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Aufgabe geöffnet</div>
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

    {{-- Print Modal --}}
    @if($printingAvailable && $printModalShow)
        <x-ui-modal wire:model="printModalShow" title="Aufgabe drucken">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Druckziel auswählen</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" wire:model="printTarget" value="printer" class="mr-2">
                            <span>Einzelner Drucker</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" wire:model="printTarget" value="group" class="mr-2">
                            <span>Druckergruppe</span>
                        </label>
                    </div>
                </div>

                @if($printTarget === 'printer')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Drucker auswählen</label>
                        <select wire:model="selectedPrinterId" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">-- Drucker auswählen --</option>
                            @foreach($printers as $printer)
                                <option value="{{ $printer->id }}">{{ $printer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($printTarget === 'group')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Druckergruppe auswählen</label>
                        <select wire:model="selectedPrinterGroupId" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">-- Gruppe auswählen --</option>
                            @foreach($printerGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <x-ui-button variant="secondary" wire:click="closePrintModal">Abbrechen</x-ui-button>
                <x-ui-button 
                    variant="primary" 
                    wire:click="printTaskConfirm"
                    :disabled="($printTarget === 'printer' && !$selectedPrinterId) || ($printTarget === 'group' && !$selectedPrinterGroupId)"
                >
                    Drucken
                </x-ui-button>
            </x-slot>
        </x-ui-modal>
    @endif
</div>

<script>
(function() {
    console.log('🔍 Teams SDK Debug - Initialisierung');
    
    // Debug-Update-Funktion
    function updateDebugInfo(elementId, content) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = content;
        }
    }
    
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
                updateDebugInfo('teams-sdk-user', `❌ User Fehler: ${error.message}`);
            });
        } catch (error) {
            console.error('❌ Teams User Exception:', error);
            updateDebugInfo('teams-sdk-user', `❌ User Exception: ${error.message}`);
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
                
                // Teams Authentication Token abrufen
                window.microsoftTeams.authentication.getAuthToken({
                    resources: [window.location.origin],
                    silent: true
                }).then(function(token) {
                    console.log('🔍 Teams JWT Token erhalten:', token ? 'Ja' : 'Nein');
                    console.log('🔍 Token Preview:', token ? token.substring(0, 50) + '...' : 'Kein Token');
                    
                    updateDebugInfo('teams-sdk-auth-token', 
                        token ? 
                        `✅ Token verfügbar<br>Preview: ${token.substring(0, 30)}...<br>Länge: ${token.length} Zeichen` : 
                        '❌ Kein Token erhalten'
                    );
                    
                    if (token) {
                        // Token an alle nachfolgenden Requests anhängen
                        const originalFetch = window.fetch;
                        window.fetch = function(url, options = {}) {
                            options.headers = options.headers || {};
                            options.headers['Authorization'] = `Bearer ${token}`;
                            options.headers['X-Teams-Token'] = token;
                            console.log('🔍 Fetch Request mit Token:', url);
                            return originalFetch(url, options);
                        };
                        
                        // Livewire Requests mit Token versehen
                        if (window.Livewire) {
                            window.Livewire.hook('request', ({ fail, succeed, payload, component }) => {
                                payload.headers = payload.headers || {};
                                payload.headers['Authorization'] = `Bearer ${token}`;
                                payload.headers['X-Teams-Token'] = token;
                                console.log('🔍 Livewire Request mit Token:', payload);
                            });
                        }
                        
                        // Sofortige Token-Übertragung für aktuelle Seite
                        console.log('🔍 Sende Token sofort an Backend...');
                        fetch(window.location.href, {
                            method: 'GET',
                            headers: {
                                'Authorization': `Bearer ${token}`,
                                'X-Teams-Token': token,
                                'X-Teams-Embedded': 'true'
                            }
                        }).then(response => {
                            console.log('🔍 Token-Request Response:', response.status);
                            if (response.ok) {
                                console.log('✅ Token erfolgreich an Backend gesendet');
                                // Seite neu laden um Auth zu aktivieren
                                setTimeout(() => {
                                    console.log('🔄 Lade Seite neu für Auth-Aktivierung...');
                                    window.location.reload();
                                }, 1000);
                            }
                        }).catch(error => {
                            console.error('❌ Token-Request Fehler:', error);
                        });
                        
                        console.log('✅ Teams JWT Token für alle Requests konfiguriert');
                    } else {
                        console.warn('⚠️ Kein Teams JWT Token erhalten');
                    }
                }).catch(function(error) {
                    console.error('❌ Teams JWT Token Fehler:', error);
                    updateDebugInfo('teams-sdk-auth-token', `❌ Token Fehler: ${error.message}`);
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

