<div class="h-full d-flex">
    @php 
        $completedTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
        $stats = [
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
        ];
        $actions = [
            [
                'label' => '+ Neue Aufgabe',
                'variant' => 'success',
                'size' => 'sm',
                'wire_click' => 'createTask()'
            ],
            [
                'label' => '+ Neue Spalte',
                'variant' => 'primary',
                'size' => 'sm',
                'wire_click' => 'createProjectSlot'
            ]
        ];
        
        // Projekt-spezifische Buttons hinzufügen
        if(($project->project_type?->value ?? $project->project_type) === 'customer') {
            $actions[] = [
                'label' => 'Kunden',
                'variant' => 'primary',
                'size' => 'sm',
                'wire_click' => null,
                'onclick' => '$dispatch(\'open-modal-customer-project\', { projectId: ' . $project->id . ' })'
            ];
        }
        
        $actions[] = [
            'label' => 'Projekt-Einstellungen',
            'variant' => 'info',
            'size' => 'sm',
            'wire_click' => null,
            'onclick' => '$dispatch(\'open-modal-project-settings\', { projectId: ' . $project->id . ' })'
        ];
    @endphp

    {{-- Layout wie Sales-Board: linke Info-Spalte + rechtes Kanban --}}
    <div class="h-full d-flex">
        <!-- Linke Info-Spalte -->
        <div class="w-80 border-r border-[var(--ui-border)] p-4 flex-shrink-0">
            <h3 class="text-lg font-semibold m-0">{{ $project->name }}</h3>
            <div class="text-sm text-[var(--ui-muted)] mb-4">Projekt-Übersicht</div>

            <!-- Dashboard Tiles -->
            <div class="space-y-3 mb-4">
                <h4 class="font-medium text-[var(--ui-secondary)] m-0">Board Statistiken</h4>

                <div class="grid grid-cols-2 gap-2">
                    <x-ui-dashboard-tile
                        title="Offen"
                        :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count())"
                        icon="clock"
                        variant="yellow"
                        size="sm"
                    />

                    <x-ui-dashboard-tile
                        title="Erledigt"
                        :count="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count())"
                        icon="check-circle"
                        variant="green"
                        size="sm"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="p-3 bg-[color:var(--ui-primary-50)] border border-[color:var(--ui-primary-200)] rounded">
                        <div class="text-sm text-[color:var(--ui-primary-600)]">Story Points offen</div>
                        <div class="text-xl font-bold text-[color:var(--ui-primary-800)]">
                            {{ $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0) }}
                        </div>
                    </div>

                    <div class="p-3 bg-[color:var(--ui-secondary-50)] border border-[color:var(--ui-secondary-200)] rounded">
                        <div class="text-sm text-[color:var(--ui-secondary-600)]">Gesamt Aufgaben</div>
                        <div class="text-xl font-bold text-[color:var(--ui-secondary-800)]">
                            {{ $groups->flatMap(fn($g) => $g->tasks)->count() }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <x-ui-dashboard-tile
                        title="Überfällig"
                        :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count()"
                        icon="exclamation-circle"
                        variant="red"
                        size="sm"
                    />

                    <x-ui-dashboard-tile
                        title="Ohne Fälligkeit"
                        :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count()"
                        icon="calendar"
                        variant="neutral"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Aktionen -->
            <div class="d-flex flex-col gap-2 mb-4">
                <x-ui-button variant="success" size="sm" wire:click="createTask()">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neue Aufgabe
                    </div>
                </x-ui-button>

                <x-ui-button variant="primary" size="sm" wire:click="createProjectSlot">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                        Neue Spalte
                    </div>
                </x-ui-button>

                @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                    <x-ui-button variant="secondary" size="sm" @click="$dispatch('open-modal-customer-project', { projectId: {{ $project->id }} })">
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-user-group', 'w-4 h-4')
                            Kunden
                        </div>
                    </x-ui-button>
                @endif

                <x-ui-button variant="info" size="sm" @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                        Projekt-Einstellungen
                    </div>
                </x-ui-button>
            </div>
        </div>

        <!-- Rechtes Kanban (scrollbar) -->
        <div class="flex-grow overflow-x-auto">
            <x-ui-kanban-board wire:sortable="updateTaskGroupOrder" wire:sortable-group="updateTaskOrder">

                {{-- Backlog (nicht sortierbar als Gruppe) --}}
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null">
                        @foreach($backlog->tasks as $task)
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
                    <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id">
                        <x-slot name="extra">
                            <div class="d-flex gap-1">
                                <x-ui-button variant="success-outline" size="sm" class="w-full" wire:click="createTask('{{ $column->id }}')">
                                    + Neue Aufgabe
                                </x-ui-button>
                                <x-ui-button variant="primary-outline" size="sm" class="w-full" @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $column->id }} })">Settings</x-ui-button>
                            </div>
                        </x-slot>

                        @foreach($column->tasks as $task)
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

                {{-- Erledigt (nicht sortierbar als Gruppe) --}}
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null">
                        @foreach($done->tasks as $task)
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
        </div>
    </div>

    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>

</div>