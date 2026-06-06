<x-ui-page>
    @include('planner::partials.planner-tokens')

    <x-slot name="navbar">
        <x-ui-page-navbar title="Hygiene" icon="heroicon-o-shield-check" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Hygiene'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" icon="heroicon-o-funnel" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--ui-secondary)] leading-relaxed m-0">
                        Was wurde lange nicht angesehen? Projekte gelten nach <strong>{{ $projectHygieneDays }}</strong> Tagen als vernachlässigt, Aufgaben nach <strong>{{ $taskHygieneDays }}</strong>.
                    </p>
                </section>

                {{-- ANSICHT --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Ansicht</h3>
                    <div class="inline-flex rounded-md border border-[var(--ui-border)]/60 overflow-hidden w-full">
                        <button
                            wire:click="$set('tab', 'stale')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 h-7 text-[11px] transition-colors {{ $tab === 'stale' ? 'bg-[var(--planner-status-overdue)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                        >
                            @svg('heroicon-o-archive-box-x-mark', 'w-3.5 h-3.5')
                            Vergessen
                        </button>
                        <button
                            wire:click="$set('tab', 'recent')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 h-7 text-[11px] border-l border-[var(--ui-border)]/60 transition-colors {{ $tab === 'recent' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                        >
                            @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                            Kürzlich
                        </button>
                    </div>
                </section>

                {{-- ENTITY-TYP --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Anzeigen</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button wire:click="$set('entityType', 'all')" class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $entityType === 'all' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}">Alles</button>
                        <button wire:click="$set('entityType', 'projects')" class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $entityType === 'projects' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}">Projekte</button>
                        <button wire:click="$set('entityType', 'tasks')" class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $entityType === 'tasks' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}">Aufgaben</button>
                    </div>
                </section>

                {{-- PROJEKT-FILTER (nur Stale) --}}
                @if($tab === 'stale' && $availableProjects->isNotEmpty())
                    <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Projekt</h3>
                        <div class="space-y-0.5 max-h-48 overflow-y-auto">
                            <button
                                wire:click="$set('projectFilter', null)"
                                class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $projectFilter === null ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                            >Alle</button>
                            @foreach($availableProjects as $proj)
                                <button
                                    wire:click="$set('projectFilter', {{ $proj->id }})"
                                    class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors flex items-center gap-2 {{ $projectFilter == $proj->id ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $proj->color ?? 'var(--ui-muted)' }};"></span>
                                    <span class="truncate">{{ $proj->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Header: Live-KPIs --}}
        <div class="px-4 pt-3 pb-2 border-b border-[var(--ui-border)]/40 bg-white">
            <div class="flex items-start justify-between gap-6">
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-[var(--ui-secondary)] truncate m-0 leading-tight inline-flex items-center gap-2">
                        @svg('heroicon-o-shield-check', 'w-4 h-4 text-[var(--planner-status-active)]')
                        Hygiene
                    </h1>
                    <p class="text-[11px] text-[var(--ui-muted)] mt-0.5 m-0">
                        @if($tab === 'stale')
                            Was im Backlog Staub fängt — sortiert nach Vernachlässigung.
                        @else
                            Was in den letzten Tagen geöffnet wurde.
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 {{ $staleProjectsCount > 0 ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-secondary)]' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $staleProjectsCount > 0 ? 'bg-[var(--planner-status-overdue)]' : 'bg-[var(--planner-status-done)]' }}"></span>
                        <span class="font-semibold tabular-nums">{{ $staleProjectsCount }}</span>
                        <span class="text-[var(--ui-muted)]">Projekt-Leichen</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 {{ $staleTasksCount > 0 ? 'text-amber-600' : 'text-[var(--ui-secondary)]' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $staleTasksCount > 0 ? 'bg-amber-500' : 'bg-[var(--planner-status-done)]' }}"></span>
                        <span class="font-semibold tabular-nums">{{ $staleTasksCount }}</span>
                        <span class="text-[var(--ui-muted)]">Task-Leichen</span>
                    </span>
                    @if($staleOverdue > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-overdue)]">
                            @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                            <span class="font-semibold tabular-nums">{{ $staleOverdue }}</span>
                            <span>überfällig</span>
                        </span>
                    @endif
                    @if($staleSP > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                            <span class="font-semibold tabular-nums">{{ $staleSP }}</span>
                            <span class="text-[var(--ui-muted)]">SP vergessen</span>
                        </span>
                    @endif
                    @if($neverViewedProjectsCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-overdue)]">
                            @svg('heroicon-o-eye-slash', 'w-3 h-3')
                            <span class="font-semibold tabular-nums">{{ $neverViewedProjectsCount }}</span>
                            <span>nie</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto bg-[var(--ui-muted-5)]">
            <div class="p-6 space-y-6">

            @if($tab === 'stale')
                {{-- ========= VERGESSEN ========= --}}
                @if($staleProjectsCount === 0 && $staleTasksCount === 0)
                    <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm p-12 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--planner-status-done)]/10 mb-3">
                            @svg('heroicon-o-shield-check', 'w-7 h-7 text-[var(--planner-status-done)]')
                        </div>
                        <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 mb-1">Alles aufgeräumt</h3>
                        <p class="text-sm text-[var(--ui-muted)] m-0">Alle Projekte und Aufgaben wurden kürzlich besucht.</p>
                    </div>
                @else
                    {{-- Stale Projects --}}
                    @if(($entityType === 'all' || $entityType === 'projects') && $staleProjects->isNotEmpty())
                        <section>
                            <div class="flex items-center gap-2 mb-2 px-1">
                                <h2 class="text-sm font-semibold text-[var(--planner-status-overdue)] m-0 inline-flex items-center gap-1.5">
                                    @svg('heroicon-o-archive-box-x-mark', 'w-4 h-4')
                                    Vergessene Projekte
                                </h2>
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">{{ $staleProjectsCount }}</span>
                            </div>
                            <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                                @foreach($staleProjects as $i => $project)
                                    @php
                                        $daysSince = $project->last_viewed_at ? (int) now()->diffInDays($project->last_viewed_at) : null;
                                        $neverViewed = $project->last_viewed_at === null;
                                        $pColor = $project->color ?? null;
                                        $severity = $neverViewed || $daysSince >= 30 ? 'critical' : ($daysSince >= 14 ? 'high' : 'medium');
                                        $edgeColor = match($severity) {
                                            'critical' => '#b91c1c',
                                            'high'     => 'var(--planner-status-overdue)',
                                            default    => '#d97706',
                                        };
                                    @endphp
                                    <a href="{{ route('planner.projects.show', ['plannerProject' => $project->id]) }}" wire:navigate
                                       class="relative flex items-center gap-3 pl-5 pr-4 py-3 hover:bg-[var(--ui-muted-5)] transition-colors group {{ $i > 0 ? 'border-t border-[var(--ui-border)]/40' : '' }}">
                                        <span class="absolute top-2.5 bottom-2.5 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>

                                        @if($pColor)
                                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $pColor }}"></span>
                                        @else
                                            @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                        @endif

                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-overdue)]">{{ $project->name }}</div>
                                            <div class="flex items-center gap-3 text-[10px] text-[var(--ui-muted)] mt-0.5">
                                                <span class="tabular-nums">{{ $project->open_tasks_count }} offen / {{ $project->total_tasks_count }} gesamt</span>
                                                @if($project->open_tasks_count === 0 && $project->total_tasks_count > 0)
                                                    <span class="text-[var(--planner-status-done)] font-medium">Alle erledigt</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if($neverViewed)
                                            <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full text-white" style="background-color: {{ $edgeColor }};">
                                                @svg('heroicon-o-eye-slash', 'w-3 h-3')
                                                Nie
                                            </span>
                                        @elseif($daysSince !== null)
                                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums" style="background-color: color-mix(in srgb, {{ $edgeColor }} 14%, white); color: {{ $edgeColor }};">{{ $daysSince }}d</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- Stale Tasks --}}
                    @if(($entityType === 'all' || $entityType === 'tasks') && $staleTasks->isNotEmpty())
                        @php $groupedStaleTasks = $staleTasks->groupBy(fn($t) => $t->project?->name ?? 'Ohne Projekt'); @endphp
                        <section>
                            <div class="flex items-center gap-2 mb-2 px-1">
                                <h2 class="text-sm font-semibold text-amber-600 m-0 inline-flex items-center gap-1.5">
                                    @svg('heroicon-o-clock', 'w-4 h-4')
                                    Vergessene Aufgaben
                                </h2>
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-amber-100 text-amber-600">{{ $staleTasksCount }}</span>
                            </div>
                            <div class="space-y-4">
                                @foreach($groupedStaleTasks as $projectName => $tasks)
                                    <div>
                                        <div class="flex items-center gap-2 mb-1.5 px-1">
                                            <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $projectName }}</span>
                                            <span class="text-[10px] text-[var(--ui-muted)] tabular-nums">{{ $tasks->count() }}</span>
                                        </div>
                                        <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                                            @foreach($tasks as $i => $task)
                                                @php
                                                    $daysSince = $task->last_viewed_at ? (int) now()->diffInDays($task->last_viewed_at) : null;
                                                    $neverViewed = $task->last_viewed_at === null;
                                                    $isOverdue = $task->due_date && $task->due_date->isPast();
                                                    $priorityColor = $task->priority?->color() ?? 'var(--ui-muted)';
                                                    $edgeColor = $isOverdue
                                                        ? 'var(--planner-status-overdue)'
                                                        : ($neverViewed ? '#d97706' : $priorityColor);
                                                @endphp
                                                <div class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors group {{ $i > 0 ? 'border-t border-[var(--ui-border)]/40' : '' }}">
                                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>

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

                                                    <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}?from=hygiene" wire:navigate class="flex-1 min-w-0">
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)] truncate block group-hover:text-amber-700">{{ $task->title }}</span>
                                                        <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)] mt-0.5">
                                                            @if($task->userInCharge)
                                                                <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span>
                                                            @endif
                                                            @if($task->due_date)
                                                                <span class="{{ $isOverdue ? 'text-[var(--planner-status-overdue)] font-semibold' : '' }} tabular-nums">{{ $task->due_date->format('d.m.Y') }}</span>
                                                            @endif
                                                        </div>
                                                    </a>

                                                    @if($isOverdue)
                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">überfällig</span>
                                                    @endif
                                                    @if($neverViewed)
                                                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-500 text-white">
                                                            @svg('heroicon-o-eye-slash', 'w-3 h-3')
                                                            Nie
                                                        </span>
                                                    @elseif($daysSince !== null)
                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums bg-amber-100 text-amber-700">{{ $daysSince }}d</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif

            @else
                {{-- ========= KÜRZLICH ========= --}}

                @if(($entityType === 'all' || $entityType === 'projects') && $recentProjects->isNotEmpty())
                    <section>
                        <div class="flex items-center gap-2 mb-2 px-1">
                            <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0 inline-flex items-center gap-1.5">
                                @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--planner-status-active)]')
                                Kürzlich besucht — Projekte
                            </h2>
                            <span class="text-[10px] text-[var(--ui-muted)]">letzte 14 Tage</span>
                        </div>
                        <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                            @foreach($recentProjects as $i => $project)
                                @php $pColor = $project->color ?? null; @endphp
                                <a href="{{ route('planner.projects.show', ['plannerProject' => $project->id]) }}" wire:navigate
                                   class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors group {{ $i > 0 ? 'border-t border-[var(--ui-border)]/40' : '' }}">
                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $pColor ?? 'var(--planner-status-active)' }};"></span>

                                    @if($pColor)
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $pColor }}"></span>
                                    @else
                                        @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-[var(--ui-secondary)] truncate block">{{ $project->name }}</span>
                                        <span class="text-[10px] text-[var(--ui-muted)] tabular-nums">{{ $project->open_tasks_count }} offen</span>
                                    </div>
                                    <span class="flex-shrink-0 text-[10px] text-[var(--ui-muted)]">{{ $project->last_viewed_at->diffForHumans() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(($entityType === 'all' || $entityType === 'tasks') && $recentTasks->isNotEmpty())
                    <section>
                        <div class="flex items-center gap-2 mb-2 px-1">
                            <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0 inline-flex items-center gap-1.5">
                                @svg('heroicon-o-clipboard-document', 'w-4 h-4 text-[var(--planner-status-active)]')
                                Kürzlich besucht — Aufgaben
                            </h2>
                            <span class="text-[10px] text-[var(--ui-muted)]">letzte 7 Tage</span>
                        </div>
                        <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                            @foreach($recentTasks as $i => $task)
                                @php
                                    $priorityColor = match($task->priority?->value ?? null) {
                                        'high'   => 'var(--planner-priority-high)',
                                        'normal' => 'var(--planner-priority-normal)',
                                        'low'    => 'var(--planner-priority-low)',
                                        default  => 'var(--planner-status-active)',
                                    };
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}?from=hygiene" wire:navigate
                                   class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors group {{ $i > 0 ? 'border-t border-[var(--ui-border)]/40' : '' }}">
                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $priorityColor }};"></span>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-[var(--ui-secondary)] truncate block">{{ $task->title }}</span>
                                        <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)] mt-0.5">
                                            @if($task->project)
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--ui-muted)' }};"></span>
                                                    {{ $task->project->name }}
                                                </span>
                                            @endif
                                            @if($task->userInCharge)
                                                <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="flex-shrink-0 text-[10px] text-[var(--ui-muted)]">{{ $task->last_viewed_at->diffForHumans() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(($entityType === 'all' || $entityType === 'projects') && $recentProjects->isEmpty() && ($entityType === 'all' || $entityType === 'tasks') && $recentTasks->isEmpty())
                    <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm p-12 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--ui-muted-5)] mb-3">
                            @svg('heroicon-o-eye', 'w-7 h-7 text-[var(--ui-muted)]')
                        </div>
                        <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 mb-1">Nichts Kürzliches</h3>
                        <p class="text-sm text-[var(--ui-muted)] m-0">Keine kürzlich besuchten Projekte oder Aufgaben.</p>
                    </div>
                @endif

            @endif

            </div>
        </div>
    </div>
</x-ui-page>
