<x-ui-kanban-card 
    :sortable-id="$task->id" 
    :title="'AUFGABE'"
    href="{{ route('planner.tasks.show', $task) }}"
>
    {{ $task->title }} @if($task->project)<small class = "text-secondary">| {{ $task->project?->name }}</small>@endif 
    <p class = "text-xs text-muted">{{ $task->description }}</p>

    <x-slot name="footer">
        <span class="text-xs text-muted">Zuletzt bearbeitet: 17.07.2025</span>
        <div class="d-flex gap-1">
            @if($task->story_points)
            <x-ui-badge variant="primary" size="xs">
                {{ $task->story_points->label() }}
            </x-ui-badge>
            @endif
            @if($task->priority)
            <x-ui-badge variant="danger" size="xs">
                {{ $task->priority->label() }}
            </x-ui-badge>
            @endif
        </div>
    </x-slot>
</x-ui-kanban-card>
         