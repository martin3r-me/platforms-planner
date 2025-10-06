<div class="h-full d-flex">
    <!-- Info-Sidebar lokal -->
    <section class="w-80 border-r border-[var(--ui-border)] p-4 flex-shrink-0 hidden md:block">
        <h3 class="text-lg font-semibold m-0">Meine Aufgaben</h3>
        <div class="text-sm text-[var(--ui-muted)] mb-4">Persönliche Aufgaben und zuständige Projektaufgaben</div>

        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <x-ui-dashboard-tile title="Offen" :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count())" icon="clock" variant="yellow" size="sm" />
                <x-ui-dashboard-tile title="Erledigt" :count="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count())" icon="check-circle" variant="green" size="sm" />
            </div>
        </div>
    </section>
        title="Meine Aufgaben"
        subtitle="Persönliche Aufgaben und zuständige Projektaufgaben"
        :stats="[
            [
                'title' => 'Story Points (offen)',
                'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'chart-bar',
                'variant' => 'warning'
            ],
            [
                'title' => 'Story Points (erledigt)',
                'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'check-circle',
                'variant' => 'success'
            ],
            [
                'title' => 'Offen',
                'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count()),
                'icon' => 'clock',
                'variant' => 'warning'
            ],
            [
                'title' => 'Gesamt',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->count(),
                'icon' => 'document-text',
                'variant' => 'secondary'
            ],
            [
                'title' => 'Erledigt',
                'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count()),
                'icon' => 'check-circle',
                'variant' => 'success'
            ],
            [
                'title' => 'Ohne Fälligkeit',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count(),
                'icon' => 'calendar',
                'variant' => 'neutral'
            ],
            [
                'title' => 'Frösche',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->is_frog)->count(),
                'icon' => 'exclamation-triangle',
                'variant' => 'danger'
            ],
            [
                'title' => 'Überfällig',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(),
                'icon' => 'exclamation-circle',
                'variant' => 'danger'
            ]
        ]"
        :actions="[
            [
                'label' => '+ Neue Aufgabe',
                'variant' => 'success-outline',
                'size' => 'sm',
                'wire_click' => 'createTask()'
            ],
            [
                'label' => '+ Neue Spalte',
                'variant' => 'primary-outline',
                'size' => 'sm',
                'wire_click' => 'createTaskGroup'
            ]
        ]"
        :completed-tasks="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)"
    />

    <!-- Kanban mit generischen UI-Komponenten -->
    <section class="flex-1 min-h-0 overflow-x-auto">
        <x-ui-kanban-board wire:sortable="updateTaskGroupOrder" wire:sortable-group="updateTaskOrder">

            {{-- Backlog (nicht sortierbar) --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                    @foreach(($backlog->tasks ?? []) as $task)
                        <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.tasks.show', $task)">
                            <div class="text-xs text-[var(--ui-muted)]">
                                @if($task->due_date)
                                    Fällig: {{ $task->due_date->format('d.m.Y') }}
                                @else
                                    Keine Fälligkeit
                                @endif
                            </div>
                        </x-ui-kanban-card>
                    @endforeach
                </x-ui-kanban-column>
            @endif

            {{-- Mittlere Spalten (sortierbar) --}}
            @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false)) as $column)
                <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                    @foreach(($column->tasks ?? []) as $task)
                        <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.tasks.show', $task)">
                            <div class="text-xs text-[var(--ui-muted)]">
                                @if($task->due_date)
                                    Fällig: {{ $task->due_date->format('d.m.Y') }}
                                @else
                                    Keine Fälligkeit
                                @endif
                            </div>
                        </x-ui-kanban-card>
                    @endforeach
                </x-ui-kanban-column>
            @endforeach

            {{-- Erledigt (nicht sortierbar) --}}
            @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($done)
                <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                    @foreach(($done->tasks ?? []) as $task)
                        <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.tasks.show', $task)">
                            <div class="text-xs text-[var(--ui-muted)]">
                                @if($task->due_date)
                                    Fällig: {{ $task->due_date->format('d.m.Y') }}
                                @else
                                    Keine Fälligkeit
                                @endif
                            </div>
                        </x-ui-kanban-card>
                    @endforeach
                </x-ui-kanban-column>
            @endif

        </x-ui-kanban-board>
    </section>

    <livewire:planner.task-group-settings-modal/>
</div>
