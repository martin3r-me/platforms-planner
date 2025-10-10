@extends('platform::layouts.embedded')

@section('content')
    <div class="p-6">
        <h1 class="text-xl font-semibold text-[var(--ui-secondary)] mb-2">Teams Tab Konfiguration – Test</h1>
        <p class="text-sm text-[var(--ui-muted)] mb-4">Prüft die Einbettung und die Teams SDK-Initialisierung.</p>

        <div id="sdkStatus" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">
            SDK: wird geprüft…
        </div>

        <div class="mt-6">
            <button id="saveBtn" type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-[var(--ui-border)] bg-white hover:bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">Speichern</button>
        </div>

        <script>
            (function(){
                // Teams SDK ist im Embedded-Layout eingebunden
                try {
                    if (window.microsoftTeams && window.microsoftTeams.app) {
                        window.microsoftTeams.app.initialize().then(() => {
                            const el = document.getElementById('sdkStatus');
                            if (el) {
                                el.textContent = 'SDK: bereit';
                                el.className = 'inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-green-200 bg-green-50 text-green-700';
                            }

                            // Config-API aktivieren: Validity setzen und Save-Handler registrieren
                            if (window.microsoftTeams.pages && window.microsoftTeams.pages.config) {
                                try {
                                    // Validity aktiv (Save-Button in Teams wird klickbar)
                                    window.microsoftTeams.pages.config.setValidityState(true);

                                    // OnSave: einfache Test-Content-URL setzen (später Projekt-URL)
                                    window.microsoftTeams.pages.config.registerOnSaveHandler(function (saveEvent) {
                                        const contentUrl = 'https://office.martin3r.me/planner/embedded/test';
                                        const websiteUrl = contentUrl;
                                        const entityId = 'planner-embedded-test';
                                        const displayName = 'Planner (Embedded)';

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
                                            // Teams zeigt Save oben – wir simulieren hier keinen direkten Abschluss,
                                            // sondern informieren den Nutzer, den Teams-Speichern-Button zu klicken.
                                            btn.textContent = 'Bereit – bitte oben in Teams auf Speichern klicken';
                                        });
                                    }
                                } catch (e) {
                                    // ignore config errors in non-Teams context
                                }
                            }
                        }).catch(() => {
                            const el = document.getElementById('sdkStatus');
                            if (el) {
                                el.textContent = 'SDK: Fehler bei initialize()';
                                el.className = 'inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-red-200 bg-red-50 text-red-700';
                            }
                        });
                    } else {
                        const el = document.getElementById('sdkStatus');
                        if (el) {
                            el.textContent = 'SDK: nicht geladen (vermutlich außerhalb von Teams)';
                            el.className = 'inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-yellow-200 bg-yellow-50 text-yellow-700';
                        }
                    }
                } catch (e) {
                    const el = document.getElementById('sdkStatus');
                    if (el) {
                        el.textContent = 'SDK: Ausnahme aufgetreten';
                        el.className = 'inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-red-200 bg-red-50 text-red-700';
                    }
                }
            })();
        </script>
    </div>
@endsection


