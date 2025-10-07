<x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.tasks.show', $task)">
    <div class="flex items-center justify-between mb-1">
        <div class="flex items-center gap-2 text-[var(--ui-secondary)] text-xs">
            @if($task->project)
                @svg('heroicon-o-folder','w-3.5 h-3.5')
                <span class="truncate max-w-[10rem]">{{ $task->project->name }}</span>
            @endif
        </div>
        @if($task->priority)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ strtoupper($task->priority) }}</span>
        @endif
    </div>

    <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
        @svg('heroicon-o-calendar', 'w-3.5 h-3.5')
        @if($task->due_date)
            <span>Fällig: {{ $task->due_date->format('d.m.Y') }}</span>
        @else
            <span>Keine Fälligkeit</span>
        @endif
    </div>

    @if($task->story_points)
        <div class="mt-1 text-[10px] text-[var(--ui-muted)]">SP: {{ $task->story_points }}</div>
    @endif
</x-ui-kanban-card>