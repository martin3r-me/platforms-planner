<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dashboard" icon="heroicon-o-home">
            <x-slot name="titleActions">
                <x-ui-segmented-toggle 
                    model="perspective"
                    :current="$perspective"
                    :options="[
                        ['value' => 'personal', 'label' => 'Persönlich', 'icon' => 'heroicon-o-user'],
                        ['value' => 'team', 'label' => 'Team', 'icon' => 'heroicon-o-users'],
                    ]"
                    active-variant="success"
                    size="sm"
                />
            </x-slot>
            <div class="text-sm text-[var(--ui-muted)]">{{ $currentDay }}, {{ $currentDate }}</div>
        </x-ui-page-navbar>
    </x-slot>

    <div class="flex-1 overflow-y-auto bg-gray-50/30">
        <div class="max-w-7xl mx-auto px-8 py-8 space-y-8">
            {{-- Info Banner --}}
            @if($perspective === 'personal')
                <x-ui-info-banner 
                    icon="heroicon-o-user"
                    title="Persönliche Übersicht"
                    message="Deine persönlichen Aufgaben und zuständigen Projektaufgaben im aktuellen Sprint."
                    variant="info"
                />
            @else
                <x-ui-info-banner 
                    icon="heroicon-o-users"
                    title="Team-Übersicht"
                    message="Alle Aufgaben des Teams in aktiven Projekten und Sprints."
                    variant="success"
                />
            @endif

            {{-- Main Stats Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-ui-dashboard-tile
                    title="Aktive Projekte"
                    :count="$activeProjects"
                    subtitle="von {{ $totalProjects }}"
                    icon="folder"
                    variant="primary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Offene Aufgaben"
                    :count="$openTasks"
                    subtitle="von {{ $totalTasks }}"
                    icon="clock"
                    variant="warning"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Erledigte Aufgaben"
                    :count="$completedTasks"
                    subtitle="diesen Monat: {{ $monthlyCompletedTasks }}"
                    icon="check-circle"
                    variant="success"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Story Points"
                    :count="$openStoryPoints"
                    subtitle="erledigt: {{ $completedStoryPoints }}"
                    icon="chart-bar"
                    variant="info"
                    size="lg"
                />
            </div>

            {{-- Detail Stats --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {{-- Aufgaben-Übersicht --}}
                <div class="bg-white rounded-lg border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Aufgaben-Übersicht</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-dashboard-tile title="Frösche" :count="$frogTasks" icon="exclamation-triangle" variant="danger" size="sm" />
                        <x-ui-dashboard-tile title="Überfällig" :count="$overdueTasks" icon="exclamation-circle" variant="danger" size="sm" />
                        <x-ui-dashboard-tile title="Erstellt (Monat)" :count="$monthlyCreatedTasks" icon="plus-circle" variant="neutral" size="sm" />
                        <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedTasks" icon="check-circle" variant="success" size="sm" />
                    </div>
                </div>

                {{-- Story Points Performance --}}
                <div class="bg-white rounded-lg border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Story Points Performance</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-dashboard-tile title="Offen" :count="$openStoryPoints" icon="clock" variant="warning" size="sm" />
                        <x-ui-dashboard-tile title="Erledigt" :count="$completedStoryPoints" icon="check-circle" variant="success" size="sm" />
                        <x-ui-dashboard-tile title="Erstellt (Monat)" :count="$monthlyCreatedPoints" icon="plus-circle" variant="neutral" size="sm" />
                        <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedPoints" icon="check-circle" variant="success" size="sm" />
                    </div>
                </div>
            </div>

            {{-- Team Members --}}
            <div class="bg-white rounded-lg border border-gray-100 p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Team-Mitglieder Übersicht</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Aufgaben und Story Points pro Team-Mitglied</p>
                </div>
                <x-ui-team-members-list :members="$teamMembers" />
            </div>

            {{-- Active Projects --}}
            <div class="bg-white rounded-lg border border-gray-100 p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Meine aktiven Projekte</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Top 5 Projekte nach offenen Aufgaben</p>
                </div>
                <x-ui-project-list :projects="$activeProjectsList" projectRoute="planner.projects.show" />
            </div>
        </div>
    </div>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Quick Actions --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('planner.my-tasks')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                                Meine Aufgaben
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Quick Stats --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Schnellstatistiken</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-secondary)]">Heute erstellt</span>
                            <span class="text-sm font-semibold text-[var(--ui-primary)]">{{ $todayCreatedTasks ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-secondary)]">Heute erledigt</span>
                            <span class="text-sm font-semibold text-[var(--ui-success)]">{{ $todayCompletedTasks ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-secondary)]">Offene Frösche</span>
                            <span class="text-sm font-semibold text-[var(--ui-danger)]">{{ $frogTasks }}</span>
                        </div>
                    </div>
                </div>

                {{-- Recent Activity --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-2 text-sm">
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40">
                            <div class="font-medium text-[var(--ui-secondary)]">Aufgabe erstellt</div>
                            <div class="text-[var(--ui-muted)] text-xs">vor 2 Stunden</div>
                        </div>
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40">
                            <div class="font-medium text-[var(--ui-secondary)]">Projekt aktualisiert</div>
                            <div class="text-[var(--ui-muted)] text-xs">vor 4 Stunden</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>