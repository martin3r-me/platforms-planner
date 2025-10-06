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

    {{-- Projekt Kopf & Statistiken (lokal gerendert) --}}
    <section class="mb-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-[var(--ui-secondary)] m-0">{{ $project->name }}</h2>
                <div class="text-sm text-[var(--ui-muted)]">Projekt-Übersicht und Statistiken</div>
            </div>
            <div class="flex items-center gap-2">
                @foreach($actions as $action)
                    @php $onclick = $action['onclick'] ?? null; @endphp
                    <button 
                        @if(isset($action['wire_click'])) wire:click="{{ $action['wire_click'] }}" @endif
                        @if($onclick) x-data @click="{!! $onclick !!}" @endif
                        class="inline-flex items-center justify-center rounded border border-[var(--ui-border)] px-3 py-1 text-sm hover:bg-[var(--ui-muted-5)]">
                        {{ $action['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-3 grid grid-cols-4 gap-4">
            @foreach($stats as $s)
                <div class="rounded-md border border-[var(--ui-border)] bg-white p-3">
                    <div class="text-xs text-[var(--ui-muted)]">{{ $s['title'] }}</div>
                    <div class="text-xl font-semibold">{{ $s['count'] }}</div>
                </div>
            @endforeach
        </div>

        @if($completedTasks->count())
            <div class="mt-2 text-xs text-[var(--ui-muted)]">Erledigte Aufgaben: {{ $completedTasks->count() }}</div>
        @endif
    </section>

    {{-- Kanban Container (mit Komponenten & Drag&Drop) --}}
    <section class="pb-4">
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
    </section>

    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>

</div>