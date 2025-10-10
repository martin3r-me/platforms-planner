@extends('platform::layouts.embedded')

@section('content')
    <div class="p-6">
        <h1 class="text-xl font-semibold text-[var(--ui-secondary)] mb-2">Teams Tab Konfiguration – Test</h1>
        <p class="text-sm text-[var(--ui-muted)] mb-4">Prüft die Einbettung und die Teams SDK-Initialisierung.</p>

        <div id="sdkStatus" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">
            SDK: wird geprüft…
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


