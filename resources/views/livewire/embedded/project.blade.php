<div class="h-screen flex flex-col">
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
            ]
        ];
    @endphp

    <x-ui-page class="h-full">
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list">
                <x-ui-button variant="secondary" size="sm" wire:click="createProjectSlot">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-square-2-stack','w-4 h-4')
                        <span class="hidden sm:inline">Spalte</span>
                    </span>
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" wire:click="createTask()">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-plus','w-4 h-4')
                        <span class="hidden sm:inline">Aufgabe</span>
                    </span>
                </x-ui-button>
            </x-ui-page-navbar>
        </x-slot>

        <x-ui-page-container class="p-2">
            {{-- Stats --}}
            <x-ui-detail-stats-grid :stats="$stats" class="mb-6" />

            {{-- Kanban-Container: automatischer Board/List-Wechsel --}}
            <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
                {{-- Backlog (nicht sortierbar als Gruppe) --}}
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                        @foreach($backlog->tasks as $task)
                            <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.embedded.task', $task)" wire:key="task-{{ $task->id }}">
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
                    <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" wire:key="column-{{ $column->id }}">
                        <x-slot name="headerActions">
                            <button 
                                wire:click="createTask('{{ $column->id }}')" 
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                        </x-slot>

                        @foreach($column->tasks as $task)
                            <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.embedded.task', $task)" wire:key="task-{{ $task->id }}">
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
                            <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.embedded.task', $task)" wire:key="task-{{ $task->id }}">
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
            </x-ui-kanban-container>
        </x-ui-page-container>

        {{-- Sidebar --}}
        <x-slot name="sidebar">
            <x-ui-page-sidebar title="Projekt-Übersicht" width="w-80" :defaultOpen="true">
                <div class="p-6 space-y-6">
                    {{-- Quick Actions --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                        <div class="space-y-2">
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="createTask()" class="w-full">
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Neue Aufgabe
                                </span>
                            </x-ui-button>
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="createProjectSlot" class="w-full">
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                                    Neue Spalte
                                </span>
                            </x-ui-button>
                        </div>
                    </div>

                    {{-- Projekt-Info --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Projekt</h3>
                        <div class="text-sm text-[var(--ui-muted)]">
                            <p><strong>Name:</strong> {{ $project->name }}</p>
                            <p><strong>Typ:</strong> {{ $project->project_type?->value ?? 'Intern' }}</p>
                            <p><strong>Erstellt:</strong> {{ $project->created_at->format('d.m.Y') }}</p>
                        </div>
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>
    </x-ui-page>
</div>


