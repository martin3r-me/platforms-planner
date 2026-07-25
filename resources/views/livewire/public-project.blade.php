@php
    $openCount = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->count();
    $doneCount = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->count();
@endphp

<div class="h-screen flex flex-col overflow-hidden bg-[var(--nx-bg)]">
    {{-- Shared Nav --}}
    @include('planner::livewire.partials.public-nav', [
        'project' => $project,
        'canvases' => $canvases,
        'current' => 'board',
        'taskCount' => $openCount,
    ])

    {{-- Sub action bar --}}
    <div class="flex-shrink-0 bg-[color:var(--nx-surface)] border-b border-[var(--nx-line)]">
        <div class="px-4 sm:px-6 py-2 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3 text-xs text-[var(--nx-muted)]">
                <span class="inline-flex items-center gap-1">
                    @svg('heroicon-o-clipboard-document-list', 'w-3.5 h-3.5')
                    <span><strong class="text-[var(--nx-text)]">{{ $openCount }}</strong> offen</span>
                </span>
                <span class="text-[color:var(--nx-faint)]">·</span>
                <span class="inline-flex items-center gap-1">
                    @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                    <span><strong class="text-[var(--nx-text)]">{{ $doneCount }}</strong> erledigt</span>
                </span>
            </div>

            <button
                wire:click="toggleShowDoneColumn"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium rounded-full border transition-colors
                    {{ $showDoneColumn
                        ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] border-[var(--nx-accent)]'
                        : 'bg-[color:var(--nx-surface)] text-[var(--nx-muted)] border-[var(--nx-line)] hover:border-[var(--nx-accent)] hover:text-[var(--nx-text)]' }}"
            >
                @if($showDoneColumn)
                    @svg('heroicon-o-eye-slash', 'w-3.5 h-3.5')
                    <span>Erledigte ausblenden</span>
                @else
                    @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                    <span>Erledigte anzeigen</span>
                @endif
            </button>
        </div>
    </div>

    {{-- Board - fills remaining height --}}
    <div class="flex-1 min-h-0 overflow-x-auto p-4">
        <x-nx-kanban-container>
            {{-- Backlog --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-nx-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                    <x-slot name="headerActions">
                        <span class="text-xs text-[var(--nx-muted)] font-medium">
                            {{ $backlog->tasks->count() }}
                        </span>
                    </x-slot>
                    @foreach($backlog->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true, 'publicToken' => $project->public_token])
                    @endforeach
                </x-nx-kanban-column>
            @endif

            {{-- Mittlere Spalten --}}
            @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false)) as $column)
                <x-nx-kanban-column :title="($column->label ?? 'Spalte')" :sortable-id="null" :scrollable="true">
                    <x-slot name="headerActions">
                        <span class="text-xs text-[var(--nx-muted)] font-medium">
                            {{ $column->tasks->count() }}
                        </span>
                    </x-slot>
                    @foreach($column->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true, 'publicToken' => $project->public_token])
                    @endforeach
                </x-nx-kanban-column>
            @endforeach

            {{-- Erledigt --}}
            @if($showDoneColumn)
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    <x-nx-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                        <x-slot name="headerActions">
                            <span class="text-xs text-[var(--nx-muted)] font-medium">
                                {{ $done->tasks->count() }}
                            </span>
                        </x-slot>
                        @foreach($done->tasks as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true, 'publicToken' => $project->public_token])
                        @endforeach
                    </x-nx-kanban-column>
                @endif
            @endif
        </x-nx-kanban-container>
    </div>
</div>
