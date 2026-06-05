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
                {{-- View-Tabs --}}
                <div class="inline-flex gap-1 mr-2 pr-2 border-r border-[var(--ui-border)]/40">
                    <x-ui-button
                        wire:click="$set('activeTab', 'board')"
                        variant="{{ $activeTab === 'board' ? 'primary' : 'ghost' }}"
                        size="sm"
                    >
                        @svg('heroicon-o-view-columns', 'w-4 h-4')
                        <span>Board</span>
                    </x-ui-button>
                    <x-ui-button
                        wire:click="$set('activeTab', 'dashboard')"
                        variant="{{ $activeTab === 'dashboard' ? 'primary' : 'ghost' }}"
                        size="sm"
                    >
                        @svg('heroicon-o-chart-bar-square', 'w-4 h-4')
                        <span>Dashboard</span>
                    </x-ui-button>
                </div>

                @can('settings', $project)
                    <x-ui-button variant="ghost" size="sm" x-data @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })">
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                        <span>Einstellungen</span>
                    </x-ui-button>
                @endcan
                @if($linkedEntities->isNotEmpty())
                    @foreach($linkedEntities as $entity)
                        <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">
                            @svg('heroicon-o-rectangle-group', 'w-3 h-3')
                            {{ $entity['entity_name'] }}
                            @if($entity['entity_type'])
                                <span class="text-[var(--ui-muted)]">({{ $entity['entity_type'] }})</span>
                            @endif
                        </span>
                    @endforeach
                @endif
                <x-ui-button variant="secondary" size="sm" wire:click="openCanvas" title="Project Canvas öffnen">
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4')
                    <span>Canvas</span>
                </x-ui-button>
            </x-slot>

            @if($activeTab === 'board')
                @can('update', $project)
                    <x-ui-button variant="primary" size="sm" wire:click="createTask()" title="Neue Aufgabe (N)">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Aufgabe</span>
                    </x-ui-button>
                    <x-ui-button variant="ghost" size="sm" wire:click="createProjectSlot">
                        @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                        <span>Spalte</span>
                    </x-ui-button>
                @endcan
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Projekt-Übersicht" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-5">
                @if($activeTab === 'board')
                    {{-- Done toggle (wird in Step 6 durch collapsed column ersetzt) --}}
                    <button
                        wire:click="toggleShowDoneColumn"
                        class="w-full flex items-center justify-between py-2 px-3 bg-[var(--ui-primary-5)] hover:bg-[var(--ui-primary-10)] border border-[var(--ui-primary)]/30 rounded transition-colors"
                    >
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--ui-primary)]">
                            @if($showDoneColumn)
                                @svg('heroicon-o-eye-slash', 'w-3.5 h-3.5')
                                <span>Erledigte ausblenden</span>
                            @else
                                @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                                <span>Erledigte anzeigen</span>
                            @endif
                        </span>
                        @if($doneTasks->count() > 0)
                            <span class="text-[10px] font-semibold text-[var(--ui-primary)] bg-[var(--ui-primary)]/20 px-1.5 py-0.5 rounded">
                                {{ $doneTasks->count() }}
                            </span>
                        @endif
                    </button>
                @endif

                {{-- Projekt-Details --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Details</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between py-1.5 px-2 rounded bg-[var(--ui-muted-5)]">
                            <span class="text-[var(--ui-muted)]">Typ</span>
                            <span class="text-[var(--ui-secondary)] font-medium">{{ $project->project_type?->value ?? '–' }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 px-2 rounded bg-[var(--ui-muted-5)]">
                            <span class="text-[var(--ui-muted)]">Erstellt</span>
                            <span class="text-[var(--ui-secondary)] font-medium">{{ $project->created_at->format('d.m.Y') }}</span>
                        </div>
                        @if($project->totalPlannedMinutes() > 0)
                            <div class="flex justify-between py-1.5 px-2 rounded bg-[var(--ui-muted-5)]">
                                <span class="text-[var(--ui-muted)]">Geplant</span>
                                <span class="text-[var(--ui-secondary)] font-medium">{{ number_format($project->totalPlannedMinutes() / 60, 1, ',', '.') }}h</span>
                            </div>
                        @endif
                        @if($project->billing_method)
                            <div class="flex justify-between py-1.5 px-2 rounded bg-[var(--ui-muted-5)]">
                                <span class="text-[var(--ui-muted)]">Abrechnung</span>
                                <span class="text-[var(--ui-secondary)] font-medium">{{ $project->billing_method?->value ?? $project->billing_method }}</span>
                            </div>
                        @endif
                        @if($project->budget_amount)
                            <div class="flex justify-between py-1.5 px-2 rounded bg-[var(--ui-muted-5)]">
                                <span class="text-[var(--ui-muted)]">Budget</span>
                                <span class="text-[var(--ui-secondary)] font-medium">{{ number_format($project->budget_amount, 2, ',', '.') }} {{ $project->currency ?? 'EUR' }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Member status --}}
                @if($currentUserRole ?? null || $hasAnyTasks ?? false)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Status</h3>
                        @if($currentUserRole ?? null)
                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">
                                {{ ucfirst($currentUserRole) }}
                            </span>
                        @elseif($hasAnyTasks ?? false)
                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-[var(--ui-warning-5)] text-[var(--ui-warning)]">
                                Mit Aufgaben
                            </span>
                        @endif
                    </div>
                @endif
            </div>
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
    ])

    @if($activeTab === 'board')
        {{-- Filter Bar (above board) --}}
        @if($availableFilterTags->isNotEmpty() || $availableFilterColors->isNotEmpty() || $hasActiveFilters)
            <div class="flex items-center gap-2 px-4 py-2 border-b border-[var(--ui-border)]/60 bg-white/80 flex-wrap">
                <span class="text-[var(--ui-muted)] flex-shrink-0">
                    @svg('heroicon-o-funnel', 'w-4 h-4')
                </span>

                {{-- Active filter tokens --}}
                @foreach($availableFilterTags->filter(fn($t) => in_array($t['id'], $filterTagIds)) as $tag)
                    <button
                        wire:click="toggleTagFilter({{ $tag['id'] }})"
                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full border transition-colors bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]"
                    >
                        @if($tag['color'])
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></span>
                        @endif
                        {{ $tag['label'] }}
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endforeach

                @if($filterColor)
                    <button
                        wire:click="toggleColorFilter('{{ $filterColor }}')"
                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full border border-[var(--ui-primary)] text-[var(--ui-secondary)]"
                    >
                        <span class="w-3 h-3 rounded-full flex-shrink-0 border border-white/50" style="background-color: {{ $filterColor }}"></span>
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif

                {{-- Add filter dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full border border-dashed border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition-colors">
                        @svg('heroicon-o-plus', 'w-3 h-3')
                        Filter
                    </button>
                    <div
                        x-show="open"
                        x-cloak
                        @click.outside="open = false"
                        class="absolute top-full left-0 mt-1 w-56 bg-white border border-[var(--ui-border)] rounded-lg shadow-lg z-20 p-3 space-y-3"
                    >
                        @if($availableFilterTags->isNotEmpty())
                            <div>
                                <div class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)] mb-1.5">Tags</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($availableFilterTags as $tag)
                                        <button
                                            wire:click="toggleTagFilter({{ $tag['id'] }})"
                                            @click="open = false"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full border transition-colors
                                                {{ in_array($tag['id'], $filterTagIds)
                                                    ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                                    : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/60' }}"
                                        >
                                            @if($tag['color'])
                                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></span>
                                            @endif
                                            {{ $tag['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if($availableFilterColors->isNotEmpty())
                            <div>
                                <div class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)] mb-1.5">Farben</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($availableFilterColors as $color)
                                        <button
                                            wire:click="toggleColorFilter('{{ $color }}')"
                                            @click="open = false"
                                            class="w-6 h-6 rounded-full border-2 transition-all
                                                {{ $filterColor === $color
                                                    ? 'border-[var(--ui-primary)] ring-2 ring-[var(--ui-primary)]/30 scale-110'
                                                    : 'border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/60' }}"
                                            style="background-color: {{ $color }}"
                                        ></button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Clear all --}}
                @if($hasActiveFilters)
                    <button
                        wire:click="clearFilters"
                        class="ml-auto inline-flex items-center gap-1 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                    >
                        Alle entfernen
                    </button>
                @endif
            </div>
        @endif

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

            {{-- Done column --}}
            @if($showDoneColumn)
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                        <x-slot name="headerActions">
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-done) 15%, transparent); color: var(--planner-col-done)">
                                {{ $done->tasks->count() }}
                            </span>
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
