<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check">
            @if($task->project)
                <a href="{{ auth()->user()->can('view', $task->project) ? route('planner.projects.show', $task->project) : '#' }}"
                   @if(auth()->user()->can('view', $task->project)) wire:navigate @endif
                   class="hidden md:inline text-sm underline text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] mr-2">
                    Projekt: {{ $task->project->name }}
                </a>
            @endif
            <a href="{{ route('planner.my-tasks') }}" wire:navigate class="hidden md:inline text-sm underline text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] mr-2">
                Meine Aufgaben
            </a>
            @can('update', $task)
                <x-ui-button variant="primary" size="sm" rounded="full" wire:click="save">
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span class="hidden sm:inline">Speichern</span>
                    </span>
                </x-ui-button>
            @endcan

            <x-ui-button variant="secondary-ghost" size="sm" rounded="full" iconOnly="true" x-data @click="Alpine.store('page').activityOpen = !Alpine.store('page').activityOpen" title="Aktivitäten">
                @svg('heroicon-o-bell-alert','w-6 h-6')
            </x-ui-button>
        </x-ui-page-navbar>
    </x-slot>

    <div class="flex-1 overflow-y-auto p-4 min-w-0 space-y-6">
        {{-- Aufgaben-Details --}}
        <x-ui-panel title="Aufgaben-Details">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui-input-text 
                        name="task.title"
                        label="Titel"
                        wire:model.live.debounce.500ms="task.title"
                        placeholder="Aufgabentitel eingeben..."
                        required
                        :errorKey="'task.title'"
                    />
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-ui-input-date
                        name="task.due_date"
                        label="Fälligkeitsdatum"
                        wire:model.live.debounce.500ms="task.due_date"
                        placeholder="Fälligkeitsdatum (optional)"
                        :nullable="true"
                        :errorKey="'task.due_date'"
                    />
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
                <div class="mt-4">
                    <x-ui-input-textarea 
                        name="task.description"
                        label="Beschreibung"
                        wire:model.live.debounce.500ms="task.description"
                        placeholder="Aufgabenbeschreibung (optional)"
                        rows="4"
                        :errorKey="'task.description'"
                    />
                </div>
        </x-ui-panel>

            {{-- Status & Zuweisung --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">Status & Zuweisung</h3>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-checkbox
                        model="task.is_done"
                        checked-label="Erledigt"
                        unchecked-label="Als erledigt markieren"
                        size="md"
                        block="true"
                    />
                    <x-ui-input-checkbox
                        model="task.is_frog"
                        checked-label="Frosch (wichtig & unangenehm)"
                        unchecked-label="Als Frosch markieren"
                        size="md"
                        block="true"
                    />
                </div>
            </div>
        </div>
        <!-- Aktivitäten unten -->
        
    </div>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-6">
                {{-- Navigation Buttons --}}
                <div class="flex flex-col gap-2">
                    @if($task->project)
                        <x-ui-button 
                            variant="secondary-outline" 
                            size="md" 
                            :href="route('planner.projects.show', ['plannerProject' => $task->project->id])" 
                            wire:navigate
                            class="w-full"
                        >
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Zum Projekt
                            </div>
                        </x-ui-button>
                    @endif
                    <x-ui-button 
                        variant="secondary-outline" 
                        size="md" 
                        :href="route('planner.my-tasks')" 
                        wire:navigate
                        class="w-full"
                    >
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-arrow-left', 'w-4 h-4')
                            Zu meinen Aufgaben
                        </div>
                    </x-ui-button>
                </div>

                {{-- Kurze Übersicht --}}
                <x-ui-panel title="Aufgaben-Übersicht">
                    <div class="space-y-1 text-sm">
                        <div><strong>Titel:</strong> {{ $task->title }}</div>
                        @if($task->project)
                            <div><strong>Projekt:</strong> {{ $task->project->name }}</div>
                        @endif
                        @if($task->due_date)
                            <div><strong>Fällig:</strong> {{ $task->due_date->format('d.m.Y') }}</div>
                        @endif
                        @if($task->story_points)
                            <div><strong>Story Points:</strong> {{ $task->story_points }}</div>
                        @endif
                    </div>
                </x-ui-panel>

                {{-- Status --}}
                <x-ui-input-checkbox
                    model="task.is_done"
                    checked-label="Aufgabe erledigt"
                    unchecked-label="Als erledigt markieren"
                    size="md"
                    block="true"
                />

                <hr class="my-4">

                {{-- Aktionen --}}
                <x-ui-panel title="Aktionen">
                    <div class="space-y-2 mt-2">
                        @can('delete', $task)
                            <x-ui-confirm-button 
                                action="delete" 
                                text="Aufgabe löschen" 
                                confirmText="Wirklich löschen?" 
                                variant="danger-outline"
                                :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                                class="w-full"
                            />
                        @endcan
                    </div>
                </x-ui-panel>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" class="border-l border-r-0">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                            <div class="text-[var(--ui-muted)]">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <!-- Print Modal direkt hier einbinden -->
    <livewire:planner.print-modal />
</x-ui-page>