<div class="h-full">
    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list">
                <x-ui-button variant="secondary" size="sm" wire:click="createTask()">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-plus','w-4 h-4')
                        <span class="hidden sm:inline">Aufgabe</span>
                    </span>
                </x-ui-button>
            </x-ui-page-navbar>
        </x-slot>

        <x-ui-page-container class="p-2">
            <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
                {{-- Backlog --}}
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                        @foreach($backlog->tasks as $task)
                            <livewire:planner.task-preview-card :task="$task" :key="'task-'.$task->id" />
                        @endforeach
                    </x-ui-kanban-column>
                @endif

                {{-- Mittlere Spalten --}}
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
                            <livewire:planner.task-preview-card :task="$task" :key="'task-'.$task->id" />
                        @endforeach
                    </x-ui-kanban-column>
                @endforeach

                {{-- Erledigt --}}
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                        @foreach($done->tasks as $task)
                            <livewire:planner.task-preview-card :task="$task" :key="'task-'.$task->id" />
                        @endforeach
                    </x-ui-kanban-column>
                @endif
            </x-ui-kanban-container>
        </x-ui-page-container>
    </x-ui-page>
</div>


