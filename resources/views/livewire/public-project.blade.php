@php
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasksCount = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->count();
    $totalTasks = $openTasks->count() + $doneTasksCount;
@endphp

<div class="min-h-screen bg-[var(--ui-bg,#f8fafc)]">
    {{-- Header --}}
    <header class="border-b border-[var(--ui-border,#e2e8f0)] bg-white">
        <div class="max-w-full mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-[var(--ui-secondary,#1e293b)]">
                        {{ $project->name }}
                    </h1>
                    @if($project->description)
                        <p class="mt-1 text-sm text-[var(--ui-muted,#64748b)]">
                            {{ Str::limit($project->description, 200) }}
                        </p>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <span class="inline-flex items-center gap-1 text-sm text-[var(--ui-muted,#64748b)]">
                        @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                        {{ $openTasks->count() }} offen
                    </span>
                    <span class="inline-flex items-center gap-1 text-sm text-[var(--ui-muted,#64748b)]">
                        @svg('heroicon-o-check-circle', 'w-4 h-4')
                        {{ $doneTasksCount }} erledigt
                    </span>
                    <button
                        wire:click="toggleShowDoneColumn"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded border transition-colors
                            {{ $showDoneColumn
                                ? 'bg-[var(--ui-primary,#3b82f6)] text-white border-[var(--ui-primary,#3b82f6)]'
                                : 'bg-white text-[var(--ui-muted,#64748b)] border-[var(--ui-border,#e2e8f0)] hover:border-[var(--ui-primary,#3b82f6)]' }}"
                    >
                        @if($showDoneColumn)
                            @svg('heroicon-o-eye-slash', 'w-4 h-4')
                            <span>Erledigte ausblenden</span>
                        @else
                            @svg('heroicon-o-eye', 'w-4 h-4')
                            <span>Erledigte anzeigen</span>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </header>

    {{-- Board --}}
    <div class="p-4 overflow-x-auto">
        <x-ui-kanban-container>
            {{-- Backlog --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                    <x-slot name="headerActions">
                        <span class="text-xs text-[var(--ui-muted)] font-medium">
                            {{ $backlog->tasks->count() }}
                        </span>
                    </x-slot>
                    @foreach($backlog->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true])
                    @endforeach
                </x-ui-kanban-column>
            @endif

            {{-- Mittlere Spalten --}}
            @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false)) as $column)
                <x-ui-kanban-column :title="($column->label ?? 'Spalte')" :sortable-id="null" :scrollable="true">
                    <x-slot name="headerActions">
                        <span class="text-xs text-[var(--ui-muted)] font-medium">
                            {{ $column->tasks->count() }}
                        </span>
                    </x-slot>
                    @foreach($column->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true])
                    @endforeach
                </x-ui-kanban-column>
            @endforeach

            {{-- Erledigt --}}
            @if($showDoneColumn)
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                        <x-slot name="headerActions">
                            <span class="text-xs text-[var(--ui-muted)] font-medium">
                                {{ $done->tasks->count() }}
                            </span>
                        </x-slot>
                        @foreach($done->tasks as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true])
                        @endforeach
                    </x-ui-kanban-column>
                @endif
            @endif
        </x-ui-kanban-container>
    </div>
</div>
