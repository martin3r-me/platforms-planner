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

    {{-- Neues Layout via x-ui-page --}}
    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list">
                <x-slot name="titleActions">
                    <x-ui-button variant="secondary-ghost" size="sm" rounded="full" iconOnly="true" x-data @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })" title="Einstellungen">
                        @svg('heroicon-o-cog-6-tooth','w-4 h-4')
                    </x-ui-button>
                </x-slot>
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
                @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                    <x-ui-button variant="secondary-ghost" size="sm" rounded="full" iconOnly="true" x-data @click="$dispatch('open-modal-customer-project', { projectId: {{ $project->id }} })">
                        @svg('heroicon-o-user-group','w-4 h-4')
                    </x-ui-button>
                @endif
            </x-ui-page-navbar>
        </x-slot>

        <x-slot name="sidebar">
            <x-ui-page-sidebar title="Projekt-Übersicht" width="w-80" :defaultOpen="true">
                <div class="p-4 space-y-4">
                    <!-- Projekt-Statistiken -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                        <div class="space-y-2">
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

                    <!-- Projekt-Details -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Details</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between py-1">
                                <span class="text-[var(--ui-muted)]">Typ:</span>
                                <span class="text-[var(--ui-secondary)] font-medium">
                                    {{ $project->project_type?->value ?? $project->project_type ?? 'Unbekannt' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-[var(--ui-muted)]">Erstellt:</span>
                                <span class="text-[var(--ui-secondary)] font-medium">
                                    {{ $project->created_at->format('d.m.Y') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>

        <x-slot name="activity">
            <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
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

        <!-- Board-Container: füllt restliche Breite, Spalten scrollen intern -->
            <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
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
                    <x-slot name="headerActions">
                        <button 
                            wire:click="createTask('{{ $column->id }}')" 
                            class="text-green-600 hover:opacity-80 transition-opacity"
                            title="Neue Aufgabe"
                        >
                            @svg('heroicon-o-plus-circle', 'w-5 h-5')
                        </button>
                        <button 
                            @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $column->id }} })"
                            class="text-gray-500 hover:text-blue-600 transition-colors"
                            title="Einstellungen"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-5 h-5')
                        </button>
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
            </x-ui-kanban-container>
    </x-ui-page>

    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>

</div>