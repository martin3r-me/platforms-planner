<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- Modern Header --}}
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
        {{-- Form Section --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <x-ui-form-grid :cols="2" :gap="6">
                <div>
                        <x-ui-input-text
                            name="task.title"
                            label="Titel"
                            wire:model.live.debounce.1000ms="task.title"
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
                        :options="$teamUsers"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="– Verantwortlichen auswählen –"
                        wire:model.live="task.user_in_charge_id"
                    />
                </div>
            </x-ui-form-grid>

            

            {{-- Description --}}
            <div class="mt-8 pt-8 border-t border-[var(--ui-border)]/60">
                    <x-ui-input-textarea
                        name="task.description"
                        label="Beschreibung"
                        wire:model.live.debounce.1000ms="task.description"
                        placeholder="Aufgabenbeschreibung (optional)"
                        rows="4"
                        :errorKey="'task.description'"
                    />
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen (Save/Print) --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        @can('update', $task)
                            @if($this->isDirty())
                                <x-ui-button variant="primary" size="sm" wire:click="save" class="w-full">
                                    <span class="inline-flex items-center gap-2">
                                        @svg('heroicon-o-check','w-4 h-4')
                                        Speichern
                                    </span>
                                </x-ui-button>
                            @endif
                        @endcan
                        @if($printingAvailable)
                            <x-ui-button variant="secondary" size="sm" wire:click="printTask()" class="w-full">
                                <span class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-printer', 'w-4 h-4')
                                    Drucken
                                </span>
                            </x-ui-button>
                        @endif
                        @can('delete', $task)
                            <x-ui-confirm-button 
                                action="deleteTask" 
                                text="Löschen" 
                                confirmText="Wirklich löschen?" 
                                variant="danger"
                                :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                                class="w-full"
                            />
                        @endcan
                    </div>
                </div>

                {{-- Quick Links --}}
                <div class="space-y-2">
                    @if($task->project)
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('planner.projects.show', ['plannerProject' => $task->project->id])" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-folder', 'w-4 h-4')
                                Zum Projekt
                            </span>
                        </x-ui-button>
                    @endif
                    <x-ui-button variant="secondary-outline" size="sm" :href="route('planner.my-tasks')" wire:navigate class="w-full">
                        <span class="flex items-center gap-2">
                            @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                            Zu meinen Aufgaben
                        </span>
                    </x-ui-button>
                </div>

                {{-- Status (interaktiv, stile wie Statistiken) --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-success)]')
                            <span class="text-sm text-[var(--ui-secondary)]">Status</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $task->is_done ? 'Erledigt' : 'Offen' }}</span>
                            <x-ui-button :variant="$task->is_done ? 'success' : 'secondary-outline'" size="xs" wire:click="toggleDone">
                                {{ $task->is_done ? 'Zurück' : 'Erledigen' }}
                            </x-ui-button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--ui-warning)]')
                            <span class="text-sm text-[var(--ui-secondary)]">Frosch</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $task->is_frog ? 'Ja' : 'Nein' }}</span>
                            <x-ui-button :variant="$task->is_frog ? 'warning' : 'secondary-outline'" size="xs" wire:click="toggleFrog">
                                {{ $task->is_frog ? 'Zurück' : 'Markieren' }}
                            </x-ui-button>
                        </div>
                    </div>
                </div>

                

                
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
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