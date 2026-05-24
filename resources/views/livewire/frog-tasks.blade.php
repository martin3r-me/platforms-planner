@include('planner::partials.planner-tokens')
@php
    $hasActiveFilters = $userFilter || $projectFilter || $priorityFilter || $overdueOnly;
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Frösche" icon="heroicon-o-exclamation-triangle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Projekte', 'href' => route('planner.dashboard'), 'icon' => 'clipboard-document-list'],
            ['label' => 'Frösche'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter & Übersicht" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-5">

                {{-- KPI Grid --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="px-3 py-2 rounded border border-[var(--planner-frog)]/20 bg-[var(--planner-frog)]/5">
                        <div class="text-lg font-bold text-[var(--planner-frog)]">{{ $totalCount }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Frösche</div>
                    </div>
                    <div class="px-3 py-2 rounded border {{ $overdueCount > 0 ? 'border-[var(--planner-status-overdue)]/20 bg-[var(--planner-status-overdue)]/5' : 'border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]' }}">
                        <div class="text-lg font-bold {{ $overdueCount > 0 ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-secondary)]' }}">{{ $overdueCount }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Überfällig</div>
                    </div>
                    <div class="px-3 py-2 rounded border {{ $highPriorityCount > 0 ? 'border-[var(--planner-priority-high)]/20 bg-[var(--planner-priority-high)]/5' : 'border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]' }}">
                        <div class="text-lg font-bold {{ $highPriorityCount > 0 ? 'text-[var(--planner-priority-high)]' : 'text-[var(--ui-secondary)]' }}">{{ $highPriorityCount }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Hoch</div>
                    </div>
                    <div class="px-3 py-2 rounded border border-[var(--planner-status-active)]/20 bg-[var(--planner-status-active)]/5">
                        <div class="text-lg font-bold text-[var(--planner-status-active)]">{{ $totalPoints }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">SP</div>
                    </div>
                </div>

                {{-- Extra-Info --}}
                <div class="space-y-1 text-xs">
                    @if($forcedFrogCount > 0)
                        <div class="flex items-center justify-between py-1.5 px-2 rounded bg-[var(--planner-status-overdue)]/5">
                            <span class="text-[var(--ui-muted)]">Zwangs-Frösche</span>
                            <span class="font-semibold text-[var(--planner-status-overdue)]">{{ $forcedFrogCount }}</span>
                        </div>
                    @endif
                    @if($withoutDueDate > 0)
                        <div class="flex items-center justify-between py-1.5 px-2 rounded bg-[var(--ui-muted-5)]">
                            <span class="text-[var(--ui-muted)]">Ohne Fälligkeit</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $withoutDueDate }}</span>
                        </div>
                    @endif
                </div>

                <div class="border-t border-[var(--ui-border)]/40"></div>

                {{-- Quick-Filter: Überfällig --}}
                <button
                    wire:click="$toggle('overdueOnly')"
                    class="w-full flex items-center justify-between py-2 px-3 rounded-lg text-xs font-medium transition-colors {{ $overdueOnly ? 'bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)] border border-[var(--planner-status-overdue)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}"
                >
                    <span class="inline-flex items-center gap-1.5">
                        @svg('heroicon-o-exclamation-circle', 'w-3.5 h-3.5')
                        Nur Überfällige
                    </span>
                    @if($overdueOnly)
                        @svg('heroicon-s-check', 'w-3.5 h-3.5')
                    @endif
                </button>

                {{-- Priorität --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-1">Priorität</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            wire:click="$set('priorityFilter', null)"
                            class="px-2.5 py-1 text-xs rounded-full transition-colors {{ $priorityFilter === null ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}"
                        >Alle</button>
                        <button
                            wire:click="$set('priorityFilter', 'high')"
                            class="px-2.5 py-1 text-xs rounded-full transition-colors {{ $priorityFilter === 'high' ? 'bg-[var(--planner-priority-high)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}"
                        >Hoch</button>
                        <button
                            wire:click="$set('priorityFilter', 'normal')"
                            class="px-2.5 py-1 text-xs rounded-full transition-colors {{ $priorityFilter === 'normal' ? 'bg-[var(--planner-priority-normal)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}"
                        >Normal</button>
                        <button
                            wire:click="$set('priorityFilter', 'low')"
                            class="px-2.5 py-1 text-xs rounded-full transition-colors {{ $priorityFilter === 'low' ? 'bg-[var(--planner-priority-low)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}"
                        >Niedrig</button>
                    </div>
                </div>

                {{-- Person --}}
                @if($availableUsers->isNotEmpty())
                    <div>
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-1">Person</h3>
                        <div class="space-y-0.5">
                            <button
                                wire:click="$set('userFilter', null)"
                                class="w-full text-left px-2.5 py-1.5 rounded text-xs transition-colors {{ $userFilter === null ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                            >Alle</button>
                            @foreach($availableUsers as $u)
                                <button
                                    wire:click="$set('userFilter', {{ $u->id }})"
                                    class="w-full text-left px-2.5 py-1.5 rounded text-xs transition-colors flex items-center gap-2 {{ $userFilter == $u->id ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >
                                    @if($u->avatar)
                                        <img src="{{ $u->avatar }}" alt="" class="w-4 h-4 rounded-full object-cover flex-shrink-0">
                                    @else
                                        <span class="w-4 h-4 rounded-full bg-[var(--ui-muted-10)] flex items-center justify-center text-[9px] font-medium text-[var(--ui-muted)] flex-shrink-0">{{ mb_strtoupper(mb_substr($u->name ?? 'U', 0, 1)) }}</span>
                                    @endif
                                    <span class="truncate">{{ $u->fullname ?? $u->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Projekt --}}
                @if($availableProjects->isNotEmpty())
                    <div>
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-1">Projekt</h3>
                        <div class="space-y-0.5">
                            <button
                                wire:click="$set('projectFilter', null)"
                                class="w-full text-left px-2.5 py-1.5 rounded text-xs transition-colors {{ $projectFilter === null ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                            >Alle</button>
                            @foreach($availableProjects as $proj)
                                <button
                                    wire:click="$set('projectFilter', {{ $proj->id }})"
                                    class="w-full text-left px-2.5 py-1.5 rounded text-xs transition-colors flex items-center gap-2 {{ $projectFilter == $proj->id ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >
                                    @if($proj->color)
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $proj->color }}"></span>
                                    @else
                                        @svg('heroicon-o-folder', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                                    @endif
                                    <span class="truncate">{{ $proj->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Gruppierung --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-1">Gruppieren nach</h3>
                    <div class="flex gap-1">
                        <button wire:click="$set('groupBy', 'project')" class="flex-1 px-2 py-1.5 text-[10px] rounded transition-colors {{ $groupBy === 'project' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}">Projekt</button>
                        <button wire:click="$set('groupBy', 'person')" class="flex-1 px-2 py-1.5 text-[10px] rounded transition-colors {{ $groupBy === 'person' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}">Person</button>
                        <button wire:click="$set('groupBy', 'priority')" class="flex-1 px-2 py-1.5 text-[10px] rounded transition-colors {{ $groupBy === 'priority' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}">Priorität</button>
                    </div>
                </div>

                {{-- Clear filters --}}
                @if($hasActiveFilters)
                    <button
                        wire:click="$set('userFilter', null); $set('projectFilter', null); $set('priorityFilter', null); $set('overdueOnly', false)"
                        class="w-full text-center py-2 text-xs text-[var(--ui-muted)] hover:text-[var(--planner-status-overdue)] transition-colors"
                    >
                        Alle Filter zurücksetzen
                    </button>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-4">

        {{-- Active filter bar --}}
        @if($hasActiveFilters)
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[var(--ui-muted)]">
                    @svg('heroicon-o-funnel', 'w-4 h-4')
                </span>
                @if($overdueOnly)
                    <button wire:click="$set('overdueOnly', false)" class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-[var(--planner-status-overdue)] text-white">
                        Überfällig @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif
                @if($priorityFilter)
                    <button wire:click="$set('priorityFilter', null)" class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-[var(--ui-secondary)] text-white">
                        {{ \Platform\Planner\Enums\TaskPriority::from($priorityFilter)->label() }} @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif
                @if($userFilter)
                    <button wire:click="$set('userFilter', null)" class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-[var(--planner-status-active)] text-white">
                        {{ $availableUsers->firstWhere('id', $userFilter)?->name ?? 'Person' }} @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif
                @if($projectFilter)
                    <button wire:click="$set('projectFilter', null)" class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-[var(--planner-status-active)] text-white">
                        {{ $availableProjects->firstWhere('id', $projectFilter)?->name ?? 'Projekt' }} @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif
                <span class="text-xs text-[var(--ui-muted)] ml-1">{{ $filteredCount }} von {{ $totalCount }}</span>
            </div>
        @endif

        {{-- Content --}}
        @if($groupedTasks->isEmpty())
            <div class="py-16 text-center">
                <div class="text-4xl mb-3">🐸</div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-1">
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
                <div>
                    <div class="flex items-center gap-2 mb-2 px-1">
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $groupLabel }}</span>
                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full" style="background-color: color-mix(in srgb, var(--planner-frog) 15%, transparent); color: var(--planner-frog)">{{ $tasks->count() }}</span>
                    </div>
                    <div class="rounded-lg border border-[var(--ui-border)]/60 overflow-hidden divide-y divide-[var(--ui-border)]/40">
                        @foreach($tasks as $task)
                            @php
                                $isOverdue = $task->due_date && $task->due_date->isPast();
                                $daysOverdue = $isOverdue ? now()->startOfDay()->diffInDays($task->due_date->startOfDay()) : 0;
                                $priorityColor = match($task->priority?->value ?? null) {
                                    'high' => 'var(--planner-priority-high)',
                                    'normal' => 'var(--planner-priority-normal)',
                                    'low' => 'var(--planner-priority-low)',
                                    default => 'var(--ui-muted)',
                                };
                            @endphp
                            <a
                                href="{{ route('planner.tasks.show', $task) }}"
                                wire:navigate
                                class="flex items-center gap-3 px-4 py-3 hover:bg-[var(--ui-muted-5)] transition-colors group {{ $isOverdue ? 'bg-[var(--planner-card-overdue)]' : '' }}"
                            >
                                {{-- Priority dot --}}
                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }}"></span>

                                {{-- Frog indicator --}}
                                <span class="flex-shrink-0 text-sm">🐸</span>

                                {{-- Title + meta --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-active)]">{{ $task->title }}</span>
                                        @if($task->is_forced_frog)
                                            <span class="flex-shrink-0 text-[9px] font-semibold px-1.5 py-0.5 rounded bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">Zwang</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 mt-0.5 text-[10px] text-[var(--ui-muted)]">
                                        @if($groupBy !== 'project' && $task->project)
                                            <span>{{ $task->project->name }}</span>
                                        @endif
                                        @if($groupBy !== 'person' && $task->userInCharge)
                                            <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span>
                                        @endif
                                        @if($task->story_points)
                                            <span>{{ $task->story_points->points() }} SP</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Due date / overdue badge --}}
                                @if($task->due_date)
                                    @if($isOverdue)
                                        <span class="flex-shrink-0 text-xs font-semibold text-[var(--planner-status-overdue)] tabular-nums">-{{ (int) $daysOverdue }}d</span>
                                    @else
                                        <span class="flex-shrink-0 text-xs text-[var(--ui-muted)] tabular-nums">{{ $task->due_date->format('d.m.') }}</span>
                                    @endif
                                @else
                                    <span class="flex-shrink-0 text-[10px] text-[var(--ui-muted)]/50">kein Datum</span>
                                @endif

                                {{-- Assignee avatar --}}
                                @if($task->userInCharge && $groupBy !== 'person')
                                    @if($task->userInCharge->avatar)
                                        <img src="{{ $task->userInCharge->avatar }}" alt="" class="w-5 h-5 rounded-full object-cover flex-shrink-0">
                                    @else
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-10)] text-[9px] font-medium text-[var(--ui-muted)] flex-shrink-0">{{ mb_strtoupper(mb_substr($task->userInCharge->name ?? 'U', 0, 1)) }}</span>
                                    @endif
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </x-ui-page-container>
</x-ui-page>
