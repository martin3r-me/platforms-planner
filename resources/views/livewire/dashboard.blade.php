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

        {{-- KPI Row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[var(--ui-border)] bg-white">
                <div class="text-[var(--ui-secondary)]">
                    @svg('heroicon-o-clipboard-document-list', 'w-5 h-5')
                </div>
                <div>
                    <div class="text-xl font-bold text-[var(--ui-secondary)] leading-none">{{ $openTasks }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Offen</div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border {{ $overdueTasksCount > 0 ? 'border-[var(--ui-danger)]/30 bg-[var(--ui-danger)]/5' : 'border-[var(--ui-border)] bg-white' }}">
                <div class="{{ $overdueTasksCount > 0 ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-muted)]' }}">
                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5')
                </div>
                <div>
                    <div class="text-xl font-bold {{ $overdueTasksCount > 0 ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-secondary)]' }} leading-none">{{ $overdueTasksCount }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Überfällig</div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[var(--ui-border)] bg-white">
                <div class="text-[var(--ui-warning)]">
                    @svg('heroicon-o-calendar', 'w-5 h-5')
                </div>
                <div>
                    <div class="text-xl font-bold text-[var(--ui-secondary)] leading-none">{{ $dueTodayCount }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Heute</div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[var(--ui-border)] bg-white">
                <div class="text-[var(--ui-muted)]">
                    @svg('heroicon-o-clock', 'w-5 h-5')
                </div>
                <div>
                    <div class="text-xl font-bold text-[var(--ui-secondary)] leading-none">{{ number_format($monthlyLoggedMinutes / 60, 1, ',', '.') }}h</div>
                    <div class="text-xs text-[var(--ui-muted)]">Monat</div>
                </div>
            </div>
        </div>

        {{-- Two-column layout: Tasks left, Projects right --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left column: Task lists (2/3 width) --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Überfällige Tasks --}}
                @if($overdueTasksList->count() > 0)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-danger)] mb-3 flex items-center gap-2">
                            @svg('heroicon-o-exclamation-circle', 'w-4 h-4')
                            Überfällig ({{ $overdueTasksCount }})
                        </h3>
                        <div class="space-y-1">
                            @foreach($overdueTasksList as $task)
                                @php
                                    $daysOverdue = now()->startOfDay()->diffInDays($task->due_date->startOfDay());
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-[var(--ui-danger)]/5 transition group">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--ui-danger)] flex-shrink-0"></span>
                                    <span class="flex-1 min-w-0 text-sm text-[var(--ui-secondary)] truncate group-hover:text-[var(--ui-danger)]">{{ $task->title }}</span>
                                    @if($task->project)
                                        <span class="text-xs text-[var(--ui-muted)] truncate max-w-[120px] hidden sm:inline">{{ $task->project->name }}</span>
                                    @endif
                                    <span class="text-xs font-medium text-[var(--ui-danger)] flex-shrink-0">-{{ (int) $daysOverdue }}d</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Anstehende Tasks --}}
                @if($upcomingTasksList->count() > 0)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3 flex items-center gap-2">
                            @svg('heroicon-o-calendar', 'w-4 h-4')
                            Anstehend ({{ $upcomingTasksList->count() }})
                        </h3>
                        <div class="space-y-1">
                            @foreach($upcomingTasksList as $task)
                                @php
                                    $daysLeft = now()->startOfDay()->diffInDays($task->due_date->startOfDay(), false);
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-[var(--ui-muted-5)] transition group">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $daysLeft <= 1 ? 'bg-[var(--ui-warning)]' : 'bg-[var(--ui-muted)]' }} flex-shrink-0"></span>
                                    <span class="flex-1 min-w-0 text-sm text-[var(--ui-secondary)] truncate">{{ $task->title }}</span>
                                    @if($task->project)
                                        <span class="text-xs text-[var(--ui-muted)] truncate max-w-[120px] hidden sm:inline">{{ $task->project->name }}</span>
                                    @endif
                                    <span class="text-xs text-[var(--ui-muted)] flex-shrink-0">
                                        @if($daysLeft == 0)
                                            heute
                                        @elseif($daysLeft == 1)
                                            morgen
                                        @else
                                            in {{ (int) $daysLeft }}d
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Meine Aufgaben --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3 flex items-center gap-2">
                        @svg('heroicon-o-user', 'w-4 h-4')
                        Meine Aufgaben ({{ $myTasksList->count() }})
                    </h3>
                    <div class="space-y-1">
                        @forelse($myTasksList as $task)
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-[var(--ui-muted-5)] transition group">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--ui-primary)] flex-shrink-0"></span>
                                <span class="flex-1 min-w-0 text-sm text-[var(--ui-secondary)] truncate">{{ $task->title }}</span>
                                @if($task->project)
                                    <span class="text-xs text-[var(--ui-muted)] truncate max-w-[120px] hidden sm:inline">{{ $task->project->name }}</span>
                                @endif
                                @if($task->due_date)
                                    <span class="text-xs text-[var(--ui-muted)] flex-shrink-0">{{ $task->due_date->format('d.m.') }}</span>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)]/50 flex-shrink-0">kein Datum</span>
                                @endif
                            </a>
                        @empty
                            <div class="px-3 py-4 text-sm text-[var(--ui-muted)] text-center">
                                Keine offenen Aufgaben zugewiesen.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

            {{-- Right column: Projects (1/3 width) --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] flex items-center gap-2">
                        @svg('heroicon-o-folder', 'w-4 h-4')
                        Projekte
                    </h3>
                    @if($recentlyCompletedWithProgress->count() > 0)
                        <button
                            wire:click="toggleCompletedProjects"
                            type="button"
                            class="text-[10px] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] underline"
                        >
                            {{ $showCompletedProjects ? 'Abgeschlossene ausblenden' : '+ ' . $recentlyCompletedWithProgress->count() . ' abgeschlossen' }}
                        </button>
                    @endif
                </div>
                <div class="space-y-2">
                    @forelse($projectsWithProgress as $project)
                        @php
                            $progress = $project['progress_percent'];
                            $progressColor = $progress >= 75 ? 'success' : ($progress >= 40 ? 'primary' : 'warning');
                        @endphp
                        <a href="{{ route('planner.projects.show', ['plannerProject' => $project['id']]) }}" class="block px-3 py-2.5 rounded-md border border-[var(--ui-border)] bg-white hover:bg-[var(--ui-muted-5)] transition">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $project['name'] }}</span>
                                <span class="text-xs font-semibold text-[var(--ui-{{ $progressColor }})] flex-shrink-0 ml-2">{{ $progress }}%</span>
                            </div>
                            <div class="h-1.5 bg-[var(--ui-muted-10)] rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-[var(--ui-{{ $progressColor }})] transition-all rounded-full"
                                    style="width: {{ $progress }}%"
                                ></div>
                            </div>
                            <div class="flex items-center gap-2 mt-1.5 text-[10px] text-[var(--ui-muted)]">
                                <span>{{ $project['completed_tasks'] }}/{{ $project['total_tasks'] }} Aufgaben</span>
                                @if($project['open_tasks'] > 0)
                                    <span>{{ $project['open_tasks'] }} offen</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="px-3 py-4 text-sm text-[var(--ui-muted)] text-center">
                            Keine aktiven Projekte.
                        </div>
                    @endforelse

                    @if($showCompletedProjects && $recentlyCompletedWithProgress->count() > 0)
                        <div class="pt-3 mt-2 border-t border-[var(--ui-border)]">
                            @foreach($recentlyCompletedWithProgress as $project)
                                <a href="{{ route('planner.projects.show', ['plannerProject' => $project['id']]) }}" class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-[var(--ui-muted-5)] transition opacity-60">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-success)] flex-shrink-0')
                                    <span class="text-sm text-[var(--ui-secondary)] truncate">{{ $project['name'] }}</span>
                                    @if($project['done_at'])
                                        <span class="text-[10px] text-[var(--ui-muted)] flex-shrink-0 ml-auto">{{ $project['done_at']->format('d.m.') }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </x-ui-page-container>
</x-ui-page>
