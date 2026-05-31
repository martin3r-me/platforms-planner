@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Guten Morgen' : ($hour < 18 ? 'Guten Tag' : 'Guten Abend');
@endphp
<x-ui-page>
    @include('planner::partials.planner-tokens')
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dashboard" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'icon' => 'home'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        {{-- Greeting --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-[var(--ui-secondary)] tracking-tight">{{ $greeting }}</h1>
            <p class="text-sm text-[var(--ui-muted)] mt-1">
                {{ $openTasks }} offene Aufgaben im Team
                @if($overdueTasksCount > 0)
                    — <span class="text-[var(--planner-status-overdue)] font-medium">{{ $overdueTasksCount }} überfällig</span>
                @endif
            </p>
        </div>

        {{-- Quick Navigation Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
            <a href="{{ route('planner.my-tasks') }}" wire:navigate class="group flex items-center gap-3 px-4 py-3.5 rounded-lg border border-[var(--planner-status-active)]/20 bg-[var(--planner-status-active)]/5 hover:bg-[var(--planner-status-active)]/10 hover:border-[var(--planner-status-active)]/40 transition-all">
                <div class="w-9 h-9 rounded-lg bg-[var(--planner-status-active)]/10 flex items-center justify-center group-hover:bg-[var(--planner-status-active)]/20 transition-colors">
                    @svg('heroicon-o-clipboard-document-check', 'w-5 h-5 text-[var(--planner-status-active)]')
                </div>
                <div>
                    <div class="text-xl font-bold text-[var(--ui-secondary)] leading-none">{{ $myOpenTasksCount }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Meine Aufgaben</div>
                </div>
            </a>

            <a href="{{ route('planner.frog-tasks') }}" wire:navigate class="group flex items-center gap-3 px-4 py-3.5 rounded-lg border {{ $myFrogsCount > 0 ? 'border-[var(--planner-frog)]/20 bg-[var(--planner-frog)]/5 hover:bg-[var(--planner-frog)]/10 hover:border-[var(--planner-frog)]/40' : 'border-[var(--ui-border)] bg-[var(--ui-muted-5)] hover:bg-[var(--ui-muted)]' }} transition-all">
                <div class="w-9 h-9 rounded-lg {{ $myFrogsCount > 0 ? 'bg-[var(--planner-frog)]/10 group-hover:bg-[var(--planner-frog)]/20' : 'bg-[var(--ui-muted-5)]' }} flex items-center justify-center transition-colors">
                    <span class="text-lg">🐸</span>
                </div>
                <div>
                    <div class="text-xl font-bold {{ $myFrogsCount > 0 ? 'text-[var(--planner-frog)]' : 'text-[var(--ui-secondary)]' }} leading-none">{{ $myFrogsCount }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Frösche</div>
                </div>
            </a>

            <a href="{{ route('planner.delegated-tasks') }}" wire:navigate class="group flex items-center gap-3 px-4 py-3.5 rounded-lg border {{ $delegatedOpenCount > 0 ? 'border-amber-300/30 bg-amber-50 hover:bg-amber-100/50 hover:border-amber-300/50' : 'border-[var(--ui-border)] bg-[var(--ui-muted-5)] hover:bg-[var(--ui-muted)]' }} transition-all">
                <div class="w-9 h-9 rounded-lg {{ $delegatedOpenCount > 0 ? 'bg-amber-100 group-hover:bg-amber-200/60' : 'bg-[var(--ui-muted-5)]' }} flex items-center justify-center transition-colors">
                    @svg('heroicon-o-user-group', 'w-5 h-5 {{ $delegatedOpenCount > 0 ? "text-amber-600" : "text-[var(--ui-muted)]" }}')
                </div>
                <div>
                    <div class="text-xl font-bold text-[var(--ui-secondary)] leading-none">{{ $delegatedOpenCount }}</div>
                    <div class="text-xs text-[var(--ui-muted)]">Delegiert</div>
                </div>
            </a>

            <a href="{{ route('planner.completed-tasks') }}" wire:navigate class="group flex items-center gap-3 px-4 py-3.5 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-muted-5)] hover:bg-[var(--ui-muted)] transition-all">
                <div class="w-9 h-9 rounded-lg bg-[var(--ui-muted-5)] group-hover:bg-[var(--planner-status-done)]/10 flex items-center justify-center transition-colors">
                    @svg('heroicon-o-check-circle', 'w-5 h-5 text-[var(--ui-muted)] group-hover:text-[var(--planner-status-done)]')
                </div>
                <div>
                    <div class="text-sm font-medium text-[var(--ui-secondary)] leading-tight">Erledigt</div>
                    <div class="text-xs text-[var(--ui-muted)]">Verlauf</div>
                </div>
            </a>
        </div>

        {{-- Überfällige Tasks — prominent red container --}}
        @if($overdueTasksList->count() > 0)
            <div class="rounded-lg border border-[var(--planner-status-overdue)]/30 bg-[var(--planner-card-overdue)] overflow-hidden mb-8">
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
                            $priorityColor = $task->priority?->color() ?? 'var(--ui-muted)';
                            $uic = $task->userInCharge;
                            $uicInitial = $uic ? mb_strtoupper(mb_substr($uic->name ?? $uic->email ?? 'U', 0, 1)) : null;
                        @endphp
                        <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-[var(--planner-status-overdue)]/5 transition group">
                            {{-- Done toggle --}}
                            <button
                                type="button"
                                wire:click="quickToggleDone({{ $task->id }})"
                                class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors border-[var(--ui-border)] text-transparent hover:border-[var(--planner-status-done)] hover:text-[var(--planner-status-done)] cursor-pointer"
                                title="Als erledigt markieren"
                            >
                                @svg('heroicon-s-check', 'w-3 h-3')
                            </button>
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }}"></span>
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="flex-1 min-w-0 text-sm text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-overdue)]">{{ $task->title }}</a>
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
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Two-column layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Left: Anstehend + Meine Aufgaben --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- Anstehende Tasks (nächste 7 Tage) --}}
                @if($upcomingTasksList->count() > 0)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3 flex items-center gap-2">
                            @svg('heroicon-o-calendar', 'w-4 h-4')
                            Anstehend
                        </h3>
                        <div class="space-y-0.5">
                            @foreach($upcomingTasksList as $task)
                                @php
                                    $daysLeft = now()->startOfDay()->diffInDays($task->due_date->startOfDay(), false);
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-[var(--ui-muted-5)] transition group">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $daysLeft <= 1 ? 'bg-amber-400' : 'bg-[var(--planner-status-active)]' }} flex-shrink-0"></span>
                                    <span class="flex-1 min-w-0 text-sm text-[var(--ui-secondary)] truncate">{{ $task->title }}</span>
                                    @if($task->project)
                                        <span class="text-[10px] text-[var(--ui-muted)] truncate max-w-[100px] hidden sm:inline">{{ $task->project->name }}</span>
                                    @endif
                                    <span class="text-xs text-[var(--ui-muted)] flex-shrink-0 tabular-nums">
                                        @if($daysLeft == 0) heute
                                        @elseif($daysLeft == 1) morgen
                                        @else in {{ (int) $daysLeft }}d
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Meine Aufgaben (Vorschau) --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-4 h-4')
                            Meine Aufgaben
                        </h3>
                        @if($myOpenTasksCount > $myTasksList->count())
                            <a href="{{ route('planner.my-tasks') }}" wire:navigate class="text-[10px] text-[var(--planner-status-active)] hover:underline">
                                Alle {{ $myOpenTasksCount }} anzeigen →
                            </a>
                        @endif
                    </div>
                    <div class="space-y-0.5">
                        @forelse($myTasksList as $task)
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-[var(--ui-muted-5)] transition group">
                                @php
                                    $pColor = $task->priority?->color() ?? 'var(--ui-muted)';
                                @endphp
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $pColor }}"></span>
                                <span class="flex-1 min-w-0 text-sm text-[var(--ui-secondary)] truncate">{{ $task->title }}</span>
                                @if($task->project)
                                    <span class="text-[10px] text-[var(--ui-muted)] truncate max-w-[100px] hidden sm:inline">{{ $task->project->name }}</span>
                                @endif
                                @if($task->due_date)
                                    @php $taskOverdue = $task->due_date->isPast(); @endphp
                                    <span class="text-xs flex-shrink-0 tabular-nums {{ $taskOverdue ? 'text-[var(--planner-status-overdue)] font-medium' : 'text-[var(--ui-muted)]' }}">{{ $task->due_date->format('d.m.') }}</span>
                                @endif
                            </a>
                        @empty
                            <div class="px-3 py-6 text-sm text-[var(--ui-muted)] text-center">
                                Keine offenen Aufgaben zugewiesen.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

            {{-- Right: Projekte --}}
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
                            {{ $showCompletedProjects ? 'Ausblenden' : '+ ' . $recentlyCompletedWithProgress->count() . ' erledigt' }}
                        </button>
                    @endif
                </div>
                <div class="space-y-2">
                    @forelse($projectsWithProgress as $project)
                        @php
                            $progress = $project['progress_percent'];
                            $projectColor = $project['color'] ?? null;
                            $progressCssColor = $progress >= 75 ? 'var(--planner-status-done)' : ($progress >= 40 ? 'var(--planner-status-active)' : 'var(--planner-col-backlog)');
                        @endphp
                        <a href="{{ route('planner.projects.show', ['plannerProject' => $project['id']]) }}" wire:navigate class="block px-3 py-2.5 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--planner-status-active)]/30 hover:bg-[var(--planner-card-hover)] transition-all">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="flex items-center gap-2 text-sm font-medium text-[var(--ui-secondary)] truncate">
                                    @if($projectColor)
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $projectColor }}"></span>
                                    @endif
                                    {{ $project['name'] }}
                                </span>
                                <span class="text-xs font-semibold flex-shrink-0 ml-2" style="color: {{ $progressCssColor }}">{{ $progress }}%</span>
                            </div>
                            <div class="h-1.5 bg-[var(--planner-track)] rounded-full overflow-hidden">
                                <div
                                    class="h-full transition-all rounded-full"
                                    style="width: {{ $progress }}%; background-color: {{ $progressCssColor }}"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between mt-1.5 text-[10px] text-[var(--ui-muted)]">
                                <span>{{ $project['completed_tasks'] }}/{{ $project['total_tasks'] }}</span>
                                @if($project['open_tasks'] > 0)
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $project['open_tasks'] }} offen</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="px-3 py-6 text-sm text-[var(--ui-muted)] text-center">
                            Keine aktiven Projekte.
                        </div>
                    @endforelse

                    @if($showCompletedProjects && $recentlyCompletedWithProgress->count() > 0)
                        <div class="pt-3 mt-2 border-t border-[var(--ui-border)]/40">
                            @foreach($recentlyCompletedWithProgress as $project)
                                <a href="{{ route('planner.projects.show', ['plannerProject' => $project['id']]) }}" wire:navigate class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-[var(--ui-muted-5)] transition opacity-60">
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

                {{-- Team-Zahlen compact --}}
                <div class="mt-6 pt-4 border-t border-[var(--ui-border)]/40">
                    <div class="flex items-center justify-between text-xs text-[var(--ui-muted)]">
                        <span>Stunden diesen Monat</span>
                        <span class="font-medium text-[var(--ui-secondary)]">{{ number_format($monthlyLoggedMinutes / 60, 1, ',', '.') }}h</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-[var(--ui-muted)] mt-1.5">
                        <span>Heute fällig</span>
                        <span class="font-medium {{ $dueTodayCount > 0 ? 'text-amber-600' : 'text-[var(--ui-secondary)]' }}">{{ $dueTodayCount }}</span>
                    </div>
                </div>
            </div>

        </div>

    </x-ui-page-container>
</x-ui-page>
