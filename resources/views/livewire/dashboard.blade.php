@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Guten Morgen' : ($hour < 18 ? 'Guten Tag' : 'Guten Abend');
    $firstName = trim(explode(' ', auth()->user()?->name ?? '')[0] ?? '');
@endphp
<x-ui-page>
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
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Meine Projekte</h3>
                    @if($recentlyCompletedWithProgress->count() > 0)
                        <button
                            wire:click="toggleCompletedProjects"
                            type="button"
                            class="text-[10px] text-[var(--nx-muted)] hover:text-[var(--nx-text)] underline"
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
                            $progressCssColor = $progress >= 75 ? 'var(--nx-success)' : ($progress >= 40 ? 'var(--nx-accent)' : 'var(--nx-faint)');
                            $myOpen = $project['my_open_tasks'] ?? 0;
                        @endphp
                        <a href="{{ route('planner.projects.show', ['plannerProject' => $project['id']]) }}" wire:navigate class="block px-3 py-2.5 rounded-lg border border-[color:var(--nx-line)] hover:border-[var(--nx-accent)]/30 hover:bg-[var(--nx-hover)] transition-all">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="flex items-center gap-2 text-sm font-medium text-[var(--nx-text)] truncate">
                                    @if($projectColor)
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $projectColor }}"></span>
                                    @endif
                                    {{ $project['name'] }}
                                </span>
                                <span class="text-xs font-semibold flex-shrink-0 ml-2" style="color: {{ $progressCssColor }}">{{ $progress }}%</span>
                            </div>
                            <div class="h-1.5 bg-[var(--nx-line)] rounded-full overflow-hidden">
                                <div class="h-full transition-all rounded-full" style="width: {{ $progress }}%; background-color: {{ $progressCssColor }}"></div>
                            </div>
                            <div class="flex items-center justify-between mt-1.5 text-[10px] text-[var(--nx-muted)]">
                                <span class="tabular-nums">{{ $project['completed_tasks'] }}/{{ $project['total_tasks'] }} gesamt</span>
                                @if($myOpen > 0)
                                    <span class="inline-flex items-center gap-1 font-semibold text-[var(--nx-accent)]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--nx-accent)]"></span>
                                        {{ $myOpen }} für mich
                                    </span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="px-3 py-6 text-xs text-[var(--nx-muted)] text-center">
                            Du hast aktuell keine Aufgaben in einem Projekt.
                        </div>
                    @endforelse

                    @if($showCompletedProjects && $recentlyCompletedWithProgress->count() > 0)
                        <div class="pt-3 mt-2 border-t border-[color:var(--nx-line)]">
                            @foreach($recentlyCompletedWithProgress as $project)
                                <a href="{{ route('planner.projects.show', ['plannerProject' => $project['id']]) }}" wire:navigate class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-[var(--nx-bg)] transition opacity-60">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--nx-success)] flex-shrink-0')
                                    <span class="text-sm text-[var(--nx-text)] truncate">{{ $project['name'] }}</span>
                                    @if($project['done_at'])
                                        <span class="text-[10px] text-[var(--nx-muted)] flex-shrink-0 ml-auto">{{ $project['done_at']->format('d.m.') }}</span>
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
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Meine Zahlen</h3>
                    <dl class="space-y-1.5 text-[11px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--nx-muted)]">Stunden diesen Monat</dt>
                            <dd class="text-[var(--nx-text)] font-medium tabular-nums m-0">{{ number_format($myMonthlyMinutes / 60, 1, ',', '.') }} h</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--nx-muted)]">Heute fällig</dt>
                            <dd class="font-medium tabular-nums m-0 {{ $myDueTodayCount > 0 ? 'text-[color:var(--nx-warning)]' : 'text-[var(--nx-text)]' }}">{{ $myDueTodayCount }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--nx-muted)]">Offen</dt>
                            <dd class="text-[var(--nx-text)] font-medium tabular-nums m-0">{{ $myOpenTasksCount }}</dd>
                        </div>
                        @if($myOverdueCount > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[var(--nx-muted)]">Überfällig</dt>
                                <dd class="text-[var(--nx-danger)] font-semibold tabular-nums m-0">{{ $myOverdueCount }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>

                <section>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Springe zu</h3>
                    <ul class="space-y-0.5 text-[11px]">
                        <li><a href="{{ route('planner.my-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">@svg('heroicon-o-clipboard-document-check', 'w-3.5 h-3.5 opacity-60') Meine Aufgaben</a></li>
                        <li><a href="{{ route('planner.frog-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]"><span class="text-xs leading-none">🐸</span> Frösche</a></li>
                        <li><a href="{{ route('planner.delegated-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">@svg('heroicon-o-user-group', 'w-3.5 h-3.5 opacity-60') Delegiert</a></li>
                        <li><a href="{{ route('planner.completed-tasks') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">@svg('heroicon-o-check-circle', 'w-3.5 h-3.5 opacity-60') Erledigt</a></li>
                        <li><a href="{{ route('planner.hygiene') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">@svg('heroicon-o-shield-check', 'w-3.5 h-3.5 opacity-60') Hygiene</a></li>
                    </ul>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>

        {{-- Greeting --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-[var(--nx-text)] tracking-tight">
                {{ $greeting }}@if($firstName), <span class="text-[var(--nx-accent)]">{{ $firstName }}</span>@endif
            </h1>
            <p class="text-sm text-[var(--nx-muted)] mt-1">
                @if($myOpenTasksCount === 0)
                    Du hast keine offenen Aufgaben — Zeit für einen Kaffee.
                @else
                    Du hast <span class="font-medium text-[var(--nx-text)] tabular-nums">{{ $myOpenTasksCount }}</span> offene Aufgabe{{ $myOpenTasksCount === 1 ? '' : 'n' }}
                    @if($myOverdueCount > 0)
                        — davon <span class="text-[var(--nx-danger)] font-medium tabular-nums">{{ $myOverdueCount }} überfällig</span>
                    @elseif($myDueTodayCount > 0)
                        — <span class="text-[color:var(--nx-warning)] font-medium tabular-nums">{{ $myDueTodayCount }}</span> heute fällig
                    @endif
                    .
                @endif
            </p>
        </div>

        {{-- Quick Navigation Cards --}}
        @php
            $quickCards = [
                ['route' => 'planner.my-tasks',        'icon' => 'heroicon-o-clipboard-document-check', 'emoji' => null, 'count' => $myOpenTasksCount,  'label' => 'Meine Aufgaben'],
                ['route' => 'planner.frog-tasks',      'icon' => null, 'emoji' => '🐸',                  'count' => $myFrogsCount,      'label' => 'Frösche'],
                ['route' => 'planner.delegated-tasks', 'icon' => 'heroicon-o-user-group',               'emoji' => null, 'count' => $delegatedOpenCount,'label' => 'Delegiert'],
                ['route' => 'planner.completed-tasks', 'icon' => 'heroicon-o-check-circle',             'emoji' => null, 'count' => null,               'label' => 'Erledigt', 'sub' => 'Verlauf'],
            ];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
            @foreach($quickCards as $c)
                <a href="{{ route($c['route']) }}" wire:navigate
                    class="group flex items-center gap-3 rounded-[10px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-4 py-3.5 shadow-[var(--nx-shadow-card)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,15,15,.08)]">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-[color:var(--nx-bg)]">
                        @if($c['emoji'])
                            <span class="text-lg">{{ $c['emoji'] }}</span>
                        @else
                            @svg($c['icon'], 'w-5 h-5 text-[color:var(--nx-muted)]')
                        @endif
                    </div>
                    <div class="min-w-0">
                        @if(!is_null($c['count']))
                            <div class="text-xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $c['count'] }}</div>
                            <div class="text-xs text-[color:var(--nx-muted)]">{{ $c['label'] }}</div>
                        @else
                            <div class="text-sm font-medium leading-tight text-[color:var(--nx-text)]">{{ $c['label'] }}</div>
                            <div class="text-xs text-[color:var(--nx-muted)]">{{ $c['sub'] ?? '' }}</div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Meine überfälligen Aufgaben --}}
        @if($overdueTasksList->count() > 0)
            <div class="rounded-xl border border-[var(--nx-danger)]/30 bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] overflow-hidden mb-8">
                <div class="px-4 py-3 border-b border-[var(--nx-danger)]/20 bg-[var(--nx-danger)]/5 flex items-center gap-2">
                    @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-[var(--nx-danger)]')
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--nx-danger)] m-0">
                        Meine überfälligen Aufgaben
                    </h3>
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-bold rounded-full bg-[var(--nx-danger)] text-white">{{ $myOverdueCount }}</span>
                </div>
                <div class="divide-y divide-[var(--nx-danger)]/10">
                    @foreach($overdueTasksList as $task)
                        @php
                            $daysOverdue = now()->startOfDay()->diffInDays($task->due_date->startOfDay());
                            $priorityColor = $task->priority?->color() ?? 'var(--nx-muted)';
                        @endphp
                        <div class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--nx-danger)]/5 transition group">
                            <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full bg-[var(--nx-danger)]"></span>
                            <button
                                type="button"
                                x-data="{ press: null }"
                                @mousedown.stop="press = { x: $event.clientX, y: $event.clientY }"
                                @click.stop.prevent="
                                    const ok = press && Math.abs($event.clientX - press.x) < 5 && Math.abs($event.clientY - press.y) < 5;
                                    press = null;
                                    if (ok) $wire.quickToggleDone({{ $task->id }});
                                "
                                class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors border-[var(--nx-line-strong)] text-transparent hover:border-[var(--nx-success)] hover:text-[var(--nx-success)] cursor-pointer"
                                title="Als erledigt markieren"
                            >
                                @svg('heroicon-s-check', 'w-3 h-3')
                            </button>
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }}"></span>
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="flex-1 min-w-0 text-sm font-medium text-[var(--nx-text)] truncate group-hover:text-[var(--nx-danger)]">{{ $task->title }}</a>
                            @if($task->project)
                                <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-[var(--nx-muted)] truncate max-w-[140px]">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--nx-muted)' }};"></span>
                                    {{ $task->project->name }}
                                </span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums bg-[var(--nx-danger)]/10 text-[var(--nx-danger)] flex-shrink-0">{{ (int) $daysOverdue }}d zu spät</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Main: Anstehend + Meine Aufgaben (Projekte sind in der linken Sidebar) --}}
        <div class="space-y-8">

                {{-- Meine anstehenden Aufgaben (nächste 7 Tage) --}}
                @if($upcomingTasksList->count() > 0)
                    <div class="rounded-xl border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                        <div class="px-4 py-3 border-b border-[color:var(--nx-line)] bg-[var(--nx-bg)] flex items-center gap-2">
                            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--nx-accent)]')
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--nx-text)] m-0">Meine anstehenden Aufgaben</h3>
                            <span class="text-[10px] text-[var(--nx-muted)]">nächste 7 Tage</span>
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]">{{ $upcomingTasksList->count() }}</span>
                        </div>
                        <div class="divide-y divide-[color:var(--nx-line)]">
                            @foreach($upcomingTasksList as $task)
                                @php
                                    $daysLeft = now()->startOfDay()->diffInDays($task->due_date->startOfDay(), false);
                                    $isUrgent = $daysLeft <= 1;
                                    $priorityColor = $task->priority?->color() ?? 'var(--nx-muted)';
                                    $edgeColor = $isUrgent ? 'var(--nx-warning)' : 'var(--nx-accent)';
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--nx-bg)] transition group">
                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }};"></span>
                                    <span class="flex-1 min-w-0 text-sm font-medium text-[var(--nx-text)] truncate group-hover:text-[var(--nx-accent)]">{{ $task->title }}</span>
                                    @if($task->project)
                                        <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-[var(--nx-muted)] truncate max-w-[140px]">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--nx-muted)' }};"></span>
                                            {{ $task->project->name }}
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums flex-shrink-0
                                        {{ $isUrgent ? 'bg-[rgba(232,89,12,0.16)] text-[color:var(--nx-warning)]' : 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' }}">
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
                <div class="rounded-xl border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                    <div class="px-4 py-3 border-b border-[color:var(--nx-line)] bg-[var(--nx-bg)] flex items-center gap-2">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 text-[var(--nx-accent)]')
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--nx-text)] m-0">Meine Aufgaben</h3>
                        <span class="ml-auto inline-flex items-center gap-2">
                            @if($myOpenTasksCount > $myTasksList->count())
                                <a href="{{ route('planner.my-tasks') }}" wire:navigate class="text-[10px] font-medium text-[var(--nx-accent)] hover:underline">
                                    Alle {{ $myOpenTasksCount }} anzeigen →
                                </a>
                            @else
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]">{{ $myOpenTasksCount }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="divide-y divide-[color:var(--nx-line)]">
                        @forelse($myTasksList as $task)
                            @php
                                $pColor = $task->priority?->color() ?? 'var(--nx-muted)';
                                $taskOverdue = $task->due_date && $task->due_date->isPast();
                                $edgeColor = $taskOverdue ? 'var(--nx-danger)' : $pColor;
                            @endphp
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--nx-bg)] transition group">
                                <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>
                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $pColor }};"></span>
                                <span class="flex-1 min-w-0 text-sm font-medium text-[var(--nx-text)] truncate group-hover:text-[var(--nx-accent)]">{{ $task->title }}</span>
                                @if($task->project)
                                    <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-[var(--nx-muted)] truncate max-w-[140px]">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--nx-muted)' }};"></span>
                                        {{ $task->project->name }}
                                    </span>
                                @endif
                                @if($task->due_date)
                                    <span class="text-xs flex-shrink-0 tabular-nums {{ $taskOverdue ? 'text-[var(--nx-danger)] font-semibold' : 'text-[var(--nx-muted)]' }}">{{ $task->due_date->format('d.m.') }}</span>
                                @else
                                    <span class="text-[10px] flex-shrink-0 text-[var(--nx-muted)]/60 italic">offen</span>
                                @endif
                            </a>
                        @empty
                            <div class="px-3 py-8 text-sm text-[var(--nx-muted)] text-center">
                                @svg('heroicon-o-check-circle', 'w-8 h-8 mx-auto mb-2 opacity-30 text-[var(--nx-success)]')
                                Keine offenen Aufgaben — gut so.
                            </div>
                        @endforelse
                    </div>
                </div>

        </div>

    </x-ui-page-container>
</x-ui-page>
