@php
    $allTasks = $groups->flatMap(fn($g) => $g->tasks);
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasks = $groups->filter(fn($g) => ($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $hasActiveFilters = !empty($filterTagIds) || $filterColor;
@endphp

<x-ui-page
    x-data="{}"
    @keydown.n.window.prevent="$wire.createTask()"
>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Aufgaben" icon="heroicon-o-clipboard-document-check" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Projekte', 'href' => route('planner.dashboard'), 'icon' => 'clipboard-document-list'],
            ['label' => 'Meine Aufgaben'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="createTask()" title="Neue Aufgabe (N)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Aufgabe</span>
            </x-ui-button>
            <x-ui-button variant="ghost" size="sm" wire:click="createTaskGroup">
                @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                <span>Spalte</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-5">

                {{-- Compact stats --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40">
                        <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $openTasks->count() }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Offen</div>
                    </div>
                    <div class="px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40">
                        <div class="text-lg font-bold text-[var(--ui-success)]">{{ $doneTasks->count() }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Erledigt</div>
                    </div>
                    <div class="px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40">
                        <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $openTasks->sum(fn($t) => $t->story_points?->points() ?? 0) }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">SP offen</div>
                    </div>
                    <div class="px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40">
                        <div class="text-lg font-bold text-[var(--ui-danger)]">{{ $openTasks->filter(fn($t) => $t->is_frog)->count() }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Frösche</div>
                    </div>
                </div>

                {{-- Done toggle --}}
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
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" class="border-l border-r-0">
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

    {{-- Filter Bar --}}
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

        {{-- Backlog / Inbox --}}
        @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
        @if($backlog)
            <x-ui-kanban-column :title="($backlog->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true">
                <x-slot name="headerActions">
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full bg-[var(--ui-muted-10)] text-[var(--ui-muted)]">
                        {{ $backlog->tasks->count() }}
                    </span>
                </x-slot>
                @forelse(($backlog->tasks ?? []) as $task)
                    @include('planner::livewire.task-preview-card', ['task' => $task, 'usePanel' => true])
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                        @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-xs">Keine Aufgaben</span>
                    </div>
                @endforelse
            </x-ui-kanban-column>
        @endif

        {{-- Middle columns --}}
        @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false) && !($g->isDueGroup ?? false)) as $column)
            <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                <x-slot name="headerActions">
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full bg-[var(--ui-muted-10)] text-[var(--ui-muted)]">
                        {{ $column->tasks->count() }}
                    </span>
                    <button
                        wire:click="createTask('{{ $column->id ?? 0 }}')"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                        title="Neue Aufgabe"
                    >
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    </button>
                    <button
                        @click="$dispatch('open-modal-task-group-settings', { taskGroupId: {{ $column->id ?? 0 }} })"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                        title="Gruppen-Einstellungen"
                    >
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    </button>
                </x-slot>
                @forelse(($column->tasks ?? []) as $task)
                    @include('planner::livewire.task-preview-card', ['task' => $task, 'usePanel' => true])
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                        @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-xs">Keine Aufgaben</span>
                    </div>
                @endforelse
            </x-ui-kanban-column>
        @endforeach

        {{-- Done column --}}
        @if($showDoneColumn)
            @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($done)
                <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                    <x-slot name="headerActions">
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full bg-[var(--ui-success)]/10 text-[var(--ui-success)]">
                            {{ $done->tasks->count() }}
                        </span>
                    </x-slot>
                    @forelse(($done->tasks ?? []) as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'usePanel' => true])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                            @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Keine erledigten Aufgaben</span>
                        </div>
                    @endforelse
                </x-ui-kanban-column>
            @endif
        @endif

    </x-ui-kanban-container>

    <livewire:planner.task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>

    {{-- Slide-in Task Panel --}}
    <livewire:planner.task-panel/>
</x-ui-page>
