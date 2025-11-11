<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Aufgaben" icon="heroicon-o-clipboard-document-check" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
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
                            $stats = [
                                ['title' => 'Story Points (offen)', 'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0), 'icon' => 'chart-bar', 'variant' => 'warning'],
                                ['title' => 'Story Points (erledigt)', 'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0), 'icon' => 'check-circle', 'variant' => 'success'],
                                ['title' => 'Offen', 'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count()), 'icon' => 'clock', 'variant' => 'warning'],
                                ['title' => 'Gesamt', 'count' => $groups->flatMap(fn($g) => $g->tasks)->count(), 'icon' => 'document-text', 'variant' => 'secondary'],
                                ['title' => 'Erledigt', 'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count()), 'icon' => 'check-circle', 'variant' => 'success'],
                                ['title' => 'Ohne Fälligkeit', 'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count(), 'icon' => 'calendar', 'variant' => 'neutral'],
                                ['title' => 'Frösche', 'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->is_frog)->count(), 'icon' => 'exclamation-triangle', 'variant' => 'danger'],
                                ['title' => 'Überfällig', 'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(), 'icon' => 'exclamation-circle', 'variant' => 'danger'],
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

    {{-- Fällige Aufgaben (außerhalb des Kanban-Boards) --}}
    @php $dueGroup = $groups->first(fn($g) => ($g->isDueGroup ?? false)); @endphp
    @if($dueGroup && $dueGroup->tasks->isNotEmpty())
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-3">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Fällig</h3>
                <span class="text-xs text-[var(--ui-muted)]">({{ $dueGroup->tasks->count() }})</span>
            </div>
            <div class="flex flex-wrap gap-3">
                @foreach(($dueGroup->tasks ?? []) as $task)
                    <div wire:key="due-{{ $task->id }}" class="flex-shrink-0 w-80">
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'wireKey' => null])
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Kanban Board --}}
    <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">

            {{-- Backlog (nicht sortierbar) --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-ui-kanban-column :title="($backlog->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true">
                    @foreach(($backlog->tasks ?? []) as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'wireKey' => 'inbox-' . $task->id])
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
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'wireKey' => 'group-' . $column->id . '-' . $task->id])
                    @endforeach
                </x-ui-kanban-column>
            @endforeach

    </x-ui-kanban-container>

    {{-- Erledigte Aufgaben (außerhalb des Kanban-Boards) --}}
    @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
    @if($done && $done->tasks->isNotEmpty())
        <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/60">
            <div class="flex items-center gap-2 mb-3">
                <h3 class="text-sm font-semibold text-[var(--ui-muted)]">Erledigt</h3>
                <span class="text-xs text-[var(--ui-muted)]">({{ $done->tasks->count() }})</span>
            </div>
            <div class="flex flex-wrap gap-3">
                @foreach(($done->tasks ?? []) as $task)
                    <div wire:key="done-{{ $task->id }}" class="flex-shrink-0 w-80 opacity-60">
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'wireKey' => null])
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <livewire:planner.task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
