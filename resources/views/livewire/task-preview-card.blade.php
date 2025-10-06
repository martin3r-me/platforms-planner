<x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.tasks.show', $task)">
    <div class="text-xs text-[var(--ui-muted)]">
        @if($task->due_date)
            Fällig: {{ $task->due_date->format('d.m.Y') }}
        @else
            Keine Fälligkeit
        @endif
    </div>
</x-ui-kanban-card>