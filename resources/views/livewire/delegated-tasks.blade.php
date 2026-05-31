<x-ui-page>
    @include('planner::partials.planner-tokens')
    <x-slot name="navbar">
        <x-ui-page-navbar title="Delegierte Aufgaben" icon="heroicon-o-user-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Delegierte Aufgaben'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="createTask()">
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
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-6">
                {{-- Projekt-Statistiken: Offen --}}
                @php 
                    $allTasks = $groups->flatMap(fn($g) => $g->tasks);
                    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
                    $doneTasks = $groups->filter(fn($g) => ($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
                    
                    $statsOpen = [
                        [
                            'title' => 'Offen',
                            'count' => $openTasks->count(),
                            'icon' => 'clock',
                            'variant' => 'warning'
                        ],
                        [
                            'title' => 'Story Points',
                            'count' => $openTasks->sum(fn($t) => $t->story_points?->points() ?? 0),
                            'icon' => 'sparkles',
                            'variant' => 'warning'
                        ],
                        [
                            'title' => 'Frösche',
                            'count' => $openTasks->filter(fn($t) => $t->is_frog)->count(),
                            'icon' => 'exclamation-triangle',
                            'variant' => 'danger'
                        ],
                        [
                            'title' => 'Überfällig',
                            'count' => $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(),
                            'icon' => 'exclamation-circle',
                            'variant' => 'danger'
                        ],
                        [
                            'title' => 'Ohne Fälligkeit',
                            'count' => $openTasks->filter(fn($t) => !$t->due_date)->count(),
                            'icon' => 'calendar',
                            'variant' => 'neutral'
                        ],
                    ];
                    
                    $statsDone = [
                        [
                            'title' => 'Erledigt',
                            'count' => $doneTasks->count(),
                            'icon' => 'check-circle',
                            'variant' => 'success'
                        ],
                        [
                            'title' => 'Story Points',
                            'count' => $doneTasks->sum(fn($t) => $t->story_points?->points() ?? 0),
                            'icon' => 'sparkles',
                            'variant' => 'success'
                        ],
                        [
                            'title' => 'Frösche',
                            'count' => $doneTasks->filter(fn($t) => $t->is_frog)->count(),
                            'icon' => 'exclamation-triangle',
                            'variant' => 'success'
                        ],
                        [
                            'title' => 'Verschiebungen',
                            'count' => $allTasks->sum(fn($t) => $t->postpone_count ?? 0),
                            'icon' => 'arrow-path',
                            'variant' => 'secondary'
                        ],
                    ];
                @endphp
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Offen</h3>
                    <div class="space-y-2">
                        @foreach($statsOpen as $stat)
                            <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-' . $stat['icon'], 'w-4 h-4 text-[var(--ui-' . $stat['variant'] . ')]')
                                    <span class="text-sm text-[var(--ui-secondary)]">{{ $stat['title'] }}</span>
                                </div>
                                <span class="text-sm font-semibold text-[var(--ui-' . $stat['variant'] . ')]">
                                    {{ $stat['count'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Projekt-Statistiken: Erledigt --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Erledigt</h3>
                    <button 
                        wire:click="toggleShowDoneColumn"
                        class="w-full flex items-center justify-between py-2.5 px-4 mb-3 bg-[var(--ui-primary-5)] hover:bg-[var(--ui-primary-10)] border border-[var(--ui-primary)]/30 transition-colors group"
                    >
                        <span class="inline-flex items-center gap-2 text-sm font-medium text-[var(--ui-primary)]">
                            @if($showDoneColumn)
                                @svg('heroicon-o-eye-slash', 'w-4 h-4')
                                <span>Erledigte ausblenden</span>
                            @else
                                @svg('heroicon-o-eye', 'w-4 h-4')
                                <span>Erledigte anzeigen</span>
                            @endif
                        </span>
                        @if($doneTasks->count() > 0)
                            <span class="text-xs font-semibold text-[var(--ui-primary)] bg-[var(--ui-primary)]/20 px-2 py-0.5 rounded">
                                {{ $doneTasks->count() }}
                            </span>
                        @endif
                    </button>
                    <div class="space-y-2">
                        @foreach($statsDone as $stat)
                            <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-' . $stat['icon'], 'w-4 h-4 text-[var(--ui-' . $stat['variant'] . ')]')
                                    <span class="text-sm text-[var(--ui-secondary)]">{{ $stat['title'] }}</span>
                                </div>
                                <span class="text-sm font-semibold text-[var(--ui-' . $stat['variant'] . ')]">
                                    {{ $stat['count'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
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

    <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">

            {{-- Backlog (nicht sortierbar) --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-ui-kanban-column :title="($backlog->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true">
                    <x-slot name="headerActions">
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-backlog) 15%, transparent); color: var(--planner-col-backlog)">
                            {{ $backlog->tasks->count() }}
                        </span>
                    </x-slot>
                    @forelse(($backlog->tasks ?? []) as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                            @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Keine delegierten Aufgaben</span>
                        </div>
                    @endforelse
                </x-ui-kanban-column>
            @endif

            {{-- Mittlere Spalten (sortierbar) --}}
            @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false) && !($g->isDueGroup ?? false)) as $column)
                <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                    <x-slot name="headerActions">
                        <button 
                            wire:click="createTask('{{ $column->id ?? 0 }}')" 
                            class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                            title="Neue Aufgabe"
                        >
                            @svg('heroicon-o-plus-circle', 'w-4 h-4')
                        </button>
                        <button 
                            @click="$dispatch('open-modal-delegated-task-group-settings', { taskGroupId: {{ $column->id ?? 0 }} })"
                            class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                            title="Gruppen-Einstellungen"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                        </button>
                    </x-slot>
                    @forelse(($column->tasks ?? []) as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                            @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Keine Aufgaben</span>
                            <span class="text-[10px] mt-0.5 opacity-60">Hierher ziehen oder neu erstellen</span>
                        </div>
                    @endforelse
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
                                    @keydown.enter.prevent="if(title.trim()) { $wire.createTask('{{ $column->id ?? 0 }}', title.trim()); title = ''; open = false; }"
                                    @keydown.escape="open = false; title = ''"
                                    @click.outside="open = false; title = ''"
                                    type="text"
                                    placeholder="Titel eingeben..."
                                    class="w-full text-xs border border-[var(--ui-border)] rounded px-2 py-1.5 bg-white focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)]/30 outline-none"
                                />
                            </div>
                        </div>
                    </x-slot>
                </x-ui-kanban-column>
            @endforeach

            {{-- Erledigt (nur wenn aktiviert) --}}
            @if($showDoneColumn)
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                        <x-slot name="headerActions">
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-done) 15%, transparent); color: var(--planner-col-done)">
                                {{ $done->tasks->count() }}
                            </span>
                        </x-slot>
                        @forelse(($done->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
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

    <livewire:planner.delegated-task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>

