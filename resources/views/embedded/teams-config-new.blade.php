@extends('platform::layouts.embedded')

@section('content')
    <div class="p-6">
        <h1 class="text-xl font-semibold text-[var(--ui-secondary)] mb-2">Teams Tab Konfiguration – Test</h1>
        <p class="text-sm text-[var(--ui-muted)] mb-1">Prüft die Einbettung und die Teams SDK-Initialisierung.</p>
        <p class="text-sm text-[var(--ui-secondary)] mb-4" id="userInfo">
            Teams User wird geladen...
        </p>

        <div id="sdkStatus" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">
            SDK: wird geprüft…
        </div>

        <div class="mt-6 space-y-3">
            <label class="block text-sm text-[var(--ui-secondary)]">Projekt wählen</label>
            <select id="projectSelect" class="w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm">
                <option value="">– Projekte werden geladen –</option>
            </select>

            <button id="saveBtn" type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-[var(--ui-border)] bg-white hover:bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">Speichern</button>
        </div>

        <script>
            (function(){
                let teamsContext = null;
                
                // Teams SDK ist im Embedded-Layout eingebunden
                try {
                    if (window.microsoftTeams && window.microsoftTeams.app) {
                        window.microsoftTeams.app.initialize().then(() => {
                            const el = document.getElementById('sdkStatus');
                            if (el) {
                                el.textContent = 'SDK: bereit';
                                el.className = 'inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-green-200 bg-green-50 text-green-700';
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
                            const el = document.getElementById('sdkStatus');
                            if (el) {
                                el.textContent = 'SDK: Fehler';
                                el.className = 'inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-red-200 bg-red-50 text-red-700';
                            }
                        });
                    } else {
                        console.warn('Microsoft Teams SDK nicht verfügbar');
                        const el = document.getElementById('sdkStatus');
                        if (el) {
                            el.textContent = 'SDK: nicht verfügbar';
                            el.className = 'inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-yellow-200 bg-yellow-50 text-yellow-700';
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
                            });
                        });
                        
                        // Validity aktivieren wenn Projekte vorhanden
                        if (window.microsoftTeams.pages && window.microsoftTeams.pages.config) {
                            window.microsoftTeams.pages.config.setValidityState(true);
                        }
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Keine Projekte gefunden';
                        select.appendChild(option);
                    }
                }
            })();
        </script>
    </div>
@endsection
