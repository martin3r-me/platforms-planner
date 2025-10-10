<div class="h-full">
    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check">
                @if($task->project)
                    <a href="{{ route('planner.embedded.project', ['plannerProject' => $task->project->id]) }}" class="text-sm underline text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] mr-2">
                        Zurück zum Projekt
                    </a>
                @endif
            </x-ui-page-navbar>
        </x-slot>

        <x-ui-page-container spacing="space-y-8">
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight">{{ $task->title }}</h1>
                        <div class="flex items-center gap-6 text-sm text-[var(--ui-muted)]">
                            @if($task->project)
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-folder', 'w-4 h-4')
                                    {{ $task->project->name }}
                                </span>
                            @endif
                            @if($task->userInCharge)
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-user', 'w-4 h-4')
                                    {{ $task->userInCharge->fullname ?? $task->userInCharge->name }}
                                </span>
                            @endif
                            @if($task->due_date)
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-calendar', 'w-4 h-4')
                                    {{ $task->due_date->format('d.m.Y H:i') }}
                                </span>
                            @endif
                            @if($task->story_points)
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-sparkles', 'w-4 h-4')
                                    {{ $task->story_points->points() }} SP
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($task->is_done)
                            <x-ui-badge variant="success" size="lg">Erledigt</x-ui-badge>
                        @endif
                        @if($task->is_frog)
                            <x-ui-badge variant="danger" size="lg">Frosch</x-ui-badge>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <x-ui-form-grid :cols="2" :gap="6">
                    <div>
                        <x-ui-input-text
                            name="task.title"
                            label="Titel"
                            wire:model.live.debounce.500ms="task.title"
                            placeholder="Aufgabentitel eingeben..."
                            required
                            :errorKey="'task.title'"
                        />
                    </div>
                    <div>
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
                    <div>
                        <x-ui-input-datetime
                            name="dueDateInput"
                            label="Fälligkeitsdatum"
                            :value="$dueDateInput"
                            wire:model="dueDateInput"
                            placeholder="Fälligkeitsdatum auswählen..."
                            :nullable="true"
                            :errorKey="'dueDateInput'"
                        />
                    </div>
                    <div>
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
                    <div>
                        <x-ui-input-select
                            name="task.user_in_charge_id"
                            label="Verantwortlicher"
                            :options="($teamUsers ?? collect([]))"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Verantwortlichen auswählen –"
                            wire:model.live="task.user_in_charge_id"
                        />
                    </div>
                </x-ui-form-grid>

                <div class="mt-8 pt-8 border-t border-[var(--ui-border)]/60">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-ui-panel class="bg-gray-50 rounded-lg">
                            <x-ui-input-checkbox
                                model="task.is_done"
                                checked-label="Erledigt"
                                unchecked-label="Als erledigt markieren"
                                size="md"
                                block="true"
                            />
                        </x-ui-panel>
                        <x-ui-panel class="bg-gray-50 rounded-lg">
                            <x-ui-input-checkbox
                                model="task.is_frog"
                                checked-label="Frosch (wichtig & unangenehm)"
                                unchecked-label="Als Frosch markieren"
                                size="md"
                                block="true"
                            />
                        </x-ui-panel>
                    </div>
                </div>

                <div class="mt-8 pt-8 border-t border-[var(--ui-border)]/60">
                    <x-ui-input-textarea
                        name="task.description"
                        label="Beschreibung"
                        wire:model.live.debounce.500ms="task.description"
                        placeholder="Aufgabenbeschreibung (optional)"
                        rows="4"
                        :errorKey="'task.description'"
                    />
                </div>
            </div>
        </x-ui-page-container>
    </x-ui-page>
</div>


