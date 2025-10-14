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

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Navigation & Details" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-3 text-sm">
                @if($task->project)
                    <x-ui-button variant="secondary-outline" :href="route('planner.embedded.project', $task->project)" class="w-full">
                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4 mr-1')
                        Zur Projektübersicht
                    </x-ui-button>
                @endif
                <div class="text-[var(--ui-muted)]">Task-ID: {{ $task->id }}</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" side="right" storeKey="activityOpen">
            <div class="p-4 space-y-2 text-sm">
                <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                    <div class="font-medium text-[var(--ui-secondary)] truncate">Aufgabe geöffnet</div>
                    <div class="text-[var(--ui-muted)]">Gerade eben</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="p-4 space-y-4">
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
        <div class="bg-white rounded-lg border p-4">
            <x-ui-input-select
                name="task.priority"
                label="Priorität"
                :options="\Platform\Planner\Enums\TaskPriority::cases()"
                optionValue="value"
                optionLabel="label"
                :nullable="false"
                wire:model.live="task.priority"
            />
        </div>
    </div>
</x-ui-page>

