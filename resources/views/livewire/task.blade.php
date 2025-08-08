<div class="d-flex h-full">
    <!-- Linke Spalte -->
    <div class="flex-grow-1 d-flex flex-col">
        <!-- Header oben (fix) -->
        <div class="border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <div class="d-flex gap-1">
                <div class="d-flex">
                    @if($task->project)
                        @can('view', $task->project)
                            <a href="{{ route('planner.projects.show', $task->project) }}" class="px-3 underline" wire:navigate>
                                Projekt: {{ $task->project?->name }}
                            </a>
                        @else
                            <span class="px-3 text-gray-400" title="Kein Zugriff auf das Projekt">
                                Projekt: {{ $task->project?->name }} <span class="italic">(kein Zugriff)</span>
                            </span>
                        @endcan
                    @endif

                    <a href="{{ route('planner.my-tasks') }}" class="d-flex px-3 border-right-solid border-right-1 border-right-muted underline" wire:navigate>
                        Meine Aufgaben
                    </a>
                </div>
                <div class="flex-grow-1 text-right">{{ $task->title }}</div>
            </div>
        </div>

        <!-- Haupt-Content (nimmt Restplatz, scrollt) -->
        <div class="flex-grow-1 overflow-y-auto p-4">
            <div class="form-group">
                <!-- Titel -->
                @can('update', $task)
                    <x-ui-input-text 
                        name="task.title"
                        label="Task-Titel"
                        wire:model.live.debounce.500ms="task.title"
                        placeholder="Task-Titel eingeben..."
                        required
                        :errorKey="'task.title'"
                    />
                @else
                    <div>
                        <label class="font-semibold">Task-Titel:</label>
                        <div>{{ $task->title }}</div>
                    </div>
                @endcan
            </div>

            <div class="form-group">
                <!-- Beschreibung -->
                @can('update', $task)
                    <x-ui-input-textarea 
                        name="task.description"
                        label="Aufgaben Beschreibung"
                        wire:model.live.debounce.500ms="task.description"
                        placeholder="Aufgaben Beschreibung eingeben..."
                        :errorKey="'task.description'"
                    />
                @else
                    <div>
                        <label class="font-semibold">Beschreibung:</label>
                        <div>{{ $task->description }}</div>
                    </div>
                @endcan
            </div>
        </div>

        <!-- Aktivitäten (immer unten) -->
        <div x-data="{ open: false }" class="flex-shrink-0 border-t border-muted">
            <div 
                @click="open = !open" 
                class="cursor-pointer border-top-1 border-top-solid border-top-muted border-bottom-1 border-bottom-solid border-bottom-muted p-2 text-center d-flex items-center justify-center gap-1 mx-2 shadow-lg"
            >
                AKTIVITÄTEN 
                <span class="text-xs">
                    {{$task->activities->count()}}
                </span>
                <x-heroicon-o-chevron-double-down 
                    class="w-3 h-3" 
                    x-show="!open"
                />
                <x-heroicon-o-chevron-double-up 
                    class="w-3 h-3" 
                    x-show="open"
                />
            </div>
            <div x-show="open" class="p-2 max-h-xs overflow-y-auto">
                <livewire:activity-log.index
                    :model="$task"
                    :key="get_class($task) . '_' . $task->id"
                />
            </div>
        </div>
    </div>

    <!-- Rechte Spalte -->
    <div class="min-w-80 w-80 d-flex flex-col border-left-1 border-left-solid border-left-muted">

        <div class="d-flex gap-2 border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <x-heroicon-o-cog-6-tooth class="w-6 h-6"/>
            Einstellungen
        </div>
        <div class="flex-grow-1 overflow-y-auto p-4">

            {{-- Navigation Buttons --}}
            <div class="d-flex flex-col gap-2 mb-4">
                @if($task->project)
                    @can('view', $task->project)
                        <x-ui-button 
                            variant="secondary-outline" 
                            size="md" 
                            :href="route('planner.projects.show', $task->project)" 
                            wire:navigate
                            class="w-full d-flex"
                        >
                            <div class="d-flex items-center gap-2">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Projekt: {{ $task->project?->name }}
                            </div>
                        </x-ui-button>
                    @else
                        <x-ui-button 
                            variant="secondary-outline" 
                            size="md" 
                            disabled="true"
                            title="Kein Zugriff auf das Projekt"
                            class="w-full d-flex"
                        >
                            <div class="d-flex items-center gap-2">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Projekt: {{ $task->project?->name }}
                            </div>
                        </x-ui-button>
                    @endcan
                @endif
                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    :href="route('planner.my-tasks')" 
                    wire:navigate
                    class="w-full d-flex"
                >
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-left', 'w-4 h-4')
                        Meine Aufgaben
                    </div>
                </x-ui-button>
            
            {{-- Erledigt-Checkbox --}}
            @can('update', $task)
                <x-ui-input-checkbox
                    model="task.is_done"
                    checked-label="Erledigt"
                    unchecked-label="Als erledigt markieren"
                    size="md"
                    block="true"
                    variant="success"
                    :icon="@svg('heroicon-o-check-circle', 'w-4 h-4')->toHtml()"
                />
            @else
                <div>
                    <x-ui-badge variant="{{ $task->is_done ? 'success' : 'gray' }}">
                        @svg('heroicon-o-check-circle', 'w-4 h-4')
                        {{ $task->is_done ? 'Erledigt' : 'Offen' }}
                    </x-ui-badge>
                </div>
            @endcan

            {{-- Frosch-Checkbox --}}
            @can('update', $task)
                <x-ui-input-checkbox
                    model="task.is_frog"
                    checked-label="Ist ein Frosch"
                    unchecked-label="Sei ein Frosch"
                    size="md"
                    block="true"
                    variant="warning"
                    :icon="@svg('heroicon-o-exclamation-triangle', 'w-4 h-4')->toHtml()"
                />
            @else
                <div>
                    <x-ui-badge variant="{{ $task->is_frog ? 'warning' : 'gray' }}">
                        @svg('heroicon-o-exclamation-triangle', 'w-4 h-4')
                        {{ $task->is_frog ? 'Frosch-Aufgabe' : 'Normale Aufgabe' }}
                    </x-ui-badge>
                </div>
            @endcan
            </div>

            {{-- Priorität --}}
            @can('update', $task)
                <x-ui-input-select
                    name="task.priority"
                    label="Priorität"
                    :options="\Platform\Planner\Enums\TaskPriority::cases()"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="false"
                    wire:model.live="task.priority"
                />
            @else
                <div>
                    <label class="font-semibold">Priorität:</label>
                    <div>{{ $task->priority->label() ?? '–' }}</div>
                </div>
            @endcan

            {{-- Story Points --}}
            @can('update', $task)
                <x-ui-input-select
                    name="task.story_points"
                    label="Story Points"
                    :options="\Platform\Planner\Enums\TaskStoryPoints::cases()"
                    optionValue="value"
                    optionLabel="label"
                    :nullable="true"
                    nullLabel="– Kein Wert –"
                    wire:model.live="task.story_points"
                />
            @else
                <div>
                    <label class="font-semibold">Story Points:</label>
                    <div>{{ $task->story_points?->label() ?? '–' }}</div>
                </div>
            @endcan

            {{-- Verantwortlich (nur wenn Projekt und Projekt-User verfügbar) --}}
            @if ($task?->project && $task?->project?->projectUsers)
                @can('update', $task)
                    <x-ui-input-select
                        name="task.user_in_charge_id"
                        label="Verantwortlich"
                        :options="$task?->project?->projectUsers ?? []"
                        optionValue="user.id"
                        optionLabel="user.fullname"
                        :nullable="true"
                        nullLabel="– Keine Auswahl –"
                        wire:model.live="task.user_in_charge_id"
                    />
                @else
                    <div>
                        <label class="font-semibold">Verantwortlich:</label>
                        <div>
                            {{
                                optional(
                                    collect($task->project->projectUsers ?? [])
                                        ->firstWhere('user.id', $task->user_in_charge_id)
                                )['user']['fullname'] ?? '–'
                            }}
                        </div>
                    </div>
                @endcan
            @endif

            <hr>

            {{-- Löschen-Buttons --}}
            @can('delete', $task)
                <div class="d-flex flex-col gap-2">
                    <x-ui-confirm-button 
                        action="deleteTaskAndReturnToDashboard" 
                        text="Zu Meinen Aufgaben" 
                        confirmText="Löschen?" 
                        variant="danger-outline"
                        :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                    />
                    
                    @if($task->project)
                        <x-ui-confirm-button 
                            action="deleteTaskAndReturnToProject" 
                            text="Zum Projekt" 
                            confirmText="Löschen?" 
                            variant="danger-outline"
                            :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                        />
                    @endif
                </div>
            @endcan
        </div>
    </div>
</div>