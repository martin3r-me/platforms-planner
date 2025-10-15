@extends('platform::layouts.embedded')

@section('content')
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
        <div class="max-w-2xl mx-auto p-6">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 rounded-full mb-3">
                    @svg('heroicon-o-clipboard-document-list', 'w-6 h-6 text-white')
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Planner Tab einrichten</h1>
                <p class="text-gray-600 text-sm">Wähle zuerst ein Team, dann ein Projekt</p>
            </div>

            <!-- Teams Auswahl -->
            <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Team auswählen</label>
                    <p class="text-xs text-gray-500">Nur Teams, denen du angehörst</p>
                </div>
                <div id="teamGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @forelse(($teams ?? collect()) as $team)
                        <button type="button" class="team-tile flex items-center justify-center p-3 rounded-lg border border-gray-200 bg-white hover:border-blue-500 text-sm"
                                data-team-id="{{ $team->id }}" data-team-name="{{ $team->name }}">
                            <span class="truncate">{{ $team->name }}</span>
                        </button>
                    @empty
                        <div class="text-xs text-gray-500">Keine Teams gefunden.</div>
                    @endforelse
                </div>
            </div>

            <!-- Project Selection -->
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Projekt auswählen</label>
                        <p class="text-xs text-gray-500">Nur Projekte aus dem gewählten Team</p>
                    </div>
                    <button id="newProjectBtn" type="button" class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neues Projekt
                    </button>
                </div>

                <div class="space-y-4">
                    <select id="projectSelect" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">– Bitte erst ein Team wählen –</option>
                    </select>

                    <div class="flex items-center justify-between pt-2">
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

            <!-- Neues Projekt Modal (einfach) -->
            <div id="newProjectModal" class="hidden fixed inset-0 z-50 bg-black/30">
                <div class="absolute inset-0 flex items-center justify-center p-4">
                    <div class="w-full max-w-sm rounded-lg bg-white border shadow p-4 space-y-3">
                        <div class="text-sm font-medium text-gray-900">Neues Projekt anlegen</div>
                        <div class="space-y-2">
                            <label class="block text-xs text-gray-600">Team</label>
                            <select id="newProjectTeam" class="w-full rounded border border-gray-300 px-3 py-2 text-sm bg-white">
                                @foreach(($teams ?? collect()) as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs text-gray-600">Projektname</label>
                            <input id="newProjectName" type="text" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" placeholder="Projektname" />
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" id="newProjectCancel" class="px-3 py-1.5 text-xs rounded border border-gray-300">Abbrechen</button>
                            <button type="button" id="newProjectCreate" class="px-3 py-1.5 text-xs rounded bg-blue-600 text-white">Anlegen</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="mt-4 bg-blue-50 rounded-lg p-3">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-4 h-4 text-blue-600 mt-0.5')
                    <div>
                        <p class="text-xs font-medium text-blue-900">Was passiert als nächstes?</p>
                        <p class="text-xs text-blue-700 mt-1">Nach der Auswahl wird der Planner Tab zu deinem Teams Kanal hinzugefügt.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        // Projekte/Teams aus Blade
        let allProjects = @json($projects);
        let selectedTeamId = null;

        // Team-Kacheln aktivieren
        const teamTiles = document.querySelectorAll('.team-tile');
        const projectSelect = document.getElementById('projectSelect');
        const projectCountEl = document.getElementById('projectCount');

        teamTiles.forEach(tile => {
            tile.addEventListener('click', function(){
                // Active-State
                teamTiles.forEach(t => t.classList.remove('ring-2','ring-blue-500'));
                this.classList.add('ring-2','ring-blue-500');
                selectedTeamId = this.getAttribute('data-team-id');
                // Projekte neu filtern
                renderProjects();
            });
        });

        function renderProjects(){
            projectSelect.innerHTML = '';
            let filtered = allProjects.filter(p => String(p.team_id) === String(selectedTeamId));
            if (!selectedTeamId) {
                projectSelect.innerHTML = '<option value="">– Bitte erst ein Team wählen –</option>';
                if (projectCountEl) projectCountEl.textContent = '0';
                return;
            }
            if (filtered.length === 0) {
                projectSelect.innerHTML = '<option value="">Keine Projekte im Team</option>';
            } else {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = '– Bitte wählen –';
                projectSelect.appendChild(placeholder);
                filtered.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id; opt.textContent = p.name;
                    projectSelect.appendChild(opt);
                });
            }
            if (projectCountEl) projectCountEl.textContent = String(filtered.length);
        }

        // Neues Projekt Modal Logik
        const modal = document.getElementById('newProjectModal');
        const btnOpen = document.getElementById('newProjectBtn');
        const btnCancel = document.getElementById('newProjectCancel');
        const btnCreate = document.getElementById('newProjectCreate');
        const inputName = document.getElementById('newProjectName');
        const selectTeam = document.getElementById('newProjectTeam');

        btnOpen?.addEventListener('click', function(){
            if (selectedTeamId) selectTeam.value = selectedTeamId;
            modal.classList.remove('hidden');
            inputName.value = '';
            inputName.focus();
        });
        btnCancel?.addEventListener('click', function(){ modal.classList.add('hidden'); });
        modal?.addEventListener('click', function(e){ if (e.target === modal) modal.classList.add('hidden'); });

        btnCreate?.addEventListener('click', async function(){
            const name = inputName.value.trim();
            const teamId = selectTeam.value;
            if (!name) { alert('Bitte einen Projektnamen eingeben'); return; }
            if (!teamId) { alert('Bitte ein Team wählen'); return; }
            try {
                const res = await fetch('/planner/embedded/planner/projects', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ name: name, team_id: teamId })
                });
                if (!res.ok) {
                    const t = await res.text();
                    alert('Fehler: ' + t);
                    return;
                }
                const data = await res.json();
                // In Liste aufnehmen und auswählen
                allProjects.push(data.project);
                selectedTeamId = String(teamId);
                renderProjects();
                projectSelect.value = data.project.id;
                modal.classList.add('hidden');
            } catch (e) {
                alert('Request-Fehler: ' + e.message);
            }
        });

        // Teams SDK Config Save-Handler (wie zuvor)
        if (window.microsoftTeams?.pages?.config) {
            window.microsoftTeams.pages.config.setValidityState(true);
            window.microsoftTeams.pages.config.registerOnSaveHandler(async function (saveEvent) {
                const projectId = projectSelect?.value || '';
                if (!projectId) {
                    saveEvent.notifyFailure('Bitte Projekt wählen');
                    return;
                }
                // Projektname finden
                let projectName = 'Projekt ' + projectId;
                const selected = allProjects.find(p => String(p.id) === String(projectId));
                if (selected) projectName = selected.name;

                const contentUrl = 'https://office.martin3r.me/planner/embedded/planner/projects/' + encodeURIComponent(projectId) + '?name=' + encodeURIComponent(projectName);
                window.microsoftTeams.pages.config.setConfig({
                    contentUrl: contentUrl,
                    websiteUrl: contentUrl,
                    entityId: 'planner-project-' + projectId
                }).then(function(){ saveEvent.notifySuccess(); })
                 .catch(function(){ saveEvent.notifyFailure('Config konnte nicht gesetzt werden'); });
            });
        }
    })();
    </script>
@endsection
