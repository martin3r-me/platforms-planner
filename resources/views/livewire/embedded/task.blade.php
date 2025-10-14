<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check">
            <div class="mt-1 text-sm text-[var(--ui-muted)] flex items-center gap-2">
                <span class="flex items-center gap-1">
                    @svg('heroicon-o-home', 'w-4 h-4')
                    Teams
                </span>
                @if($task->project)
                    <span>›</span>
                    <a href="{{ route('planner.embedded.project', $task->project) }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                        @svg('heroicon-o-folder', 'w-4 h-4')
                        {{ $task->project->name }}
                    </a>
                @endif
                <span>›</span>
                <span class="flex items-center gap-1">
                    @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                    Aufgabe
                </span>
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <div class="p-4">
        <div class="bg-white rounded-lg border p-4">
            <div class="text-sm text-[var(--ui-muted)] mb-2">Task-ID: {{ $task->id }}</div>
            <x-ui-input-text
                name="task.title"
                label="Titel"
                wire:model.live.debounce.500ms="task.title"
                placeholder="Aufgabentitel eingeben..."
                required
                :errorKey="'task.title'"
            />
        </div>
    </div>
</x-ui-page>

