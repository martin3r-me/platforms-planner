@php
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasksCount = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->count();
    $totalTasks = $openTasks->count() + $doneTasksCount;
@endphp

<div class="h-screen flex flex-col overflow-hidden bg-[var(--ui-bg,#f8fafc)]">
    {{-- Header --}}
    <header class="flex-shrink-0 border-b border-[var(--ui-border,#e2e8f0)] bg-white">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-[var(--ui-secondary,#1e293b)] truncate">
                        {{ $project->name }}
                    </h1>
                    @if($project->description)
                        <p class="mt-1 text-sm text-[var(--ui-muted,#64748b)] truncate">
                            {{ Str::limit($project->description, 200) }}
                        </p>
                    @endif
                </div>
                <div class="flex items-center gap-4 flex-shrink-0">
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

    {{-- Canvases (Project Canvases shared publicly) --}}
    @if($canvases->isNotEmpty())
        <section class="flex-shrink-0 border-b border-[var(--ui-border,#e2e8f0)] bg-white">
            <div class="px-6 py-3">
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#f2ca52] text-[#1a1a2e]">
                        Project Canvas
                    </span>
                    <span class="text-xs text-[var(--ui-muted,#64748b)]">{{ $canvases->count() }} {{ $canvases->count() === 1 ? 'Canvas' : 'Canvases' }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($canvases as $canvas)
                        <a
                            href="{{ route('planner.public.canvas', ['token' => $project->public_token, 'canvas' => $canvas->id]) }}"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-[var(--ui-border,#e2e8f0)] bg-white text-xs text-[var(--ui-secondary,#1e293b)] hover:border-[#f2ca52] hover:bg-yellow-50 transition-colors"
                        >
                            @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5 text-[#f2ca52]')
                            <span class="font-medium">{{ $canvas->name }}</span>
                            @php
                                $statusClass = match($canvas->status) {
                                    'open' => 'bg-blue-100 text-blue-700',
                                    'completed' => 'bg-green-100 text-green-700',
                                    'discarded' => 'bg-gray-100 text-gray-500',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-medium {{ $statusClass }}">
                                {{ \Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$canvas->status] ?? $canvas->status }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Board - fills remaining height --}}
    <div class="flex-1 min-h-0 overflow-x-auto p-4">
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
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true, 'publicToken' => $project->public_token])
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
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true, 'publicToken' => $project->public_token])
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
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'publicMode' => true, 'publicToken' => $project->public_token])
                        @endforeach
                    </x-ui-kanban-column>
                @endif
            @endif
        </x-ui-kanban-container>
    </div>
</div>
