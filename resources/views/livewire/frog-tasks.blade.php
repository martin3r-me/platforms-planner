@php
    $hasActiveFilters = $userFilter || $projectFilter || $priorityFilter || $overdueOnly;
@endphp

<x-ui-page>
    @include('planner::partials.planner-tokens')

    <x-slot name="navbar">
        <x-ui-page-navbar title="Frösche" icon="heroicon-o-exclamation-triangle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Frösche'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" icon="heroicon-o-funnel" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--ui-secondary)] leading-relaxed m-0">
                        Frösche sind die wichtigsten oder unangenehmsten Aufgaben — die du <em>zuerst</em> erledigen solltest, damit der Rest leicht wird.
                    </p>
                </section>

                {{-- QUICK-FILTER --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm space-y-3">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Schnell-Filter</h3>
                    <button
                        wire:click="$toggle('overdueOnly')"
                        class="w-full flex items-center justify-between py-1.5 px-2.5 rounded-md text-[11px] font-medium transition-colors {{ $overdueOnly ? 'bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)] border border-[var(--planner-status-overdue)]/30' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)] border border-transparent' }}"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            @svg('heroicon-o-exclamation-circle', 'w-3.5 h-3.5')
                            Nur Überfällige
                        </span>
                        @if($overdueOnly)
                            @svg('heroicon-s-check', 'w-3.5 h-3.5')
                        @endif
                    </button>
                </section>

                {{-- PRIORITÄT --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Priorität</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            wire:click="$set('priorityFilter', null)"
                            class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $priorityFilter === null ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}"
                        >Alle</button>
                        <button
                            wire:click="$set('priorityFilter', 'high')"
                            class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $priorityFilter === 'high' ? 'bg-[var(--planner-priority-high)] text-white' : 'bg-[var(--planner-priority-high)]/10 text-[var(--planner-priority-high)] hover:bg-[var(--planner-priority-high)]/20' }}"
                        >Hoch</button>
                        <button
                            wire:click="$set('priorityFilter', 'normal')"
                            class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $priorityFilter === 'normal' ? 'bg-[var(--planner-priority-normal)] text-white' : 'bg-[var(--planner-priority-normal)]/10 text-[var(--planner-priority-normal)] hover:bg-[var(--planner-priority-normal)]/20' }}"
                        >Normal</button>
                        <button
                            wire:click="$set('priorityFilter', 'low')"
                            class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $priorityFilter === 'low' ? 'bg-[var(--planner-priority-low)] text-white' : 'bg-[var(--planner-priority-low)]/10 text-[var(--planner-priority-low)] hover:bg-[var(--planner-priority-low)]/20' }}"
                        >Niedrig</button>
                    </div>
                </section>

                {{-- PERSON --}}
                @if($availableUsers->isNotEmpty())
                    <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Person</h3>
                        <div class="space-y-0.5 max-h-48 overflow-y-auto">
                            <button
                                wire:click="$set('userFilter', null)"
                                class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $userFilter === null ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                            >Alle</button>
                            @foreach($availableUsers as $u)
                                <button
                                    wire:click="$set('userFilter', {{ $u->id }})"
                                    class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors flex items-center gap-2 {{ $userFilter == $u->id ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >
                                    @if($u->avatar)
                                        <img src="{{ $u->avatar }}" alt="" class="w-4 h-4 rounded-full object-cover flex-shrink-0">
                                    @else
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-secondary)] text-white text-[8px] font-semibold flex-shrink-0">{{ mb_strtoupper(mb_substr($u->name ?? 'U', 0, 1)) }}</span>
                                    @endif
                                    <span class="truncate">{{ $u->fullname ?? $u->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- PROJEKT --}}
                @if($availableProjects->isNotEmpty())
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

                {{-- GRUPPIERUNG --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Gruppieren nach</h3>
                    <div class="inline-flex rounded-md border border-[var(--ui-border)]/60 overflow-hidden w-full">
                        <button wire:click="$set('groupBy', 'project')" class="flex-1 px-2 py-1 text-[11px] transition-colors {{ $groupBy === 'project' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">Projekt</button>
                        <button wire:click="$set('groupBy', 'person')" class="flex-1 px-2 py-1 text-[11px] border-l border-[var(--ui-border)]/60 transition-colors {{ $groupBy === 'person' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">Person</button>
                        <button wire:click="$set('groupBy', 'priority')" class="flex-1 px-2 py-1 text-[11px] border-l border-[var(--ui-border)]/60 transition-colors {{ $groupBy === 'priority' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">Priorität</button>
                    </div>
                </section>

                @if($hasActiveFilters)
                    <button
                        wire:click="$set('userFilter', null); $set('projectFilter', null); $set('priorityFilter', null); $set('overdueOnly', false)"
                        class="w-full text-center py-1.5 text-[11px] text-[var(--ui-muted)] hover:text-[var(--planner-status-overdue)] transition-colors"
                    >
                        Alle Filter zurücksetzen
                    </button>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Header: Live-Counts --}}
        <div class="px-4 pt-3 pb-2 border-b border-[var(--ui-border)]/40 bg-white">
            <div class="flex items-start justify-between gap-6">
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-[var(--ui-secondary)] truncate m-0 leading-tight inline-flex items-center gap-2">
                        <span class="text-lg">🐸</span> Frösche
                    </h1>
                    <p class="text-[11px] text-[var(--ui-muted)] mt-0.5 m-0">
                        Die wichtigsten Brocken — erst beißen, dann der Rest läuft.
                    </p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0 text-[11px]">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="text-lg">🐸</span>
                        <span class="font-semibold tabular-nums text-[var(--planner-frog)]">{{ $totalCount }}</span>
                        <span class="text-[var(--ui-muted)]">gesamt</span>
                    </span>
                    @if($overdueCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-overdue)]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-overdue)]"></span>
                            <span class="font-semibold tabular-nums">{{ $overdueCount }}</span>
                            <span>überfällig</span>
                        </span>
                    @endif
                    @if($highPriorityCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-priority-high)]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-priority-high)]"></span>
                            <span class="font-semibold tabular-nums">{{ $highPriorityCount }}</span>
                            <span>hoch</span>
                        </span>
                    @endif
                    @if($forcedFrogCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-overdue)]">
                            @svg('heroicon-o-bolt', 'w-3 h-3')
                            <span class="font-semibold tabular-nums">{{ $forcedFrogCount }}</span>
                            <span>Zwang</span>
                        </span>
                    @endif
                    @if($withoutDueDate > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--ui-muted)]">
                            @svg('heroicon-o-calendar', 'w-3 h-3 opacity-60')
                            <span class="font-semibold tabular-nums">{{ $withoutDueDate }}</span>
                            <span>ohne Datum</span>
                        </span>
                    @endif
                    @if($totalPoints > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                            <span class="font-semibold tabular-nums">{{ $totalPoints }}</span>
                            <span class="text-[var(--ui-muted)]">SP</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Aktive Filter-Chips --}}
        @if($hasActiveFilters)
            <div class="flex items-center gap-1.5 px-4 h-9 border-b border-[var(--ui-border)]/40 bg-white text-[11px]">
                <span class="text-[var(--ui-muted)] flex-shrink-0 mr-1">
                    @svg('heroicon-o-funnel', 'w-3.5 h-3.5')
                </span>
                @if($overdueOnly)
                    <button wire:click="$set('overdueOnly', false)" class="inline-flex items-center gap-1 pl-1.5 pr-1 py-0.5 rounded border border-[var(--planner-status-overdue)]/30 bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)] hover:bg-[var(--planner-status-overdue)]/20 transition-colors">
                        Überfällig
                        @svg('heroicon-o-x-mark', 'w-3 h-3 opacity-60')
                    </button>
                @endif
                @if($priorityFilter)
                    <button wire:click="$set('priorityFilter', null)" class="inline-flex items-center gap-1 pl-1.5 pr-1 py-0.5 rounded border border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)] transition-colors">
                        {{ \Platform\Planner\Enums\TaskPriority::from($priorityFilter)->label() }}
                        @svg('heroicon-o-x-mark', 'w-3 h-3 opacity-60')
                    </button>
                @endif
                @if($userFilter)
                    <button wire:click="$set('userFilter', null)" class="inline-flex items-center gap-1 pl-1.5 pr-1 py-0.5 rounded border border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)] transition-colors">
                        {{ $availableUsers->firstWhere('id', $userFilter)?->name ?? 'Person' }}
                        @svg('heroicon-o-x-mark', 'w-3 h-3 opacity-60')
                    </button>
                @endif
                @if($projectFilter)
                    <button wire:click="$set('projectFilter', null)" class="inline-flex items-center gap-1 pl-1.5 pr-1 py-0.5 rounded border border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)] transition-colors">
                        {{ $availableProjects->firstWhere('id', $projectFilter)?->name ?? 'Projekt' }}
                        @svg('heroicon-o-x-mark', 'w-3 h-3 opacity-60')
                    </button>
                @endif
                <span class="ml-auto text-[var(--ui-muted)] tabular-nums">{{ $filteredCount }} von {{ $totalCount }}</span>
            </div>
        @endif

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto bg-[var(--ui-muted-5)]">
            <div class="p-6 space-y-6">
            @if($groupedTasks->isEmpty())
                <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm p-12 text-center">
                    <div class="text-5xl mb-3">🐸</div>
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] mb-1">
                        @if($hasActiveFilters)
                            Keine Frösche mit diesen Filtern
                        @else
                            Keine Frösche
                        @endif
                    </h3>
                    <p class="text-sm text-[var(--ui-muted)]">
                        @if($hasActiveFilters)
                            Probiere andere Filter oder setze sie zurück.
                        @else
                            Aktuell gibt es keine offenen Frog-Tasks.
                        @endif
                    </p>
                </div>
            @else
                @foreach($groupedTasks as $groupLabel => $tasks)
                    <section>
                        <div class="flex items-center gap-2 mb-2 px-1">
                            <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">{{ $groupLabel }}</h2>
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-frog) 18%, transparent); color: var(--planner-frog)">{{ $tasks->count() }}</span>
                        </div>
                        <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                            @foreach($tasks as $i => $task)
                                @php
                                    $isOverdue = $task->due_date && $task->due_date->isPast();
                                    $daysOverdue = $isOverdue ? now()->startOfDay()->diffInDays($task->due_date->startOfDay()) : 0;
                                    $priorityColor = $task->priority?->color() ?? 'var(--ui-muted)';
                                    $edgeColor = $isOverdue
                                        ? 'var(--planner-status-overdue)'
                                        : ($priorityColor ?? 'var(--planner-frog)');
                                    $uic = $task->userInCharge;
                                    $uicInitial = $uic ? mb_strtoupper(mb_substr($uic->name ?? $uic->email ?? 'U', 0, 1)) : null;
                                @endphp
                                <div class="relative flex items-center gap-3 pl-5 pr-4 py-3 hover:bg-[var(--ui-muted-5)] transition-colors group {{ $i > 0 ? 'border-t border-[var(--ui-border)]/40' : '' }}">
                                    {{-- Color edge --}}
                                    <span class="absolute top-2.5 bottom-2.5 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>

                                    {{-- Quick done --}}
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

                                    <span class="flex-shrink-0 text-base">🐸</span>

                                    <a href="{{ route('planner.tasks.show', $task) }}?from=frog" wire:navigate class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-active)]">{{ $task->title }}</span>
                                            @if($task->is_forced_frog)
                                                <span class="flex-shrink-0 inline-flex items-center gap-0.5 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[var(--planner-status-overdue)] text-white">
                                                    @svg('heroicon-o-bolt', 'w-2.5 h-2.5')
                                                    ZWANG
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 mt-0.5 text-[10px] text-[var(--ui-muted)]">
                                            @if($groupBy !== 'project' && $task->project)
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--ui-muted)' }};"></span>
                                                    {{ $task->project->name }}
                                                </span>
                                            @endif
                                            @if($groupBy !== 'person' && $uic)
                                                <span>{{ $uic->fullname ?? $uic->name }}</span>
                                            @endif
                                            @if($task->story_points)
                                                <span class="tabular-nums">{{ $task->story_points->points() }} SP</span>
                                            @endif
                                        </div>
                                    </a>

                                    {{-- Due / overdue --}}
                                    @if($task->due_date)
                                        @if($isOverdue)
                                            <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">{{ (int) $daysOverdue }}d zu spät</span>
                                        @else
                                            <span class="flex-shrink-0 text-[11px] text-[var(--ui-muted)] tabular-nums">{{ $task->due_date->format('d.m.') }}</span>
                                        @endif
                                    @else
                                        <span class="flex-shrink-0 text-[10px] text-[var(--ui-muted)] italic">kein Datum</span>
                                    @endif

                                    {{-- Avatar --}}
                                    @if($uic && $groupBy !== 'person')
                                        @if($uic->avatar)
                                            <img src="{{ $uic->avatar }}" alt="" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                                        @else
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[var(--ui-secondary)] text-white text-[10px] font-semibold flex-shrink-0">{{ $uicInitial }}</span>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endif
            </div>
        </div>
    </div>
</x-ui-page>
