<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Aufgaben" icon="heroicon-o-clipboard-document-check" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                {{-- Meine Frösche (Verantwortung) --}}
                @php 
                    $myFrogs = $groups->flatMap(fn($g) => $g->tasks)
                        ->filter(fn($t) => $t->is_frog && !$t->is_done && ($t->user_in_charge_id ?? null) === auth()->id());
                @endphp
                @if($myFrogs->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-warning)] mb-3">Meine Frösche</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            @foreach($myFrogs as $task)
                                <a 
                                    href="{{ route('planner.tasks.show', $task) }}" 
                                    wire:navigate
                                    class="block p-3 rounded-lg bg-[var(--ui-warning-5)] border border-[var(--ui-warning)]/40 hover:bg-[var(--ui-warning-10)] hover:border-[var(--ui-warning)]/60 transition-colors"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate mb-1">
                                                {{ $task->title }}
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)]">
                                                @if($task->due_date)
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-calendar', 'w-3 h-3')
                                                        {{ $task->due_date->format('d.m.Y') }}
                                                    </span>
                                                    @if($task->due_date->isPast())
                                                        <span class="text-[var(--ui-danger)] font-semibold">Überfällig</span>
                                                    @elseif($task->due_date->isToday())
                                                        <span class="text-[var(--ui-warning)] font-semibold">Heute</span>
                                                    @elseif($task->due_date->isTomorrow())
                                                        <span class="text-[var(--ui-warning)] font-semibold">Morgen</span>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-calendar', 'w-3 h-3')
                                                        Keine Fälligkeit
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-md bg-[var(--ui-warning)] text-[var(--ui-on-warning)]">
                                            @svg('heroicon-o-exclamation-triangle','w-3 h-3')
                                            Frosch
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                {{-- Fällige Aufgaben --}}
                @php $dueGroup = $groups->first(fn($g) => ($g->isDueGroup ?? false)); @endphp
                @if($dueGroup && $dueGroup->tasks->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Fällig</h3>
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            @foreach($dueGroup->tasks as $task)
                                <a 
                                    href="{{ route('planner.tasks.show', $task) }}" 
                                    wire:navigate
                                    class="block p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted)] hover:border-[var(--ui-primary)]/40 transition-colors"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-[var(--ui-secondary)] truncate mb-1">
                                                {{ $task->title }}
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)]">
                                                <span class="inline-flex items-center gap-1">
                                                    @svg('heroicon-o-calendar', 'w-3 h-3')
                                                    {{ $task->due_date->format('d.m.Y') }}
                                                </span>
                                                @if($task->due_date->isPast())
                                                    <span class="text-[var(--ui-danger)] font-semibold">Überfällig</span>
                                                @elseif($task->due_date->isToday())
                                                    <span class="text-[var(--ui-warning)] font-semibold">Heute</span>
                                                @elseif($task->due_date->isTomorrow())
                                                    <span class="text-[var(--ui-warning)] font-semibold">Morgen</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Quick Actions verschoben aus Navbar --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Aktionen</h3>
                    <div class="flex items-center gap-2">
                        <x-ui-button variant="secondary" size="sm" wire:click="createTaskGroup">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-square-2-stack','w-4 h-4 inline-block align-middle')
                                <span class="hidden sm:inline">Spalte</span>
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="createTask()">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus','w-4 h-4 inline-block align-middle')
                                <span class="hidden sm:inline">Aufgabe</span>
                            </span>
                        </x-ui-button>
                    </div>
                </div>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        @php 
                            $allTasks = $groups->flatMap(fn($g) => $g->tasks);
                            $stats = [
                                ['title' => 'Story Points (offen)', 'count' => $allTasks->filter(fn($t) => !$t->is_done)->sum(fn($t) => $t->story_points?->points() ?? 0), 'icon' => 'chart-bar', 'variant' => 'warning'],
                                ['title' => 'Story Points (erledigt)', 'count' => $allTasks->filter(fn($t) => $t->is_done)->sum(fn($t) => $t->story_points?->points() ?? 0), 'icon' => 'check-circle', 'variant' => 'success'],
                                ['title' => 'Offen', 'count' => $allTasks->filter(fn($t) => !$t->is_done)->count(), 'icon' => 'clock', 'variant' => 'warning'],
                                ['title' => 'Gesamt', 'count' => $allTasks->count(), 'icon' => 'document-text', 'variant' => 'secondary'],
                                ['title' => 'Erledigt', 'count' => $allTasks->filter(fn($t) => $t->is_done)->count(), 'icon' => 'check-circle', 'variant' => 'success'],
                                ['title' => 'Ohne Fälligkeit', 'count' => $allTasks->filter(fn($t) => !$t->due_date)->count(), 'icon' => 'calendar', 'variant' => 'neutral'],
                                ['title' => 'Frösche offen', 'count' => $allTasks->filter(fn($t) => $t->is_frog && !$t->is_done)->count(), 'icon' => 'exclamation-triangle', 'variant' => 'danger'],
                                ['title' => 'Frösche erledigt', 'count' => $allTasks->filter(fn($t) => $t->is_frog && $t->is_done)->count(), 'icon' => 'exclamation-triangle', 'variant' => 'success'],
                                ['title' => 'Überfällig', 'count' => $allTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(), 'icon' => 'exclamation-circle', 'variant' => 'danger'],
                            ];
                        @endphp
                        @foreach($stats as $stat)
                            <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
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
                    @foreach(($backlog->tasks ?? []) as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task])
                    @endforeach
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
                            @click="$dispatch('open-modal-task-group-settings', { taskGroupId: {{ $column->id ?? 0 }} })"
                            class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                            title="Gruppen-Einstellungen"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                        </button>
                    </x-slot>
                    @foreach(($column->tasks ?? []) as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task])
                    @endforeach
                </x-ui-kanban-column>
            @endforeach

            {{-- Erledigt (nicht sortierbar) --}}
            @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($done)
                <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                    @foreach(($done->tasks ?? []) as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task])
                    @endforeach
                </x-ui-kanban-column>
            @endif

    </x-ui-kanban-container>

    <livewire:planner.task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
