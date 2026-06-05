@php
    if ($activeTab === 'board') {
        $allTasks = $groups->flatMap(fn($g) => $g->tasks);
        $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
        $doneTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
        $headerOpenCount = $openTasks->count();
        $headerDoneCount = $doneTasks->count();
        $headerOverdueCount = $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count();
    } else {
        $headerOpenCount = $dashboardData['open_count'] ?? 0;
        $headerDoneCount = $dashboardData['done_count'] ?? 0;
        $headerOverdueCount = isset($dashboardData['overdue_tasks']) ? $dashboardData['overdue_tasks']->count() : 0;
    }
    $hasActiveFilters = !empty($filterTagIds) || $filterColor;
@endphp

<x-ui-page
    x-data="{ activeTab: @js($activeTab) }"
    @keydown.n.window.prevent="if (activeTab === 'board') $wire.createTask()"
>
    @include('planner::partials.planner-tokens')
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => $project->name],
        ]">
            <x-slot name="left">
                {{-- View-Tabs als segmented control --}}
                <div class="inline-flex rounded-md border border-[var(--ui-border)]/60 overflow-hidden">
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'dashboard')"
                        class="inline-flex items-center gap-1.5 px-2.5 h-7 text-xs transition-colors {{ $activeTab === 'dashboard' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                    >
                        @svg('heroicon-o-chart-bar-square', 'w-3.5 h-3.5')
                        <span>Dashboard</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'board')"
                        class="inline-flex items-center gap-1.5 px-2.5 h-7 text-xs border-l border-[var(--ui-border)]/60 transition-colors {{ $activeTab === 'board' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                    >
                        @svg('heroicon-o-view-columns', 'w-3.5 h-3.5')
                        <span>Board</span>
                    </button>
                </div>
            </x-slot>

            {{-- Primary action --}}
            @if($activeTab === 'board')
                @can('update', $project)
                    <x-ui-button variant="primary" size="sm" wire:click="createTask()" title="Neue Aufgabe (N)">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Aufgabe</span>
                    </x-ui-button>
                @endcan
            @endif

            {{-- Overflow menu --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center justify-center w-8 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                    title="Mehr"
                >
                    @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    class="absolute top-full right-0 mt-1 w-52 bg-white border border-[var(--ui-border)] rounded-lg shadow-lg z-30 py-1"
                >
                    @if($activeTab === 'board')
                        @can('update', $project)
                            <button
                                type="button"
                                wire:click="createProjectSlot"
                                @click="open = false"
                                class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                            >
                                @svg('heroicon-o-square-2-stack', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span>Neue Spalte</span>
                            </button>
                        @endcan
                    @endif
                    <button
                        type="button"
                        wire:click="openCanvas"
                        @click="open = false"
                        class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                    >
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span>Project Canvas</span>
                    </button>
                    @can('settings', $project)
                        <div class="border-t border-[var(--ui-border)]/60 my-1"></div>
                        <button
                            type="button"
                            @click="open = false; $dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })"
                            class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--ui-muted)]')
                            <span>Einstellungen</span>
                        </button>
                    @endcan
                </div>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Projekt" width="w-72" :defaultOpen="true">
            @include('planner::livewire.project._sidebar', [
                'project' => $project,
                'currentUserRole' => $currentUserRole,
                'hasAnyTasks' => $hasAnyTasks ?? false,
                'userOpenTaskCount' => $userOpenTaskCount ?? 0,
                'allProjectUsers' => $allProjectUsers,
            ])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                            <div class="text-[var(--ui-muted)]">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">
    {{-- Project Header (immer sichtbar, einheitlich für Board + Dashboard) --}}
    @include('planner::livewire.project._header', [
        'project' => $project,
        'openCount' => $headerOpenCount,
        'doneCount' => $headerDoneCount,
        'overdueCount' => $headerOverdueCount,
        'linkedEntities' => $linkedEntities,
    ])

    @if($activeTab === 'board')
        @include('planner::livewire.project._filter-bar', [
            'availableFilterTags' => $availableFilterTags,
            'availableFilterColors' => $availableFilterColors,
            'filterTagIds' => $filterTagIds,
            'filterColor' => $filterColor,
            'hasActiveFilters' => $hasActiveFilters,
        ])

        {{-- Board --}}
        <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
            {{-- Backlog --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                    <x-slot name="headerActions">
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-backlog) 15%, transparent); color: var(--planner-col-backlog)">
                            {{ $backlog->tasks->count() }}
                        </span>
                    </x-slot>
                    @forelse($backlog->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                            @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Backlog ist leer</span>
                            <span class="text-[10px] mt-0.5 opacity-60">Neue Aufgaben landen hier</span>
                        </div>
                    @endforelse
                </x-ui-kanban-column>
            @endif

            {{-- Middle columns --}}
            @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false)) as $column)
                <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                    <x-slot name="headerActions">
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-default) 15%, transparent); color: var(--planner-col-default)">
                            {{ $column->tasks->count() }}
                        </span>
                        @can('update', $project)
                            <button
                                wire:click="createTask('{{ $column->id }}')"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                        @endcan
                        @can('update', $project)
                            <button
                                @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $column->id }} })"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Einstellungen"
                            >
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        @endcan
                    </x-slot>

                    @forelse($column->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                            @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Keine Aufgaben</span>
                            <span class="text-[10px] mt-0.5 opacity-60">Hierher ziehen oder neu erstellen</span>
                        </div>
                    @endforelse
                    @can('update', $project)
                        <x-slot name="footer">
                            <div x-data="{ open: false, title: '' }">
                                <button x-show="!open" @click="open = true; $nextTick(() => $refs.inlineInput.focus())" class="w-full text-left text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors flex items-center gap-1.5">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                    <span>Aufgabe</span>
                                </button>
                                <div x-show="open" x-cloak>
                                    <input
                                        x-ref="inlineInput"
                                        x-model="title"
                                        @keydown.enter.prevent="if(title.trim()) { $wire.createTask('{{ $column->id }}', title.trim()); title = ''; open = false; }"
                                        @keydown.escape="open = false; title = ''"
                                        @click.outside="open = false; title = ''"
                                        type="text"
                                        placeholder="Titel eingeben..."
                                        class="w-full text-xs border border-[var(--ui-border)] rounded px-2 py-1.5 bg-white focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)]/30 outline-none"
                                    />
                                </div>
                            </div>
                        </x-slot>
                    @endcan
                </x-ui-kanban-column>
            @endforeach

            {{-- Done column (immer sichtbar — expanded oder collapsed) --}}
            @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($done)
                @if($showDoneColumn)
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                        <x-slot name="headerActions">
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-done) 15%, transparent); color: var(--planner-col-done)">
                                {{ $done->tasks->count() }}
                            </span>
                            <button
                                type="button"
                                wire:click="toggleShowDoneColumn"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Einklappen"
                            >
                                @svg('heroicon-o-chevron-double-right', 'w-4 h-4')
                            </button>
                        </x-slot>
                        @forelse($done->tasks as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Noch nichts erledigt</span>
                            </div>
                        @endforelse
                    </x-ui-kanban-column>
                @else
                    {{-- Collapsed: schmaler Streifen, klick öffnet --}}
                    <button
                        type="button"
                        wire:click="toggleShowDoneColumn"
                        class="group/done flex-shrink-0 h-full flex flex-col items-center justify-between py-3 px-2 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40 hover:border-[var(--planner-status-done)]/40 hover:bg-[var(--planner-card-done)] transition-colors cursor-pointer"
                        style="width: 2.5rem; min-width: 2.5rem;"
                        title="Erledigte anzeigen ({{ $done->tasks->count() }})"
                    >
                        @svg('heroicon-o-chevron-double-left', 'w-4 h-4 text-[var(--ui-muted)] group-hover/done:text-[var(--planner-status-done)] transition-colors')

                        <div class="flex flex-col items-center gap-2 flex-1 justify-center min-h-0">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]" style="writing-mode: vertical-rl; transform: rotate(180deg);">
                                {{ $done->label ?? 'Erledigt' }}
                            </span>
                        </div>

                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1 text-[10px] font-semibold rounded-full tabular-nums" style="background-color: color-mix(in srgb, var(--planner-col-done) 15%, transparent); color: var(--planner-col-done)">
                            {{ $done->tasks->count() }}
                        </span>
                    </button>
                @endif
            @endif
        </x-ui-kanban-container>
    @endif

    @if($activeTab === 'dashboard')
        <div class="flex-1 overflow-y-auto">
            @include('planner::livewire.project._dashboard', [
                'dashboardData' => $dashboardData,
                'project' => $project,
            ])
        </div>
    @endif
    </div>

    {{-- Modals --}}
    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>
</x-ui-page>
