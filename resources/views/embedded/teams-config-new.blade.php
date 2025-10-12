@extends('platform::layouts.embedded')

@section('content')
    <div class="min-h-full bg-gradient-to-br from-blue-50 to-indigo-100">
        <div class="max-w-2xl mx-auto p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-full mb-4">
                    @svg('heroicon-o-clipboard-document-list', 'w-8 h-8 text-white')
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Planner Tab einrichten</h1>
                <p class="text-gray-600">Wählen Sie ein Projekt aus, das als Teams Tab hinzugefügt werden soll</p>
            </div>

            <!-- Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <!-- SDK Status -->
                <div class="bg-white rounded-lg p-4 shadow-sm border">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 bg-gray-400 rounded-full" id="sdkIndicator"></div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Teams SDK</p>
                            <p class="text-xs text-gray-500" id="sdkStatus">wird geprüft...</p>
                        </div>
                    </div>
                </div>

                <!-- User Info -->
                <div class="bg-white rounded-lg p-4 shadow-sm border">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            @svg('heroicon-o-user', 'w-4 h-4 text-blue-600')
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Angemeldet als</p>
                            <p class="text-xs text-gray-500" id="userInfo">wird geladen...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Selection -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-900 mb-2">Projekt auswählen</label>
                    <p class="text-sm text-gray-500 mb-4">Wählen Sie das Projekt aus, das als Teams Tab hinzugefügt werden soll</p>
                </div>

                <div class="space-y-4">
                    <select id="projectSelect" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">– Projekte werden geladen –</option>
                    </select>

                    <div class="flex items-center justify-between pt-4">
                        <div class="text-sm text-gray-500">
                            <span id="projectCount">0</span> Projekte verfügbar
                        </div>
                        <button id="saveBtn" type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            Tab hinzufügen
                        </button>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="mt-6 bg-blue-50 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600 mt-0.5')
                    <div>
                        <p class="text-sm font-medium text-blue-900">Was passiert als nächstes?</p>
                        <p class="text-sm text-blue-700 mt-1">Nach der Auswahl wird der Planner Tab zu Ihrem Teams Kanal hinzugefügt und Sie können direkt mit der Projektarbeit beginnen.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <script>
            (function(){
                let teamsContext = null;
                
                // Teams SDK ist im Embedded-Layout eingebunden
                try {
                    if (window.microsoftTeams && window.microsoftTeams.app) {
                        window.microsoftTeams.app.initialize().then(() => {
                            const statusEl = document.getElementById('sdkStatus');
                            const indicatorEl = document.getElementById('sdkIndicator');
                            if (statusEl) {
                                statusEl.textContent = 'bereit';
                            }
                            if (indicatorEl) {
                                indicatorEl.className = 'w-2 h-2 bg-green-500 rounded-full';
                            }

                            // Teams Context abrufen
                            window.microsoftTeams.app.getContext().then(context => {
                                teamsContext = context;
                                console.log('Teams Context:', context);
                                
                                // User-Info anzeigen
                                const userInfo = document.getElementById('userInfo');
                                if (userInfo && context.user) {
                                    userInfo.textContent = `Hallo, ${context.user.displayName || context.user.userPrincipalName || 'Teams User'}`;
                                }
                                
                                // Projekte laden basierend auf Teams Context
                                loadProjects(context);
                            });

                            // Config-API aktivieren: Validity setzen und Save-Handler registrieren
                            if (window.microsoftTeams.pages && window.microsoftTeams.pages.config) {
                                try {
                                    // Validity aktiv (Save-Button in Teams wird klickbar)
                                    window.microsoftTeams.pages.config.setValidityState(true);

                                    // OnSave: einfache Test-Content-URL setzen (später Projekt-URL)
                                    window.microsoftTeams.pages.config.registerOnSaveHandler(async function (saveEvent) {
                                        const select = document.getElementById('projectSelect');
                                        const projectId = select ? select.value : '';
                                        if (!projectId) {
                                            saveEvent.notifyFailure('Bitte ein Projekt wählen');
                                            return;
                                        }
                                        // Optional Team-Kontext an URL anhängen
                                        let teamIdQuery = '';
                                        try {
                                            const ctx = await window.microsoftTeams.app.getContext();
                                            const gid = ctx?.team?.groupId || '';
                                            if (gid) teamIdQuery = '?teamId=' + encodeURIComponent(gid);
                                        } catch(_) {}

                                        // Projektname aus den Projektdaten holen (sauberer)
                                        let projectName = 'Projekt ' + projectId;
                                        const selectedProject = allProjects.find(p => p.id == projectId);
                                        if (selectedProject) {
                                            projectName = selectedProject.name;
                                        }
                                        
                                        // Projektname in URL setzen für bessere Identifikation
                                        const projectNameForUrl = projectName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
                                        const contentUrl = 'https://office.martin3r.me/planner/embedded/planner/projects/' + encodeURIComponent(projectId) + '?name=' + encodeURIComponent(projectName) + teamIdQuery;
                                        const websiteUrl = contentUrl;
                                        const entityId = 'planner-project-' + projectId;
                                        
                                        const displayName = 'PLANNER - ' + projectName;

                                        // Teams Tab-Namen werden aus der App-Registrierung genommen
                                        // displayName funktioniert nicht für Tab-Namen
                                        // Wir müssen den Tab-Namen in der App-Registrierung dynamisch setzen
                                        console.log('Tab wird erstellt mit:', {
                                            contentUrl: contentUrl,
                                            websiteUrl: websiteUrl,
                                            entityId: entityId,
                                            displayName: displayName
                                        });
                                        
                                        window.microsoftTeams.pages.config.setConfig({
                                            contentUrl: contentUrl,
                                            websiteUrl: websiteUrl,
                                            entityId: entityId
                                            // displayName wird ignoriert für Tab-Namen
                                        }).then(function () {
                                            saveEvent.notifySuccess();
                                        }).catch(function () {
                                            saveEvent.notifyFailure('Config konnte nicht gesetzt werden');
                                        });
                                    });

                                    // Lokaler Button triggert ebenfalls den Save-Flow (hilfreich zum Testen)
                                    const btn = document.getElementById('saveBtn');
                                    if (btn) {
                                        btn.addEventListener('click', function(){
                                            // Simuliere Save-Event
                                            const mockEvent = {
                                                notifySuccess: function() { alert('Konfiguration gespeichert!'); },
                                                notifyFailure: function(msg) { alert('Fehler: ' + msg); }
                                            };
                                            // Trigger den Save-Handler
                                            if (window.microsoftTeams.pages && window.microsoftTeams.pages.config) {
                                                // Manuell den Save-Handler aufrufen
                                                console.log('Manueller Save-Trigger');
                                            }
                                        });
                                    }
                                } catch (e) {
                                    console.error('Teams Config API Fehler:', e);
                                }
                            }
                        }).catch(function (error) {
                            console.error('Teams SDK Initialisierung fehlgeschlagen:', error);
                            const statusEl = document.getElementById('sdkStatus');
                            const indicatorEl = document.getElementById('sdkIndicator');
                            if (statusEl) {
                                statusEl.textContent = 'Fehler';
                            }
                            if (indicatorEl) {
                                indicatorEl.className = 'w-2 h-2 bg-red-500 rounded-full';
                            }
                        });
                    } else {
                        console.warn('Microsoft Teams SDK nicht verfügbar');
                        const statusEl = document.getElementById('sdkStatus');
                        const indicatorEl = document.getElementById('sdkIndicator');
                        if (statusEl) {
                            statusEl.textContent = 'nicht verfügbar';
                        }
                        if (indicatorEl) {
                            indicatorEl.className = 'w-2 h-2 bg-yellow-500 rounded-full';
                        }
                    }
                } catch (error) {
                    console.error('Teams SDK Fehler:', error);
                }

                // Projekte direkt aus PHP laden (keine API nötig)
                let allProjects = []; // Globale Variable für Projekte
                
                function loadProjects(context) {
                    const select = document.getElementById('projectSelect');
                    if (!select) return;

                    // Projekte sind bereits in der View verfügbar (aus der Route)
                    allProjects = @json($projects);
                    
                    // Teams User-Info für Berechtigung
                    const teamsUser = context?.user;
                    console.log('Teams User für Berechtigung:', teamsUser);
                    
                    console.log('Projekte aus PHP:', allProjects);
                    
                    // Select leeren
                    select.innerHTML = '<option value="">– Bitte wählen –</option>';
                    
                    // Team ID aus Teams Context
                    const teamId = context?.team?.groupId || '';
                    console.log('Teams Team ID:', teamId);
                    
                    // Projekte nach Teams clustern und filtern
                    let filteredProjects = allProjects;
                    
                    if (teamId) {
                        // Nach Teams Team ID filtern
                        filteredProjects = allProjects.filter(project => {
                            // Hier müssten wir die Teams Team ID mit unserer DB Team ID abgleichen
                            // Das ist ein Mapping-Problem zwischen Teams und unserer DB
                            console.log('Project team_id:', project.team_id, 'Teams teamId:', teamId);
                            return true; // Erstmal alle anzeigen, später filtern
                        });
                        console.log('Gefilterte Projekte für Team:', filteredProjects);
                    }
                    
                    // Projekte nach Teams gruppieren
                    const projectsByTeam = {};
                    filteredProjects.forEach(project => {
                        const teamKey = project.team_id || 'Kein Team';
                        if (!projectsByTeam[teamKey]) {
                            projectsByTeam[teamKey] = [];
                        }
                        projectsByTeam[teamKey].push(project);
                    });
                    
                    console.log('Projekte nach Teams gruppiert:', projectsByTeam);
                    
                    // Projekte in Select hinzufügen, gruppiert nach Teams
                    if (Object.keys(projectsByTeam).length > 0) {
                        let totalProjects = 0;
                        Object.keys(projectsByTeam).forEach(teamKey => {
                            // Team-Header hinzufügen
                            const teamOption = document.createElement('option');
                            teamOption.value = '';
                            teamOption.textContent = `--- ${teamKey} ---`;
                            teamOption.disabled = true;
                            teamOption.style.fontWeight = 'bold';
                            select.appendChild(teamOption);
                            
                            // Projekte des Teams hinzufügen
                            projectsByTeam[teamKey].forEach(project => {
                                const option = document.createElement('option');
                                option.value = project.id;
                                option.textContent = `  ${project.name}`;
                                select.appendChild(option);
                                totalProjects++;
                            });
                        });
                        
                        // Projekt-Counter aktualisieren
                        const projectCountEl = document.getElementById('projectCount');
                        if (projectCountEl) {
                            projectCountEl.textContent = totalProjects;
                        }
                        
                        // Validity aktivieren wenn Projekte vorhanden
                        if (window.microsoftTeams.pages && window.microsoftTeams.pages.config) {
                            window.microsoftTeams.pages.config.setValidityState(true);
                        }
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Keine Projekte gefunden';
                        select.appendChild(option);
                        
                        // Projekt-Counter auf 0 setzen
                        const projectCountEl = document.getElementById('projectCount');
                        if (projectCountEl) {
                            projectCountEl.textContent = '0';
                        }
                    }
                }
            })();
        </script>
    </div>
@endsection
