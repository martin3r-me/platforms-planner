<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight leading-tight">{{ $task->title }}</h1>
                        
                        {{-- Meta Informationen -- schlicht ohne Rahmen --}}
                        <div class="space-y-2">
                            {{-- Erste Zeile: Team & Projekt --}}
                            <div class="flex flex-wrap items-center gap-6 text-sm text-[var(--ui-muted)]">
                                @if($task->team)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-user-group', 'w-4 h-4')
                                        <span>Team: <span class="text-[var(--ui-secondary)]">{{ $task->team->name }}</span></span>
                                    </span>
                                @endif
                                @if($task->project)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-folder', 'w-4 h-4')
                                        <span>Projekt: <span class="text-[var(--ui-secondary)]">{{ $task->project->name }}</span></span>
                                    </span>
                                @endif
                            </div>
                            
                            {{-- Zweite Zeile: Personen & Details --}}
                            <div class="flex flex-wrap items-center gap-6 text-sm text-[var(--ui-muted)]">
                                @if($task->user)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-user-circle', 'w-4 h-4')
                                        <span>Erstellt von: <span class="text-[var(--ui-secondary)]">{{ $task->user->fullname ?? $task->user->name }}</span></span>
                                    </span>
                                @endif
                                @if($task->userInCharge)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-user', 'w-4 h-4')
                                        <span>Verantwortlich: <span class="text-[var(--ui-secondary)]">{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span></span>
                                    </span>
                                @endif
                                @if($task->due_date)
                                    @php
                                        $isOverdue = $task->due_date->isPast() && !$task->is_done;
                                        $isToday = $task->due_date->isToday();
                                        $isTomorrow = $task->due_date->isTomorrow();
                                        $dueDateColor = $isOverdue ? 'text-[var(--ui-danger)]' : ($isToday || $isTomorrow ? 'text-[var(--ui-warning)]' : 'text-[var(--ui-muted)]');
                                        $dueDateTextColor = $isOverdue ? 'text-[var(--ui-danger)]' : ($isToday || $isTomorrow ? 'text-[var(--ui-warning)]' : 'text-[var(--ui-secondary)]');
                                    @endphp
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-calendar', 'w-4 h-4 ' . $dueDateColor)
                                        <span>Fällig: <span class="{{ $dueDateTextColor }}">{{ $task->due_date->format('d.m.Y H:i') }}</span></span>
                                    </span>
                                @endif
                                @if($task->story_points)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-sparkles', 'w-4 h-4')
                                        <span>Story Points: <span class="text-[var(--ui-secondary)] font-medium">{{ $task->story_points->points() }} SP</span></span>
                                    </span>
                                @endif
                            </div>

                            {{-- Zusatzinfos: Ursprungs-Datum & Verschiebungen --}}
                            @if($task->original_due_date || ($task->postpone_count ?? 0) > 0)
                                <div class="flex flex-wrap items-center gap-6 text-sm text-[var(--ui-muted)]">
                                    @if($task->original_due_date)
                                        <span class="flex items-center gap-2">
                                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                            <span>Ursprünglich: <span class="text-[var(--ui-secondary)]">{{ $task->original_due_date->format('d.m.Y H:i') }}</span></span>
                                        </span>
                                    @endif
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                        <span>Verschoben: <span class="text-[var(--ui-secondary)]">{{ $task->postpone_count ?? 0 }}×</span></span>
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Status Badges -- kleiner --}}
                    <div class="flex flex-col items-end gap-2 flex-shrink-0">
                        @if($task->is_done)
                            <x-ui-badge variant="success" size="sm">Erledigt</x-ui-badge>
                        @endif
                        @if($task->is_frog)
                            <x-ui-badge variant="danger" size="sm">Frosch</x-ui-badge>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        {{-- Form Section --}}
        <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                {{-- Grundinformationen --}}
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grundinformationen</h2>
                    <x-ui-form-grid :cols="2" :gap="6">
                        <div class="col-span-2">
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
                    </x-ui-form-grid>
                </div>

                {{-- Fälligkeit & Verantwortung --}}
                <div class="mb-8 pb-8 border-b border-[var(--ui-border)]/60">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Fälligkeit & Verantwortung</h2>
                    <x-ui-form-grid :cols="2" :gap="6">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Fälligkeitsdatum
                            </label>
                            <button
                                type="button"
                                wire:click="openDueDateModal"
                                class="w-full px-4 py-2.5 text-left bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 rounded-lg hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)] flex items-center justify-between group"
                            >
                                <span class="flex items-center gap-2 text-sm text-[var(--ui-secondary)]">
                                    @svg('heroicon-o-calendar', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                                    @if($task->due_date)
                                        <span class="font-medium">{{ $task->due_date->format('d.m.Y H:i') }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">Kein Datum gesetzt</span>
                                    @endif
                                </span>
                                @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                            </button>
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
                </div>

                {{-- Anmerkung --}}
                <div class="mb-8 pb-8 border-b border-[var(--ui-border)]/60">
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-1">
                            <label class="text-sm font-semibold text-[var(--ui-secondary)]">Anmerkung</label>
                            <span class="text-xs text-[var(--ui-muted)] px-1.5 py-0.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                Verschlüsselt
                            </span>
                        </div>
                        <p class="text-xs text-[var(--ui-muted)]">Zusätzliche Notizen und Informationen zur Aufgabe</p>
                    </div>
                    <x-ui-input-textarea
                        name="description"
                        label=""
                        wire:model.live.debounce.1000ms="description"
                        :placeholder="empty($description) ? 'Zusätzliche Notizen und Informationen zur Aufgabe (optional)' : ''"
                        rows="6"
                        :errorKey="'description'"
                    />
                </div>

                {{-- Definition of Done --}}
                <div>
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-1">
                            <label class="text-sm font-semibold text-[var(--ui-secondary)]">Definition of Done</label>
                            <span class="text-xs text-[var(--ui-muted)] px-1.5 py-0.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                Verschlüsselt
                            </span>
                        </div>
                        <p class="text-xs text-[var(--ui-muted)]">Kriterien, die erfüllt sein müssen, damit die Aufgabe als erledigt gilt</p>
                    </div>
                    <x-ui-input-textarea
                        name="dod"
                        label=""
                        wire:model.live.debounce.1000ms="dod"
                        :placeholder="empty($dod) ? 'Kriterien, die erfüllt sein müssen, damit die Aufgabe als erledigt gilt (optional)' : ''"
                        rows="6"
                        :errorKey="'dod'"
                    />
                </div>
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
                        @can('update', $task)
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="openMoveModal" class="w-full">
                                <span class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-arrows-right-left', 'w-4 h-4')
                                    Verschieben
                                </span>
                            </x-ui-button>
                        @endcan
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
                    @if($task->project && $this->canAccessProject)
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
                    <button type="button" wire:click="toggleDone" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-primary-5)] transition-colors cursor-pointer">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-success)]')
                            <span class="text-sm text-[var(--ui-secondary)]">Status</span>
                        </div>
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $task->is_done ? 'Erledigt' : 'Offen' }}</span>
                    </button>
                    <button type="button" wire:click="toggleFrog" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-primary-5)] transition-colors cursor-pointer">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--ui-warning)]')
                            <span class="text-sm text-[var(--ui-secondary)]">Frosch</span>
                        </div>
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $task->is_frog ? 'Ja' : 'Nein' }}</span>
                    </button>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-4">Letzte Aktivitäten</h3>
                <div class="space-y-3">
                    @forelse(($activities ?? []) as $activity)
                        <div class="p-3 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-muted)] transition-colors">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-[var(--ui-secondary)] leading-snug">
                                        {{ $activity['title'] ?? 'Aktivität' }}
                                    </div>
                                </div>
                                @if(($activity['type'] ?? null) === 'system')
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-xs text-[var(--ui-muted)]">
                                            @svg('heroicon-o-cog', 'w-3 h-3')
                                            System
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)]">
                                @svg('heroicon-o-clock', 'w-3 h-3')
                                <span>{{ $activity['time'] ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--ui-muted-5)] mb-3">
                                @svg('heroicon-o-clock', 'w-6 h-6 text-[var(--ui-muted)]')
                            </div>
                            <p class="text-sm text-[var(--ui-muted)]">Noch keine Aktivitäten</p>
                            <p class="text-xs text-[var(--ui-muted)] mt-1">Änderungen werden hier angezeigt</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <!-- Print Modal direkt hier einbinden -->
    <livewire:planner.print-modal />

    <!-- Move Task Modal -->
    <x-ui-modal size="md" wire:model="moveModalOpen" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-arrows-right-left', 'w-5 h-5 text-[var(--ui-primary)]')
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Aufgabe verschieben</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Projekt und Slot auswählen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-6">
            <x-ui-form-grid :cols="2" :gap="6">
                <div class="col-span-2">
                    <x-ui-input-select
                        name="targetProjectId"
                        label="Zielprojekt"
                        :options="$projectMoveOptions"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="– Projekt wählen –"
                        wire:model.live="targetProjectId"
                    />
                    <p class="mt-2 text-xs text-[var(--ui-muted)]">Nur Projekte mit Berechtigung werden angezeigt.</p>
                </div>
                <div class="col-span-2">
                    <x-ui-input-select
                        name="targetSlotId"
                        label="Slot im Zielprojekt"
                        :options="$projectSlotOptions"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="Backlog (kein Slot)"
                        wire:model.live="targetSlotId"
                        :disabled="!$targetProjectId"
                    />
                </div>
            </x-ui-form-grid>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeMoveModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button
                    variant="primary"
                    size="sm"
                    wire:click="moveTaskToProject"
                    wire:loading.attr="disabled"
                    wire:target="moveTaskToProject"
                    :disabled="!$targetProjectId"
                >
                    <span class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Verschieben
                    </span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    <!-- Due Date Modal -->
    <x-ui-modal size="md" wire:model="dueDateModalShow" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-calendar', 'w-5 h-5 text-[var(--ui-primary)]')
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Fälligkeitsdatum</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Datum und Uhrzeit festlegen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-6">
            <!-- Kalender Navigation -->
            <div class="flex items-center justify-between">
                <h2 class="flex-auto text-sm font-semibold text-[var(--ui-secondary)]">
                    {{ $this->calendarMonthName }}
                </h2>
                <div class="flex items-center gap-2">
                    <button 
                        type="button" 
                        wire:click="previousMonth"
                        class="flex flex-none items-center justify-center p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors rounded-lg hover:bg-[var(--ui-muted-5)]"
                    >
                        <span class="sr-only">Vorheriger Monat</span>
                        @svg('heroicon-o-chevron-left', 'w-5 h-5')
                    </button>
                    <button 
                        type="button" 
                        wire:click="nextMonth"
                        class="flex flex-none items-center justify-center p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors rounded-lg hover:bg-[var(--ui-muted-5)]"
                    >
                        <span class="sr-only">Nächster Monat</span>
                        @svg('heroicon-o-chevron-right', 'w-5 h-5')
                    </button>
                </div>
            </div>

            <!-- Wochentage Header -->
            <div class="grid grid-cols-7 text-center text-xs font-medium text-[var(--ui-muted)]">
                <div>Mo</div>
                <div>Di</div>
                <div>Mi</div>
                <div>Do</div>
                <div>Fr</div>
                <div>Sa</div>
                <div>So</div>
            </div>

            <!-- Kalender Grid -->
            <div class="grid grid-cols-7 gap-1 text-sm">
                @foreach($this->calendarDays as $day)
                    <div class="py-2 {{ !$loop->first ? 'border-t border-[var(--ui-border)]/40' : '' }}">
                        <button
                            type="button"
                            wire:click="selectDate('{{ $day['date'] }}')"
                            class="mx-auto flex w-8 h-8 items-center justify-center rounded-full transition-all duration-200
                                {{ !$day['isCurrentMonth'] ? 'text-[var(--ui-muted)]/50' : '' }}
                                {{ $day['isCurrentMonth'] && !$day['isToday'] && !$day['isSelected'] ? 'text-[var(--ui-secondary)] hover:bg-[var(--ui-primary-5)] hover:text-[var(--ui-primary)]' : '' }}
                                {{ $day['isToday'] && !$day['isSelected'] ? 'font-semibold text-[var(--ui-primary)]' : '' }}
                                {{ $day['isSelected'] && !$day['isToday'] ? 'font-semibold text-white bg-[var(--ui-secondary)]' : '' }}
                                {{ $day['isSelected'] && $day['isToday'] ? 'font-semibold text-white bg-[var(--ui-primary)]' : '' }}
                            "
                        >
                            <time datetime="{{ $day['date'] }}">{{ $day['day'] }}</time>
                        </button>
                    </div>
                @endforeach
            </div>

            <!-- Zeitauswahl -->
            <div class="pt-4 border-t border-[var(--ui-border)]/60">
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-3">
                    Uhrzeit
                </label>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Stunde</label>
                            <select
                                wire:model.live="selectedHour"
                                class="w-28 px-3 py-2 text-sm rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]"
                            >
                                @for($h = 0; $h < 24; $h++)
                                    <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="text-2xl font-bold text-[var(--ui-muted)]">:</div>

                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Minute</label>
                            <select
                                wire:model.live="selectedMinute"
                                class="w-28 px-3 py-2 text-sm rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]"
                            >
                                @foreach([0, 15, 30, 45] as $minute)
                                    <option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="sm:text-right">
                        <span class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-[var(--ui-primary)] bg-[var(--ui-primary-10)] rounded-lg border border-[var(--ui-primary)]/20">
                            @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-primary)]')
                            {{ sprintf('%02d:%02d', $selectedHour, $selectedMinute) }} Uhr
                        </span>
                    </div>
                </div>
            </div>

            <!-- Aktuelles Datum Anzeige -->
            @if($selectedDate)
                <div class="pt-4 border-t border-[var(--ui-border)]/60">
                    <div class="flex items-center gap-2 text-sm text-[var(--ui-muted)]">
                        @svg('heroicon-o-calendar-days', 'w-4 h-4')
                        <span>
                            Ausgewählt: 
                            <span class="font-medium text-[var(--ui-secondary)]">
                                {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                                @if($selectedTime)
                                    um {{ $selectedTime }} Uhr
                                @endif
                            </span>
                        </span>
                    </div>
                </div>
            @endif

            <!-- Entfernen Button -->
            @if($task->due_date)
                <div class="pt-4 border-t border-[var(--ui-border)]/60">
                    <x-ui-button 
                        variant="danger-outline" 
                        size="sm" 
                        wire:click="clearDueDate"
                        class="w-full"
                    >
                        <span class="inline-flex items-center gap-2">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            Datum entfernen
                        </span>
                    </x-ui-button>
                </div>
            @endif
        </div>
        
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeDueDateModal">
                    Abbrechen
                </x-ui-button>
                <button 
                    type="button"
                    wire:click="saveDueDate"
                    wire:loading.attr="disabled"
                    wire:target="saveDueDate"
                    wire:disabled="!selectedDate"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary-80)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                >
                    <span wire:loading.remove wire:target="saveDueDate" class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </span>
                    <span wire:loading wire:target="saveDueDate" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Speichern...
                    </span>
                </button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>