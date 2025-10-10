<div class="h-full">
    {{-- DEBUG: Test ob embedded Komponente geladen wird --}}
    <div class="bg-red-100 p-4 mb-4">
        <p class="text-red-800">DEBUG: Embedded Project Komponente geladen</p>
        <p class="text-red-800">Projekt: {{ $project->name }}</p>
        <p class="text-red-800">Zeit: {{ now() }}</p>
        <p class="text-red-800">User Agent: <span id="user-agent"></span></p>
        <p class="text-red-800">Teams Context: <span id="teams-context"></span></p>
    </div>
    
    <script>
        document.getElementById('user-agent').textContent = navigator.userAgent;
        document.getElementById('teams-context').textContent = typeof microsoftTeams !== 'undefined' ? 'Teams detected' : 'No Teams';
    </script>
    
    <x-ui-page>
            <x-slot name="navbar">
                <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list">
                    <x-ui-button variant="success" size="sm" rounded="full" wire:click="createTask()">
                        <span class="inline-flex items-center gap-2">
                            @svg('heroicon-o-plus','w-4 h-4 inline-block align-middle')
                            <span class="hidden sm:inline">Aufgabe</span>
                        </span>
                    </x-ui-button>
                </x-ui-page-navbar>
            </x-slot>

            <x-ui-page-container spacing="space-y-2 md:space-y-4" class="px-2 md:px-4">
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
                                    class="text-green-600 hover:opacity-80 transition-opacity"
                                    title="Neue Aufgabe"
                                >
                                    @svg('heroicon-o-plus-circle', 'w-5 h-5')
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
    </x-ui-page>
</div>


