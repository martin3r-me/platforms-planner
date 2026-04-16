<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dashboard" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Projekte', 'icon' => 'clipboard-document-list'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        {{-- Hero Tiles (4er Grid) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <x-ui-dashboard-tile
                title="Aktive Projekte"
                :count="$activeProjects"
                :subtitle="'von ' . $totalProjects . ' gesamt'"
                icon="folder"
                variant="secondary"
                size="lg"
            />
            <x-ui-dashboard-tile
                title="Offene Aufgaben"
                :count="$openTasks"
                :description="$overdueTasksCount > 0 ? $overdueTasksCount . ' überfällig' : 'keine überfälligen'"
                icon="clock"
                :variant="$overdueTasksCount > 0 ? 'danger' : 'secondary'"
                size="lg"
            />
            <x-ui-dashboard-tile
                title="Erledigt (Monat)"
                :count="$monthlyCompletedTasks"
                :trend="$monthlyCompletedTrend['direction']"
                :trendValue="$monthlyCompletedTrend['direction'] ? $monthlyCompletedTrend['percent'] . '% vs. Vormonat' : null"
                icon="check-circle"
                variant="success"
                size="lg"
            />
            <x-ui-dashboard-tile
                title="Stunden (Monat)"
                :count="round($monthlyLoggedMinutes / 60, 1)"
                :trend="$monthlyHoursTrend['direction']"
                :trendValue="$monthlyHoursTrend['direction'] ? $monthlyHoursTrend['percent'] . '% vs. Vormonat' : null"
                icon="clock"
                variant="secondary"
                size="lg"
            />
        </div>

        {{-- Überfällige Tasks + Anstehende Deadlines --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Überfällige Tasks --}}
            <x-ui-panel
                title="Überfällige Aufgaben"
                :subtitle="$overdueTasksList->count() . ' von ' . $overdueTasksCount . ' angezeigt'"
            >
                <div class="space-y-2">
                    @forelse($overdueTasksList as $task)
                        @php
                            $daysOverdue = now()->startOfDay()->diffInDays($task->due_date->startOfDay());
                            $taskUrl = route('planner.tasks.show', ['plannerTask' => $task->id]);
                        @endphp
                        <a href="{{ $taskUrl }}" class="flex items-center gap-3 p-3 rounded-md border border-[color:var(--ui-danger-20)] bg-[color:var(--ui-danger-5)] hover:bg-[color:var(--ui-danger-10)] transition">
                            <div class="shrink-0 text-[color:var(--ui-danger)]">
                                @svg('heroicon-o-exclamation-circle', 'w-5 h-5')
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-[color:var(--ui-secondary)] truncate">
                                    {{ $task->title }}
                                </div>
                                <div class="text-xs text-[color:var(--ui-muted)] truncate">
                                    @if($task->project)
                                        <span class="text-[color:var(--ui-secondary)]/70">{{ $task->project->name }}</span> •
                                    @endif
                                    fällig {{ $task->due_date->format('d.m.Y') }}
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="text-xs font-semibold text-[color:var(--ui-danger)]">
                                    {{ (int) $daysOverdue }}&nbsp;T überfällig
                                </div>
                                @if($task->userInCharge)
                                    <div class="text-xs text-[color:var(--ui-muted)] truncate max-w-[140px]">
                                        {{ $task->userInCharge->name }}
                                    </div>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="p-3 text-sm text-[color:var(--ui-muted)] bg-white rounded-md border border-[color:var(--ui-border)]">
                            Keine überfälligen Aufgaben. Saubere Arbeit!
                        </div>
                    @endforelse
                </div>
            </x-ui-panel>

            {{-- Anstehende Deadlines --}}
            <x-ui-panel
                title="Anstehende Deadlines"
                subtitle="Nächste 7 Tage"
            >
                <div class="space-y-2">
                    @forelse($upcomingTasksList as $task)
                        @php
                            $daysLeft = now()->startOfDay()->diffInDays($task->due_date->startOfDay(), false);
                            $taskUrl = route('planner.tasks.show', ['plannerTask' => $task->id]);
                            $urgencyVariant = $daysLeft <= 1 ? 'warning' : 'secondary';
                        @endphp
                        <a href="{{ $taskUrl }}" class="flex items-center gap-3 p-3 rounded-md border border-[color:var(--ui-border)] bg-white hover:bg-[color:var(--ui-muted-5)] transition">
                            <div class="shrink-0 text-[color:var(--ui-{{ $urgencyVariant }})]">
                                @svg('heroicon-o-calendar', 'w-5 h-5')
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-[color:var(--ui-secondary)] truncate">
                                    {{ $task->title }}
                                </div>
                                <div class="text-xs text-[color:var(--ui-muted)] truncate">
                                    @if($task->project)
                                        <span class="text-[color:var(--ui-secondary)]/70">{{ $task->project->name }}</span> •
                                    @endif
                                    {{ $task->due_date->format('d.m.Y') }}
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="text-xs font-semibold text-[color:var(--ui-{{ $urgencyVariant }})]">
                                    @if($daysLeft == 0)
                                        heute
                                    @elseif($daysLeft == 1)
                                        morgen
                                    @else
                                        in {{ (int) $daysLeft }}&nbsp;T
                                    @endif
                                </div>
                                @if($task->userInCharge)
                                    <div class="text-xs text-[color:var(--ui-muted)] truncate max-w-[140px]">
                                        {{ $task->userInCharge->name }}
                                    </div>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="p-3 text-sm text-[color:var(--ui-muted)] bg-white rounded-md border border-[color:var(--ui-border)]">
                            Keine Deadlines in den nächsten 7 Tagen.
                        </div>
                    @endforelse
                </div>
            </x-ui-panel>
        </div>

        {{-- Projekte mit Fortschritt --}}
        <x-ui-panel title="Projekte mit Fortschritt" :subtitle="$projectsWithProgress->count() . ' aktive Projekte'">
            <div class="flex justify-end mb-3">
                <button
                    wire:click="toggleCompletedProjects"
                    type="button"
                    class="text-xs font-normal text-[color:var(--ui-muted)] hover:text-[color:var(--ui-secondary)] underline"
                >
                    {{ $showCompletedProjects ? 'Abgeschlossene ausblenden' : 'Abgeschlossene anzeigen (' . $recentlyCompletedWithProgress->count() . ')' }}
                </button>
            </div>

            <div class="space-y-2">
                @forelse($projectsWithProgress as $project)
                    @php
                        $href = route('planner.projects.show', ['plannerProject' => $project['id']]);
                        $progress = $project['progress_percent'];
                        $progressColor = $progress >= 75 ? 'success' : ($progress >= 40 ? 'secondary' : 'warning');
                    @endphp
                    <a href="{{ $href }}" class="block p-3 rounded-md border border-[color:var(--ui-border)] bg-white hover:bg-[color:var(--ui-muted-5)] transition">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] rounded flex items-center justify-center shrink-0">
                                @svg('heroicon-o-folder', 'w-5 h-5')
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-[color:var(--ui-secondary)] truncate">{{ $project['name'] }}</div>
                                <div class="text-xs text-[color:var(--ui-muted)] flex items-center gap-2">
                                    @if($project['project_type_label'])
                                        <span class="px-1.5 py-0.5 rounded bg-[color:var(--ui-muted-10)] text-[color:var(--ui-secondary)]">
                                            {{ $project['project_type_label'] }}
                                        </span>
                                    @endif
                                    <span>{{ $project['completed_tasks'] }}/{{ $project['total_tasks'] }} Aufgaben</span>
                                    @if($project['story_points'] > 0)
                                        <span>{{ $project['story_points'] }} SP</span>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                <div class="font-semibold text-[color:var(--ui-secondary)]">
                                    {{ number_format($project['logged_minutes'] / 60, 1, ',', '.') }}h
                                </div>
                                @if($project['monthly_minutes'] > 0)
                                    <div class="text-xs text-[color:var(--ui-muted)]">
                                        Monat: {{ number_format($project['monthly_minutes'] / 60, 1, ',', '.') }}h
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-2 bg-[color:var(--ui-muted-10)] rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-[color:var(--ui-{{ $progressColor }})] transition-all"
                                    style="width: {{ $progress }}%"
                                ></div>
                            </div>
                            <div class="text-xs font-semibold text-[color:var(--ui-{{ $progressColor }})] shrink-0 w-12 text-right">
                                {{ $progress }}%
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-3 text-sm text-[color:var(--ui-muted)] bg-white rounded-md border border-[color:var(--ui-border)]">
                        Keine aktiven Projekte gefunden.
                    </div>
                @endforelse

                @if($showCompletedProjects && $recentlyCompletedWithProgress->count() > 0)
                    <div class="pt-4 mt-4 border-t border-[color:var(--ui-border)]">
                        <div class="text-xs uppercase tracking-wider text-[color:var(--ui-muted)] font-bold mb-2">
                            Kürzlich abgeschlossen (30 Tage)
                        </div>
                        @foreach($recentlyCompletedWithProgress as $project)
                            @php
                                $href = route('planner.projects.show', ['plannerProject' => $project['id']]);
                            @endphp
                            <a href="{{ $href }}" class="block p-3 rounded-md border border-[color:var(--ui-success-20)] bg-[color:var(--ui-success-5)] hover:bg-[color:var(--ui-success-10)] transition mb-2 opacity-80">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-[color:var(--ui-success)] text-white rounded flex items-center justify-center shrink-0">
                                        @svg('heroicon-o-check-circle', 'w-5 h-5')
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-[color:var(--ui-secondary)] truncate">{{ $project['name'] }}</div>
                                        <div class="text-xs text-[color:var(--ui-muted)]">
                                            Abgeschlossen
                                            @if($project['done_at'])
                                                {{ $project['done_at']->format('d.m.Y') }}
                                            @endif
                                            • {{ $project['total_tasks'] }} Aufgaben
                                            • {{ number_format($project['logged_minutes'] / 60, 1, ',', '.') }}h
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ui-panel>

        {{-- Team-Übersicht --}}
        <x-ui-panel title="Team-Übersicht" subtitle="Fortschritt und Auslastung pro Mitglied">
            <div class="space-y-2">
                @forelse($teamMembers as $member)
                    <div class="flex items-center gap-3 p-3 rounded-md border border-[color:var(--ui-border)] bg-white">
                        @if(!empty($member['profile_photo_url']))
                            <img src="{{ $member['profile_photo_url'] }}" alt="{{ $member['name'] }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                        @else
                            <div class="w-8 h-8 rounded-full bg-[color:var(--ui-primary-10)] text-[color:var(--ui-primary)] font-semibold flex items-center justify-center shrink-0">
                                {{ strtoupper(mb_substr($member['name'] ?? '??', 0, 2)) }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-[color:var(--ui-secondary)] truncate">
                                {{ $member['name'] ?? 'Mitglied' }}
                            </div>
                            <div class="flex items-center gap-3 mt-1">
                                <div class="flex-1 h-1.5 bg-[color:var(--ui-muted-10)] rounded-full overflow-hidden max-w-xs">
                                    <div
                                        class="h-full bg-[color:var(--ui-success)] transition-all"
                                        style="width: {{ $member['progress_percent'] }}%"
                                    ></div>
                                </div>
                                <span class="text-xs text-[color:var(--ui-muted)]">
                                    {{ $member['completed_tasks'] }}/{{ $member['total_tasks'] }}
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center gap-4 text-xs text-[color:var(--ui-muted)]">
                            <div class="text-right">
                                <div class="font-semibold text-[color:var(--ui-secondary)]">{{ $member['open_tasks'] }}</div>
                                <div>offen</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-[color:var(--ui-secondary)]">{{ $member['open_story_points'] }}</div>
                                <div>SP</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-[color:var(--ui-secondary)]">{{ number_format($member['monthly_minutes'] / 60, 1, ',', '.') }}h</div>
                                <div>Monat</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-3 text-sm text-[color:var(--ui-muted)] bg-white rounded-md border border-[color:var(--ui-border)]">
                        Keine Team-Mitglieder gefunden.
                    </div>
                @endforelse
            </div>
        </x-ui-panel>

        {{-- Story Points & Zeit --}}
        <x-ui-detail-stats-grid cols="2" gap="6">
            <x-slot:left>
                <h3 class="text-lg font-semibold text-[color:var(--ui-secondary)] mb-4">Story Points</h3>
                <x-ui-form-grid :cols="2" :gap="3">
                    <x-ui-dashboard-tile title="Offen" :count="$openStoryPoints" icon="clock" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt" :count="$completedStoryPoints" icon="check-circle" variant="success" size="sm" />
                    <x-ui-dashboard-tile title="Erstellt (Monat)" :count="$monthlyCreatedPoints" icon="plus-circle" variant="neutral" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedPoints" icon="check-circle" variant="success" size="sm" />
                </x-ui-form-grid>
            </x-slot:left>
            <x-slot:right>
                <h3 class="text-lg font-semibold text-[color:var(--ui-secondary)] mb-4">Zeit-Übersicht</h3>
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
                        :subtitle="$unbilledAmountCents ? 'Wert: ' . number_format($unbilledAmountCents / 100, 2, ',', '.') . ' €' : 'Noch keine offenen Werte'"
                        icon="exclamation-circle"
                        variant="warning"
                        size="sm"
                    />
                    <x-ui-dashboard-tile
                        title="Erstellt (Monat)"
                        :count="$monthlyCreatedTasks"
                        icon="plus-circle"
                        variant="neutral"
                        size="sm"
                    />
                    <x-ui-dashboard-tile
                        title="Frösche"
                        :count="$frogTasks"
                        icon="exclamation-triangle"
                        variant="danger"
                        size="sm"
                    />
                </x-ui-form-grid>
            </x-slot:right>
        </x-ui-detail-stats-grid>

    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Quick Stats --}}
                <div>
                    <h3 class="text-sm font-bold text-[color:var(--ui-secondary)] uppercase tracking-wider mb-3">Heute</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[color:var(--ui-muted-5)] rounded-lg border border-[color:var(--ui-border)]/40">
                            <div class="text-xs text-[color:var(--ui-muted)]">Heute erstellt</div>
                            <div class="text-lg font-bold text-[color:var(--ui-secondary)]">{{ $todayCreatedTasks }} Aufgaben</div>
                        </div>
                        <div class="p-3 bg-[color:var(--ui-muted-5)] rounded-lg border border-[color:var(--ui-border)]/40">
                            <div class="text-xs text-[color:var(--ui-muted)]">Heute erledigt</div>
                            <div class="text-lg font-bold text-[color:var(--ui-secondary)]">{{ $todayCompletedTasks }} Aufgaben</div>
                        </div>
                        <div class="p-3 bg-[color:var(--ui-muted-5)] rounded-lg border border-[color:var(--ui-border)]/40">
                            <div class="text-xs text-[color:var(--ui-muted)]">Offene Frösche</div>
                            <div class="text-lg font-bold text-[color:var(--ui-secondary)]">{{ $frogTasks }} Aufgaben</div>
                        </div>
                        <div class="p-3 bg-[color:var(--ui-muted-5)] rounded-lg border border-[color:var(--ui-border)]/40">
                            <div class="text-xs text-[color:var(--ui-muted)]">Offene Stunden</div>
                            <div class="text-lg font-bold text-[color:var(--ui-secondary)]">{{ number_format($unbilledMinutes / 60, 2, ',', '.') }} h</div>
                        </div>
                        @if($unbilledAmountCents > 0)
                            <div class="p-3 bg-[color:var(--ui-muted-5)] rounded-lg border border-[color:var(--ui-border)]/40">
                                <div class="text-xs text-[color:var(--ui-muted)]">Offener Wert</div>
                                <div class="text-lg font-bold text-[color:var(--ui-secondary)]">{{ number_format($unbilledAmountCents / 100, 2, ',', '.') }} €</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Echte Aktivitäten --}}
                <div>
                    <h3 class="text-sm font-bold text-[color:var(--ui-secondary)] uppercase tracking-wider mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-2 text-sm">
                        @forelse($recentActivities as $activity)
                            @php
                                $eventLabel = match($activity->name) {
                                    'created' => 'erstellt',
                                    'updated' => 'aktualisiert',
                                    'deleted' => 'gelöscht',
                                    default => $activity->name,
                                };
                            @endphp
                            <div class="p-2 rounded border border-[color:var(--ui-border)]/60 bg-[color:var(--ui-muted-5)]">
                                <div class="font-medium text-[color:var(--ui-secondary)] truncate">
                                    @if($activity->subject_kind)
                                        <span class="text-[color:var(--ui-muted)]">{{ $activity->subject_kind }}:</span>
                                    @endif
                                    {{ \Illuminate\Support\Str::limit($activity->subject_label ?? 'Element', 40) }}
                                    <span class="text-[color:var(--ui-muted)] font-normal">{{ $eventLabel }}</span>
                                </div>
                                <div class="text-[color:var(--ui-muted)] text-xs flex items-center gap-2">
                                    <span>{{ $activity->created_at->diffForHumans() }}</span>
                                    @if($activity->user)
                                        <span>•</span>
                                        <span>{{ $activity->user->name }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-2 rounded border border-[color:var(--ui-border)]/60 bg-[color:var(--ui-muted-5)]">
                                <div class="text-[color:var(--ui-muted)] text-xs">Noch keine Aktivitäten.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
