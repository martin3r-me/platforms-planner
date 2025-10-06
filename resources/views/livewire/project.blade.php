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

    {{-- Kanban Container (lokal) --}}
    <section class="grid grid-flow-col auto-cols-[18rem] gap-4 overflow-x-auto pb-4">
        @foreach($groups as $group)
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between px-3 py-2 rounded-t-md bg-[var(--ui-muted-5)] border border-b-0 border-[var(--ui-border)]">
                    <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $group->name ?? 'Spalte' }}</div>
                    <button x-data @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $group->id ?? 'null' }} })" title="Spalte bearbeiten"
                            class="text-[var(--ui-primary)] hover:opacity-80">
                        @svg('heroicon-o-cog-6-tooth','w-4 h-4')
                    </button>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto rounded-b-md border border-[var(--ui-border)] bg-white p-2 space-y-2">
                    @forelse($group->tasks as $task)
                        <a href="{{ route('planner.tasks.show', $task) }}" wire:navigate
                           class="block rounded border border-[var(--ui-border)] bg-white p-2 hover:bg-[var(--ui-muted-5)]">
                            <div class="text-sm font-medium truncate">{{ $task->title }}</div>
                            <div class="mt-1 text-xs text-[var(--ui-muted)]">
                                @if($task->due_date)
                                    Fällig: {{ $task->due_date->format('d.m.Y') }}
                                @else
                                    Keine Fälligkeit
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="text-xs text-[var(--ui-muted)]">Keine Aufgaben</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </section>

    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>

</div>