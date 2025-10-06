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

    {{-- Neues Layout: oben Navbar + Aktionen, darunter volles Kanban mit Spalten-Scroll --}}
    <div class="h-full flex flex-col">
        <!-- Top-Navbar: Titel + Aktionen -->
        <div class="sticky top-0 z-10 px-4 py-3 bg-[var(--ui-surface)]/90 border-b border-[var(--ui-border)]/60 shadow-sm backdrop-blur">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    @svg('heroicon-o-clipboard-document-list','w-5 h-5 text-[color:var(--ui-primary)]')
                    <h1 class="m-0 truncate text-[color:var(--ui-secondary)] font-semibold tracking-tight text-base md:text-lg">
                        {{ $project->name }}
                    </h1>
                </div>
                <div class="d-flex items-center gap-2">
                <button wire:click="createProjectSlot" class="inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-semibold select-none whitespace-nowrap 
                    bg-[rgb(var(--ui-primary-rgb))] text-[var(--ui-on-primary)] shadow-sm hover:bg-[rgba(var(--ui-primary-rgb),0.90)] 
                    focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(var(--ui-primary-rgb))]">
                    @svg('heroicon-o-square-2-stack','w-4 h-4')
                    <span class="hidden sm:inline">Spalte</span>
                </button>
                <button wire:click="createTask()" class="inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-semibold select-none whitespace-nowrap 
                    bg-[rgb(var(--ui-success-rgb))] text-[var(--ui-on-success)] shadow-sm hover:bg-[rgba(var(--ui-success-rgb),0.90)] 
                    focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(var(--ui-success-rgb))]">
                    @svg('heroicon-o-plus','w-4 h-4')
                    <span class="hidden sm:inline">Aufgabe</span>
                </button>
                @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                    <button x-data @click="$dispatch('open-modal-customer-project', { projectId: {{ $project->id }} })" class="inline-flex items-center justify-center rounded-full h-8 w-8 sm:w-auto sm:px-2 text-sm 
                        text-[var(--ui-secondary)] hover:bg-[rgba(var(--ui-secondary-rgb),0.08)] border border-transparent 
                        focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(var(--ui-secondary-rgb))]">
                        @svg('heroicon-o-user-group','w-4 h-4')
                    </button>
                @endif
                <button x-data @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })" class="inline-flex items-center justify-center rounded-full h-8 w-8 sm:w-auto sm:px-2 text-sm 
                    text-[var(--ui-info)] hover:bg-[rgba(var(--ui-info-rgb),0.08)] border border-transparent 
                    focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(var(--ui-info-rgb))]">
                    @svg('heroicon-o-cog-6-tooth','w-4 h-4')
                </button>
                </div>
            </div>
        </div>

        <!-- Board-Container: füllt Höhe, Spalten scrollen intern -->
        <div class="flex-1 min-h-0 overflow-x-auto">
            <x-ui-kanban-board wire:sortable="updateTaskGroupOrder" wire:sortable-group="updateTaskOrder" class="h-full">

                {{-- Backlog (nicht sortierbar als Gruppe) --}}
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
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
                    <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
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
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
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