@include('planner::partials.planner-tokens')
@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Guten Morgen' : ($hour < 18 ? 'Guten Tag' : 'Guten Abend');
@endphp
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

        {{-- Greeting + Summary --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[var(--ui-secondary)] tracking-tight">{{ $greeting }}</h1>
            <p class="text-sm text-[var(--ui-muted)] mt-1">
                {{ $openTasks }} offene Aufgaben
                @if($overdueTasksCount > 0)
                    — <span class="text-[var(--planner-status-overdue)] font-medium">{{ $overdueTasksCount }} überfällig</span>
                @endif
            </p>
        </div>

        {{-- KPI Strip with status colors --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[var(--planner-status-active)]/20 bg-[var(--planner-status-active)]/5">
                <div class="text-[var(--planner-status-active)]">
                    @svg('heroicon-o-clipboard-document-list', 'w-5 h-5')
                </div>
                <div>
                    <div class="text-xl font-bold text-[var(--ui-secondary)] leading-none">{{ $openTasks }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Offen</div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border {{ $overdueTasksCount > 0 ? 'border-[var(--planner-status-overdue)]/30 bg-[var(--planner-status-overdue)]/8' : 'border-[var(--ui-border)] bg-[var(--ui-muted-5)]' }}">
                <div class="{{ $overdueTasksCount > 0 ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-muted)]' }}">
                    @svg('heroicon-o-exclamation-circle', 'w-5 h-5')
                </div>
                <div>
                    <div class="text-xl font-bold {{ $overdueTasksCount > 0 ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-secondary)]' }} leading-none">{{ $overdueTasksCount }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Überfällig</div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border {{ $dueTodayCount > 0 ? 'border-amber-300/40 bg-amber-50' : 'border-[var(--ui-border)] bg-[var(--ui-muted-5)]' }}">
                <div class="{{ $dueTodayCount > 0 ? 'text-amber-500' : 'text-[var(--ui-muted)]' }}">
                    @svg('heroicon-o-calendar', 'w-5 h-5')
                </div>
                <div>
                    <div class="text-xl font-bold text-[var(--ui-secondary)] leading-none">{{ $dueTodayCount }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Heute</div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
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

                {{-- Überfällige Tasks — prominent red container --}}
                @if($overdueTasksList->count() > 0)
                    <div class="rounded-lg border border-[var(--planner-status-overdue)]/30 bg-[var(--planner-card-overdue)] overflow-hidden">
                        <div class="px-4 py-3 border-b border-[var(--planner-status-overdue)]/20">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--planner-status-overdue)] flex items-center gap-2">
                                @svg('heroicon-o-exclamation-circle', 'w-4 h-4')
                                Überfällig ({{ $overdueTasksCount }})
                            </h3>
                        </div>
                        <div class="divide-y divide-[var(--planner-status-overdue)]/10">
                            @foreach($overdueTasksList as $task)
                                @php
                                    $daysOverdue = now()->startOfDay()->diffInDays($task->due_date->startOfDay());
                                    $priorityColor = match($task->priority?->value ?? null) {
                                        'high' => 'var(--planner-priority-high)',
                                        'normal' => 'var(--planner-priority-normal)',
                                        'low' => 'var(--planner-priority-low)',
                                        default => 'var(--ui-muted)',
                                    };
                                    $uic = $task->userInCharge;
                                    $uicInitial = $uic ? mb_strtoupper(mb_substr($uic->name ?? $uic->email ?? 'U', 0, 1)) : null;
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-[var(--planner-status-overdue)]/5 transition group">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }}"></span>
                                    <span class="flex-1 min-w-0 text-sm text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-overdue)]">{{ $task->title }}</span>
                                    @if($task->project)
                                        <span class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] rounded bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)] truncate max-w-[120px]">{{ $task->project->name }}</span>
                                    @endif
                                    @if($uic)
                                        @if($uic->avatar)
                                            <img src="{{ $uic->avatar }}" alt="" class="w-5 h-5 rounded-full object-cover flex-shrink-0">
                                        @else
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--planner-status-overdue)]/10 text-[10px] font-medium text-[var(--planner-status-overdue)] flex-shrink-0">{{ $uicInitial }}</span>
                                        @endif
                                    @endif
                                    <span class="text-xs font-semibold text-[var(--planner-status-overdue)] flex-shrink-0 tabular-nums">-{{ (int) $daysOverdue }}d</span>
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
                                    <span class="w-1.5 h-1.5 rounded-full {{ $daysLeft <= 1 ? 'bg-amber-400' : 'bg-[var(--planner-status-active)]' }} flex-shrink-0"></span>
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
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-active)] flex-shrink-0"></span>
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
                            $projectColor = $project['color'] ?? null;
                            $progressCssColor = $progress >= 75 ? 'var(--planner-status-done)' : ($progress >= 40 ? 'var(--planner-status-active)' : 'var(--planner-priority-high)');
                        @endphp
                        <a href="{{ route('planner.projects.show', ['plannerProject' => $project['id']]) }}" class="block px-3 py-2.5 rounded-md border border-[var(--ui-border)] bg-white hover:bg-[var(--planner-card-hover)] transition">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="flex items-center gap-2 text-sm font-medium text-[var(--ui-secondary)] truncate">
                                    @if($projectColor)
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $projectColor }}"></span>
                                    @endif
                                    {{ $project['name'] }}
                                </span>
                                <span class="text-xs font-semibold flex-shrink-0 ml-2" style="color: {{ $progressCssColor }}">{{ $progress }}%</span>
                            </div>
                            <div class="h-2 bg-[var(--planner-track)] rounded-full overflow-hidden">
                                <div
                                    class="h-full transition-all rounded-full"
                                    style="width: {{ $progress }}%; background-color: {{ $progressCssColor }}"
                                ></div>
                            </div>
                            <div class="flex items-center gap-2 mt-1.5 text-[10px] text-[var(--ui-muted)]">
                                <span>{{ $project['completed_tasks'] }}/{{ $project['total_tasks'] }} Aufgaben</span>
                                @if($project['open_tasks'] > 0)
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $project['open_tasks'] }} offen</span>
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
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--planner-status-done)] flex-shrink-0')
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
