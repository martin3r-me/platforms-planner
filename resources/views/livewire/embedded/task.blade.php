<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check" />
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

