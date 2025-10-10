<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check">
            <x-slot name="titleActions">
                @can('update', $task)
                    <x-ui-button variant="secondary-ghost" size="sm" rounded="full" iconOnly="true" x-data @click="$dispatch('open-modal-task-settings', { taskId: {{ $task->id }} })" title="Einstellungen">
                        @svg('heroicon-o-cog-6-tooth','w-4 h-4')
                    </x-ui-button>
                @endcan
            </x-slot>
            
            {{-- Simple Breadcrumbs --}}
            <div class="flex items-center space-x-2 text-sm">
                @php($embedded = request()->is('*/embedded/*') || request()->boolean('embedded', false))
                @if($embedded)
                    <a href="{{ route('planner.dashboard') }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                        @svg('heroicon-o-home', 'w-4 h-4')
                        Dashboard
                    </a>
                    @if($task->project)
                        <span class="text-[var(--ui-muted)]">›</span>
                        <a href="{{ route('planner.embedded.project', $task->project) }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                            @svg('heroicon-o-folder', 'w-4 h-4')
                            {{ $task->project->name }}
                        </a>
                    @endif
                    <span class="text-[var(--ui-muted)]">›</span>
                    <span class="text-[var(--ui-muted)] flex items-center gap-1">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                        {{ $task->title }}
                    </span>
                @else
                    <a href="{{ route('planner.dashboard') }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                        @svg('heroicon-o-home', 'w-4 h-4')
                        Dashboard
                    </a>
                    <span class="text-[var(--ui-muted)]">›</span>
                    <a href="{{ route('planner.my-tasks') }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                        @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                        Meine Aufgaben
                    </a>
                    @if($task->project)
                        <span class="text-[var(--ui-muted)]">›</span>
                        <a href="{{ route('planner.projects.show', $task->project) }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                            @svg('heroicon-o-folder', 'w-4 h-4')
                            {{ $task->project->name }}
                        </a>
                    @endif
                    <span class="text-[var(--ui-muted)]">›</span>
                    <span class="text-[var(--ui-muted)] flex items-center gap-1">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                        {{ $task->title }}
                    </span>
                @endif
            </div>
            
            @can('update', $task)
                <div class="flex items-center gap-2">
                    {{-- Diskrete Auto-Save Status --}}
                    <div class="flex items-center gap-1 text-xs text-[var(--ui-muted)]" x-data="{ 
                        showSaved: false,
                        init() {
                            // Nur Manual Save Status anzeigen
                            this.$wire.on('task-saved', () => {
                                this.showSaved = true;
                                setTimeout(() => this.showSaved = false, 1500);
                            });
                        }
                    }">
                        <div x-show="showSaved" class="flex items-center gap-1 text-[var(--ui-primary)]">
                            @svg('heroicon-o-check-circle', 'w-3 h-3')
                            <span>Gespeichert</span>
                        </div>
                    </div>
                    
                    {{-- Manual Save Button --}}
                    <x-ui-button variant="secondary" size="sm" wire:click="save">
                        <span class="inline-flex items-center gap-2">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            <span class="hidden sm:inline">Speichern</span>
                        </span>
                    </x-ui-button>
                </div>
            @endcan
            @if($printingAvailable)
                <x-ui-button variant="secondary" size="sm" wire:click="printTask()">
                    @svg('heroicon-o-printer', 'w-4 h-4')
                    <span class="hidden sm:inline ml-1">Drucken</span>
                </x-ui-button>
            @endif
        </x-ui-page-navbar>
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
                        :options="$teamUsers"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="– Verantwortlichen auswählen –"
                        wire:model.live="task.user_in_charge_id"
                    />
                </div>
            </x-ui-form-grid>

            {{-- Status Checkboxes --}}
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

            {{-- Description --}}
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

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Navigation & Details" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Navigation --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Navigation</h3>
                    <div class="space-y-2">
                        @if($task->project)
                            <x-ui-button
                                variant="secondary-outline"
                                size="sm"
                                :href="route('planner.projects.show', ['plannerProject' => $task->project->id])"
                                wire:navigate
                                class="w-full"
                            >
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-folder', 'w-4 h-4')
                                    Zum Projekt
                                </span>
                            </x-ui-button>
                        @endif
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            :href="route('planner.my-tasks')"
                            wire:navigate
                            class="w-full"
                        >
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                                Zu meinen Aufgaben
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Quick Stats --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Status</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">Erledigt</span>
                            <x-ui-input-checkbox
                                model="task.is_done"
                                checked-label=""
                                unchecked-label=""
                                size="sm"
                                block="false"
                            />
                        </div>
                        <div class="flex items-center justify-between py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">Frosch</span>
                            <x-ui-input-checkbox
                                model="task.is_frog"
                                checked-label=""
                                unchecked-label=""
                                size="sm"
                                block="false"
                            />
                        </div>
                    </div>
                </div>

                {{-- Metrics --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Metriken</h3>
                    <div class="space-y-3">
                        @if($task->userInCharge)
                            <div class="py-3 px-4 bg-[var(--ui-info-5)] rounded-lg border-l-4 border-[var(--ui-info)]">
                                <div class="text-xs text-[var(--ui-info)] font-medium uppercase tracking-wide">Verantwortlicher</div>
                                <div class="text-lg font-bold text-[var(--ui-info)]">{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-primary-5)] rounded-lg border-l-4 border-[var(--ui-primary)]">
                            <div class="text-xs text-[var(--ui-primary)] font-medium uppercase tracking-wide">Offen seit</div>
                            <div class="text-lg font-bold text-[var(--ui-primary)]">{{ optional($task->created_at)->diffForHumans(null, true) }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)] font-medium uppercase tracking-wide">Kommentare</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">0</div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
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