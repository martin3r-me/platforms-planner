@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Guten Morgen' : ($hour < 18 ? 'Guten Tag' : 'Guten Abend');
    $firstName = trim(explode(' ', auth()->user()?->name ?? '')[0] ?? '');
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

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Projekte" icon="heroicon-o-folder" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Meine Projekte</h3>
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
                            $myOpen = $project['my_open_tasks'] ?? 0;
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
                                <div class="h-full transition-all rounded-full" style="width: {{ $progress }}%; background-color: {{ $progressCssColor }}"></div>
                            </div>
                            <div class="flex items-center justify-between mt-1.5 text-[10px] text-[var(--ui-muted)]">
                                <span class="tabular-nums">{{ $project['completed_tasks'] }}/{{ $project['total_tasks'] }} gesamt</span>
                                @if($myOpen > 0)
                                    <span class="inline-flex items-center gap-1 font-semibold text-[var(--planner-status-active)]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-active)]"></span>
                                        {{ $myOpen }} für mich
                                    </span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="px-3 py-6 text-xs text-[var(--ui-muted)] text-center">
                            Du hast aktuell keine Aufgaben in einem Projekt.
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
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Heute" icon="heroicon-o-bolt" width="w-72" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-5">
                <section>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Meine Zahlen</h3>
                    <dl class="space-y-1.5 text-[11px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--ui-muted)]">Stunden diesen Monat</dt>
                            <dd class="text-[var(--ui-secondary)] font-medium tabular-nums m-0">{{ number_format($myMonthlyMinutes / 60, 1, ',', '.') }} h</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--ui-muted)]">Heute fällig</dt>
                            <dd class="font-medium tabular-nums m-0 {{ $myDueTodayCount > 0 ? 'text-amber-600' : 'text-[var(--ui-secondary)]' }}">{{ $myDueTodayCount }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--ui-muted)]">Offen</dt>
                            <dd class="text-[var(--ui-secondary)] font-medium tabular-nums m-0">{{ $myOpenTasksCount }}</dd>
                        </div>
                        @if($myOverdueCount > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[var(--ui-muted)]">Überfällig</dt>
                                <dd class="text-[var(--planner-status-overdue)] font-semibold tabular-nums m-0">{{ $myOverdueCount }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>

                <section>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Springe zu</h3>
                    <ul class="space-y-0.5 text-[11px]">
                        <li><a href="{{ route('planner.my-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">@svg('heroicon-o-clipboard-document-check', 'w-3.5 h-3.5 opacity-60') Meine Aufgaben</a></li>
                        <li><a href="{{ route('planner.frog-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]"><span class="text-xs leading-none">🐸</span> Frösche</a></li>
                        <li><a href="{{ route('planner.delegated-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">@svg('heroicon-o-user-group', 'w-3.5 h-3.5 opacity-60') Delegiert</a></li>
                        <li><a href="{{ route('planner.completed-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">@svg('heroicon-o-check-circle', 'w-3.5 h-3.5 opacity-60') Erledigt</a></li>
                        <li><a href="{{ route('planner.hygiene') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">@svg('heroicon-o-shield-check', 'w-3.5 h-3.5 opacity-60') Hygiene</a></li>
                    </ul>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>

        {{-- Greeting --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-[var(--ui-secondary)] tracking-tight">
                {{ $greeting }}@if($firstName), <span class="text-[var(--planner-status-active)]">{{ $firstName }}</span>@endif
            </h1>
            <p class="text-sm text-[var(--ui-muted)] mt-1">
                @if($myOpenTasksCount === 0)
                    Du hast keine offenen Aufgaben — Zeit für einen Kaffee.
                @else
                    Du hast <span class="font-medium text-[var(--ui-secondary)] tabular-nums">{{ $myOpenTasksCount }}</span> offene Aufgabe{{ $myOpenTasksCount === 1 ? '' : 'n' }}
                    @if($myOverdueCount > 0)
                        — davon <span class="text-[var(--planner-status-overdue)] font-medium tabular-nums">{{ $myOverdueCount }} überfällig</span>
                    @elseif($myDueTodayCount > 0)
                        — <span class="text-amber-600 font-medium tabular-nums">{{ $myDueTodayCount }}</span> heute fällig
                    @endif
                    .
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

        {{-- Meine überfälligen Aufgaben --}}
        @if($overdueTasksList->count() > 0)
            <div class="rounded-xl border border-[var(--planner-status-overdue)]/30 bg-white shadow-sm overflow-hidden mb-8">
                <div class="px-4 py-3 border-b border-[var(--planner-status-overdue)]/20 bg-[var(--planner-status-overdue)]/5 flex items-center gap-2">
                    @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-[var(--planner-status-overdue)]')
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--planner-status-overdue)] m-0">
                        Meine überfälligen Aufgaben
                    </h3>
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-bold rounded-full bg-[var(--planner-status-overdue)] text-white">{{ $myOverdueCount }}</span>
                </div>
                <div class="divide-y divide-[var(--planner-status-overdue)]/10">
                    @foreach($overdueTasksList as $task)
                        @php
                            $daysOverdue = now()->startOfDay()->diffInDays($task->due_date->startOfDay());
                            $priorityColor = $task->priority?->color() ?? 'var(--ui-muted)';
                        @endphp
                        <div class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--planner-status-overdue)]/5 transition group">
                            <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full bg-[var(--planner-status-overdue)]"></span>
                            <button
                                type="button"
                                x-data="{ press: null }"
                                @mousedown.stop="press = { x: $event.clientX, y: $event.clientY }"
                                @click.stop.prevent="
                                    const ok = press && Math.abs($event.clientX - press.x) < 5 && Math.abs($event.clientY - press.y) < 5;
                                    press = null;
                                    if (ok) $wire.quickToggleDone({{ $task->id }});
                                "
                                class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors border-[var(--ui-border)] text-transparent hover:border-[var(--planner-status-done)] hover:text-[var(--planner-status-done)] cursor-pointer"
                                title="Als erledigt markieren"
                            >
                                @svg('heroicon-s-check', 'w-3 h-3')
                            </button>
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }}"></span>
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="flex-1 min-w-0 text-sm font-medium text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-overdue)]">{{ $task->title }}</a>
                            @if($task->project)
                                <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-[var(--ui-muted)] truncate max-w-[140px]">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--ui-muted)' }};"></span>
                                    {{ $task->project->name }}
                                </span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)] flex-shrink-0">{{ (int) $daysOverdue }}d zu spät</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Main: Anstehend + Meine Aufgaben (Projekte sind in der linken Sidebar) --}}
        <div class="space-y-8">

                {{-- Meine anstehenden Aufgaben (nächste 7 Tage) --}}
                @if($upcomingTasksList->count() > 0)
                    <div class="rounded-xl border border-[var(--ui-border)]/40 bg-white shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] flex items-center gap-2">
                            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--planner-status-active)]')
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-secondary)] m-0">Meine anstehenden Aufgaben</h3>
                            <span class="text-[10px] text-[var(--ui-muted)]">nächste 7 Tage</span>
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)]">{{ $upcomingTasksList->count() }}</span>
                        </div>
                        <div class="divide-y divide-[var(--ui-border)]/30">
                            @foreach($upcomingTasksList as $task)
                                @php
                                    $daysLeft = now()->startOfDay()->diffInDays($task->due_date->startOfDay(), false);
                                    $isUrgent = $daysLeft <= 1;
                                    $priorityColor = $task->priority?->color() ?? 'var(--ui-muted)';
                                    $edgeColor = $isUrgent ? '#f59e0b' : 'var(--planner-status-active)';
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition group">
                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }};"></span>
                                    <span class="flex-1 min-w-0 text-sm font-medium text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-active)]">{{ $task->title }}</span>
                                    @if($task->project)
                                        <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-[var(--ui-muted)] truncate max-w-[140px]">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--ui-muted)' }};"></span>
                                            {{ $task->project->name }}
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums flex-shrink-0
                                        {{ $isUrgent ? 'bg-amber-100 text-amber-700' : 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)]' }}">
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

                {{-- Meine Aufgaben (alle offenen, Vorschau) --}}
                <div class="rounded-xl border border-[var(--ui-border)]/40 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] flex items-center gap-2">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 text-[var(--planner-status-active)]')
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-secondary)] m-0">Meine Aufgaben</h3>
                        <span class="ml-auto inline-flex items-center gap-2">
                            @if($myOpenTasksCount > $myTasksList->count())
                                <a href="{{ route('planner.my-tasks') }}" wire:navigate class="text-[10px] font-medium text-[var(--planner-status-active)] hover:underline">
                                    Alle {{ $myOpenTasksCount }} anzeigen →
                                </a>
                            @else
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)]">{{ $myOpenTasksCount }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="divide-y divide-[var(--ui-border)]/30">
                        @forelse($myTasksList as $task)
                            @php
                                $pColor = $task->priority?->color() ?? 'var(--ui-muted)';
                                $taskOverdue = $task->due_date && $task->due_date->isPast();
                                $edgeColor = $taskOverdue ? 'var(--planner-status-overdue)' : $pColor;
                            @endphp
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition group">
                                <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>
                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $pColor }};"></span>
                                <span class="flex-1 min-w-0 text-sm font-medium text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-active)]">{{ $task->title }}</span>
                                @if($task->project)
                                    <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-[var(--ui-muted)] truncate max-w-[140px]">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--ui-muted)' }};"></span>
                                        {{ $task->project->name }}
                                    </span>
                                @endif
                                @if($task->due_date)
                                    <span class="text-xs flex-shrink-0 tabular-nums {{ $taskOverdue ? 'text-[var(--planner-status-overdue)] font-semibold' : 'text-[var(--ui-muted)]' }}">{{ $task->due_date->format('d.m.') }}</span>
                                @else
                                    <span class="text-[10px] flex-shrink-0 text-[var(--ui-muted)]/60 italic">offen</span>
                                @endif
                            </a>
                        @empty
                            <div class="px-3 py-8 text-sm text-[var(--ui-muted)] text-center">
                                @svg('heroicon-o-check-circle', 'w-8 h-8 mx-auto mb-2 opacity-30 text-[var(--planner-status-done)]')
                                Keine offenen Aufgaben — gut so.
                            </div>
                        @endforelse
                    </div>
                </div>

        </div>

    </x-ui-page-container>
</x-ui-page>
