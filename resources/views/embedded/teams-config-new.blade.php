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

                                        const contentUrl = 'https://office.martin3r.me/planner/embedded/planner/projects/' + encodeURIComponent(projectId) + teamIdQuery;
                                        const websiteUrl = contentUrl;
                                        const entityId = 'planner-project-' + projectId;
                                        const projectName = (select && select.options[select.selectedIndex]) ? select.options[select.selectedIndex].textContent : ('Projekt ' + projectId);
                                        const displayName = 'PLANNER · ' + projectName;

                                        window.microsoftTeams.pages.config.setConfig({
                                            contentUrl: contentUrl,
                                            websiteUrl: websiteUrl,
                                            entityId: entityId,
                                            displayName: displayName
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

                // Projekte laden basierend auf Teams Context
                function loadProjects(context) {
                    const select = document.getElementById('projectSelect');
                    if (!select) return;

                    // Team ID aus Teams Context
                    const teamId = context?.team?.groupId || '';
                    
                    // API-URL mit Team ID
                    let apiUrl = '/planner/embedded/planner/api/projects';
                    if (teamId) {
                        apiUrl += '?teamId=' + encodeURIComponent(teamId);
                    }

                    console.log('Lade Projekte von:', apiUrl);

                    fetch(apiUrl)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Projekte geladen:', data);
                            
                            // Select leeren
                            select.innerHTML = '<option value="">– Bitte wählen –</option>';
                            
                            // Projekte hinzufügen
                            if (data.data && data.data.length > 0) {
                                data.data.forEach(project => {
                                    const option = document.createElement('option');
                                    option.value = project.id;
                                    option.textContent = project.name;
                                    select.appendChild(option);
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
                        })
                        .catch(error => {
                            console.error('Fehler beim Laden der Projekte:', error);
                            select.innerHTML = '<option value="">Fehler beim Laden</option>';
                        });
                }
            })();
        </script>
    </div>
@endsection
