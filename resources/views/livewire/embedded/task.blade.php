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
        <div class="bg-white rounded-lg border p-4 space-y-3">
            <label class="block text-sm font-medium text-[var(--ui-secondary)]">Fälligkeitsdatum</label>
            <input 
                type="datetime-local" 
                class="w-full rounded border border-[var(--ui-border)]/60 px-3 py-2 text-sm"
                wire:model.live.debounce.400ms="dueDateInput"
                @keydown.enter.prevent
                @change.stop
            />
            <div class="flex items-center justify-between text-xs text-[var(--ui-muted)]">
                <div>Eingabe schreibt direkt in dueDateInput. Speichern setzt task.due_date.</div>
                <div class="flex items-center gap-2">
                    <x-ui-button variant="secondary-ghost" size="xs" wire:click="$set('dueDateInput','')">
                        @svg('heroicon-o-x-mark','w-4 h-4')
                        Leeren
                    </x-ui-button>
                    <x-ui-button variant="primary" size="xs" wire:click="save">
                        @svg('heroicon-o-check','w-4 h-4')
                        Speichern
                    </x-ui-button>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <x-ui-input-select
                name="task.story_points"
                label="Story Points"
                :options="\Platform\Planner\Enums\TaskStoryPoints::cases()"
                optionValue="value"
                optionLabel="label"
                :nullable="true"
                nullLabel="– Story Points auswählen –"
                wire:model.live="task.story_points"
            />
        </div>
        <div class="bg-white rounded-lg border p-4">
            <x-ui-input-select
                name="task.user_in_charge_id"
                label="Verantwortlicher"
                :options="$teamUsers"
                optionValue="id"
                optionLabel="name"
                :nullable="true"
                nullLabel="– Verantwortlichen auswählen –"
                wire:model.live="task.user_in_charge_id"
            />
        </div>
    </div>
</x-ui-page>

