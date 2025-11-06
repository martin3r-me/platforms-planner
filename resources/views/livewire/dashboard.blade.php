<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dashboard" icon="heroicon-o-home" />
    </x-slot>

    <x-ui-page-container>


            {{-- Main Stats Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-ui-dashboard-tile
                    title="Aktive Projekte"
                    :count="$activeProjects"
                    subtitle="von {{ $totalProjects }}"
                    icon="folder"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Offene Aufgaben"
                    :count="$openTasks"
                    subtitle="von {{ $totalTasks }}"
                    icon="clock"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Erledigte Aufgaben"
                    :count="$completedTasks"
                    subtitle="diesen Monat: {{ $monthlyCompletedTasks }}"
                    icon="check-circle"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Gearbeitete Stunden"
                    :count="round($totalLoggedMinutes / 60, 1)"
                    subtitle="Monat: {{ number_format($monthlyLoggedMinutes / 60, 1, ',', '.') }} h"
                    icon="clock"
                    variant="secondary"
                    size="lg"
                />
            </div>

            {{-- Detail Stats --}}
            <x-ui-detail-stats-grid cols="2" gap="6">
                <x-slot:left>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Aufgaben-Übersicht</h3>
                    <x-ui-form-grid :cols="2" :gap="3">
                        <x-ui-dashboard-tile title="Frösche" :count="$frogTasks" icon="exclamation-triangle" variant="danger" size="sm" />
                        <x-ui-dashboard-tile title="Überfällig" :count="$overdueTasks" icon="exclamation-circle" variant="danger" size="sm" />
                        <x-ui-dashboard-tile title="Erstellt (Monat)" :count="$monthlyCreatedTasks" icon="plus-circle" variant="neutral" size="sm" />
                        <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedTasks" icon="check-circle" variant="success" size="sm" />
                    </x-ui-form-grid>
                </x-slot:left>
                <x-slot:right>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Story Points Performance</h3>
                    <x-ui-form-grid :cols="2" :gap="3">
                        <x-ui-dashboard-tile title="Offen" :count="$openStoryPoints" icon="clock" variant="warning" size="sm" />
                        <x-ui-dashboard-tile title="Erledigt" :count="$completedStoryPoints" icon="check-circle" variant="success" size="sm" />
                        <x-ui-dashboard-tile title="Erstellt (Monat)" :count="$monthlyCreatedPoints" icon="plus-circle" variant="neutral" size="sm" />
                        <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedPoints" icon="check-circle" variant="success" size="sm" />
                    </x-ui-form-grid>
                    <h3 class="mt-6 text-lg font-semibold text-[var(--ui-secondary)] mb-4">Zeit-Übersicht</h3>
                    <x-ui-form-grid :cols="2" :gap="3">
                        <x-ui-dashboard-tile
                            title="Abgerechnet"
                            :count="round($billedMinutes / 60, 1)"
                            :subtitle="'Monat: ' . number_format($monthlyBilledMinutes / 60, 1, ',', '.') . ' h'"
                            icon="check-circle"
                            variant="success"
                            size="sm"
                        />
                        <x-ui-dashboard-tile
                            title="Offen"
                            :count="round($unbilledMinutes / 60, 1)"
                            :subtitle="$unbilledAmountCents ? 'Offene Stunden: ' . number_format($unbilledMinutes / 60, 1, ',', '.') . ' h • Wert: ' . number_format($unbilledAmountCents / 100, 2, ',', '.') . ' €' : 'Noch keine offenen Werte'"
                            icon="exclamation-circle"
                            variant="warning"
                            size="sm"
                        />
                    </x-ui-form-grid>
                </x-slot:right>
            </x-ui-detail-stats-grid>

            <x-ui-panel class="mb-8" title="Team-Mitglieder Übersicht" subtitle="Aufgaben und Story Points pro Team-Mitglied">
                <x-ui-team-members-list :members="$teamMembers" />
            </x-ui-panel>

            <x-ui-panel title="Meine aktiven Projekte" subtitle="Top 5 Projekte nach offenen Aufgaben">
                <x-ui-project-list :projects="$activeProjectsList" projectRoute="planner.projects.show" />
            </x-ui-panel>
    </x-ui-page-container>

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
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Heute erstellt</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $todayCreatedTasks ?? 0 }} Aufgaben</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Heute erledigt</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $todayCompletedTasks ?? 0 }} Aufgaben</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Offene Frösche</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $frogTasks }} Aufgaben</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Offene Stunden</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ number_format($unbilledMinutes / 60, 2, ',', '.') }} h</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Offener Wert</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ number_format($unbilledAmountCents / 100, 2, ',', '.') }} €</div>
                        </div>
                    </div>
                </div>

                {{-- Recent Activity (Dummy) --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-2 text-sm">
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Dashboard geladen</div>
                            <div class="text-[var(--ui-muted)] text-xs">vor 1 Minute</div>
                        </div>
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Aufgabe erstellt</div>
                            <div class="text-[var(--ui-muted)] text-xs">vor 2 Stunden</div>
                        </div>
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Projekt aktualisiert</div>
                            <div class="text-[var(--ui-muted)] text-xs">vor 4 Stunden</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Dashboard geladen</div>
                        <div class="text-[var(--ui-muted)]">vor 1 Minute</div>
                    </div>
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Aufgabe erstellt</div>
                        <div class="text-[var(--ui-muted)]">vor 2 Stunden</div>
                    </div>
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Projekt aktualisiert</div>
                        <div class="text-[var(--ui-muted)]">vor 4 Stunden</div>
                    </div>
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Aufgabe erledigt</div>
                        <div class="text-[var(--ui-muted)]">vor 6 Stunden</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>