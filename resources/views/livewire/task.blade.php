@include('planner::partials.planner-tokens')
@php
    $isOverdue = $task->due_date && $task->due_date->isPast() && !$task->is_done;
    $isToday = $task->due_date?->isToday() ?? false;
    $isTomorrow = $task->due_date?->isTomorrow() ?? false;
    $dueDateColor = $isOverdue ? 'var(--planner-status-overdue)' : ($isToday || $isTomorrow ? '#f59e0b' : 'var(--ui-muted)');
    $spValue = is_object($task->story_points) ? $task->story_points->points() : $task->story_points;
    $priorityColor = match($task->priority?->value ?? null) {
        'high' => 'var(--planner-priority-high)',
        'normal' => 'var(--planner-priority-normal)',
        'low' => 'var(--planner-priority-low)',
        default => null,
    };
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_filter([
            ['label' => 'Projekte', 'href' => route('planner.dashboard'), 'icon' => 'clipboard-document-list'],
            ['label' => 'Meine Aufgaben', 'href' => route('planner.my-tasks')],
            $task->project ? ['label' => $task->project->name, 'href' => route('planner.projects.show', ['plannerProject' => $task->project->id])] : null,
            ['label' => Str::limit($task->title, 40)],
        ])">
            <x-slot name="left">
                @if($printingAvailable)
                    <x-ui-button variant="ghost" size="sm" wire:click="printTask()">
                        @svg('heroicon-o-printer', 'w-4 h-4')
                        <span>Drucken</span>
                    </x-ui-button>
                @endif
                @can('update', $task)
                    <x-ui-button variant="ghost" size="sm" wire:click="openMoveModal">
                        @svg('heroicon-o-arrows-right-left', 'w-4 h-4')
                        <span>Verschieben</span>
                    </x-ui-button>
                @endcan
            </x-slot>

            @can('update', $task)
                @if($this->isDirty())
                    <x-ui-button variant="primary" size="sm" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </x-ui-button>
                @endif
            @endcan
            @can('delete', $task)
                <x-ui-confirm-button
                    action="deleteTask"
                    text="Löschen"
                    confirmText="Wirklich löschen?"
                    variant="danger"
                    :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                />
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-0">
        {{-- Hero Title + Status Circle --}}
        <div class="flex items-start gap-4 mb-1">
            {{-- Status Circle (clickable done toggle) --}}
            <button
                type="button"
                wire:click="toggleDone"
                class="flex-shrink-0 mt-1 w-8 h-8 rounded-full border-2 flex items-center justify-center transition-all duration-200
                    {{ $task->is_done
                        ? 'bg-[var(--planner-status-done)] border-[var(--planner-status-done)] text-white'
                        : 'border-[var(--ui-border)] text-transparent hover:border-[var(--planner-status-done)] hover:text-[var(--planner-status-done)]' }}"
                title="{{ $task->is_done ? 'Als offen markieren' : 'Als erledigt markieren' }}"
            >
                @svg('heroicon-s-check', 'w-4 h-4')
            </button>

            <div class="flex-1 min-w-0">
                <x-ui-input-text
                    name="task.title"
                    label=""
                    wire:model.live.debounce.1000ms="task.title"
                    :value="$task->title"
                    placeholder="Aufgabentitel eingeben..."
                    required
                    :errorKey="'task.title'"
                    class="!text-2xl !font-bold !border-none !shadow-none !ring-0 !p-0 !bg-transparent text-[var(--ui-secondary)] tracking-tight"
                />
            </div>
        </div>

        {{-- Context line --}}
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[var(--ui-muted)] mb-6 pl-12">
            @if($task->project)
                <a href="{{ route('planner.projects.show', ['plannerProject' => $task->project->id]) }}" class="hover:text-[var(--planner-status-active)] transition-colors">{{ $task->project->name }}</a>
                <span class="text-[var(--ui-muted)]/40">/</span>
            @endif
            @if($task->created_at)
                <span>{{ $task->created_at->format('d.m.Y') }}</span>
            @endif
            @if($task->user)
                <span class="text-[var(--ui-muted)]/40">/</span>
                <span>von {{ $task->user->fullname ?? $task->user->name }}</span>
            @endif
            @if($task->is_frog)
                <span class="ml-1" title="Frosch">🐸</span>
            @endif
        </div>

        {{-- Two-column layout: Main + Properties Sidebar --}}
        <div class="flex flex-col lg:flex-row gap-8">
            {{-- Main Area --}}
            <div class="flex-1 min-w-0 space-y-8">
                {{-- Anmerkung / Description --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Anmerkung</h2>
                        <span class="text-[10px] text-[var(--ui-muted)] px-1.5 py-0.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded">
                            Verschlüsselt
                        </span>
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
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Definition of Done</h2>
                            <span class="text-[10px] text-[var(--ui-muted)] px-1.5 py-0.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded">
                                Verschlüsselt
                            </span>
                        </div>
                        @if(count($dodItems) > 0)
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium text-[var(--ui-muted)]">
                                    {{ $this->dodProgress['checked'] }}/{{ $this->dodProgress['total'] }}
                                </span>
                                <div class="w-24 h-2 bg-[var(--planner-track)] rounded-full overflow-hidden">
                                    <div
                                        class="h-full transition-all duration-300 rounded-full {{ $this->dodProgress['isComplete'] ? 'bg-[var(--planner-status-done)]' : 'bg-[var(--planner-track-fill)]' }}"
                                        style="width: {{ $this->dodProgress['percentage'] }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- DoD Items --}}
                    <div class="space-y-2">
                        @forelse($dodItems as $index => $item)
                            <div
                                class="group flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--planner-status-active)]/40 transition-all duration-200 {{ $item['checked'] ? 'bg-[var(--planner-card-done)]' : '' }}"
                                wire:key="dod-item-{{ $index }}"
                            >
                                <button
                                    type="button"
                                    wire:click="toggleDodItem({{ $index }})"
                                    class="flex-shrink-0 w-5 h-5 mt-0.5 rounded border-2 transition-all duration-200 flex items-center justify-center {{ $item['checked'] ? 'bg-[var(--planner-status-done)] border-[var(--planner-status-done)] text-white' : 'border-[var(--ui-border)] hover:border-[var(--planner-status-active)]' }}"
                                >
                                    @if($item['checked'])
                                        @svg('heroicon-s-check', 'w-3 h-3')
                                    @endif
                                </button>
                                <div class="flex-1 min-w-0">
                                    <input
                                        type="text"
                                        value="{{ $item['text'] }}"
                                        wire:blur="updateDodItemText({{ $index }}, $event.target.value)"
                                        class="w-full bg-transparent border-none p-0 text-sm focus:ring-0 focus:outline-none {{ $item['checked'] ? 'line-through text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]' }}"
                                        placeholder="DoD-Kriterium eingeben..."
                                    />
                                </div>
                                <button
                                    type="button"
                                    wire:click="removeDodItem({{ $index }})"
                                    wire:confirm="Möchten Sie diesen DoD-Punkt wirklich entfernen?"
                                    class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition-all duration-200"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        @empty
                            <div class="text-center py-6 text-[var(--ui-muted)]">
                                <div class="flex justify-center mb-2">
                                    @svg('heroicon-o-clipboard-document-check', 'w-8 h-8')
                                </div>
                                <p class="text-sm">Noch keine DoD-Kriterien definiert</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Add DoD item --}}
                    <div class="mt-3">
                        <div
                            x-data="{ newDodText: '', isAdding: false }"
                            class="relative"
                        >
                            <template x-if="!isAdding">
                                <button
                                    type="button"
                                    @click="isAdding = true; $nextTick(() => $refs.newDodInput?.focus())"
                                    class="w-full flex items-center gap-2 p-3 rounded-lg border border-dashed border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:border-[var(--planner-status-active)]/60 hover:text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/5 transition-all duration-200"
                                >
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    <span class="text-sm">DoD-Kriterium hinzufügen</span>
                                </button>
                            </template>

                            <template x-if="isAdding">
                                <div class="flex items-center gap-2 p-2 rounded-lg border border-[var(--planner-status-active)]/60 bg-[var(--planner-status-active)]/5">
                                    <input
                                        type="text"
                                        x-ref="newDodInput"
                                        x-model="newDodText"
                                        @keydown.enter.prevent="if(newDodText.trim()) { $wire.addDodItem(newDodText); newDodText = ''; }"
                                        @keydown.escape="isAdding = false; newDodText = ''"
                                        @blur="if(!newDodText.trim()) { isAdding = false; }"
                                        class="flex-1 bg-transparent border-none p-1 text-sm focus:ring-0 focus:outline-none text-[var(--ui-secondary)]"
                                        placeholder="Neues DoD-Kriterium eingeben..."
                                    />
                                    <button
                                        type="button"
                                        @click="if(newDodText.trim()) { $wire.addDodItem(newDodText); newDodText = ''; } isAdding = false;"
                                        class="flex-shrink-0 p-1 rounded text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/10 transition-colors"
                                    >
                                        @svg('heroicon-o-check', 'w-5 h-5')
                                    </button>
                                    <button
                                        type="button"
                                        @click="isAdding = false; newDodText = ''"
                                        class="flex-shrink-0 p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                                    >
                                        @svg('heroicon-o-x-mark', 'w-5 h-5')
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Extra Fields --}}
                <x-core-extra-fields-section
                    :definitions="$this->extraFieldDefinitions"
                    :model="$task"
                />
            </div>

            {{-- Properties Sidebar (right) --}}
            <div class="lg:w-72 flex-shrink-0">
                <div class="lg:sticky lg:top-4 space-y-1">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-2">Eigenschaften</h3>

                    {{-- Status --}}
                    <button type="button" wire:click="toggleDone" class="w-full flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors group">
                        <span class="text-xs text-[var(--ui-muted)]">Status</span>
                        <span class="flex items-center gap-1.5 text-xs font-medium">
                            <span class="w-2 h-2 rounded-full {{ $task->is_done ? 'bg-[var(--planner-status-done)]' : 'bg-[var(--planner-status-active)]' }}"></span>
                            <span class="text-[var(--ui-secondary)]">{{ $task->is_done ? 'Erledigt' : 'Offen' }}</span>
                        </span>
                    </button>

                    {{-- Priority --}}
                    <div class="py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs text-[var(--ui-muted)]">Priorität</span>
                            @if($priorityColor)
                                <span class="w-2 h-2 rounded-full" style="background-color: {{ $priorityColor }}"></span>
                            @endif
                        </div>
                        <x-ui-input-select
                            name="task.priority"
                            label=""
                            :options="\Platform\Planner\Enums\TaskPriority::cases()"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="false"
                            wire:model.live="task.priority"
                        />
                    </div>

                    {{-- Assignee --}}
                    <div class="py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <span class="text-xs text-[var(--ui-muted)] block mb-1.5">Verantwortlich</span>
                        <x-ui-input-select
                            name="task.user_in_charge_id"
                            label=""
                            :options="$teamUsers"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Niemand –"
                            wire:model.live="task.user_in_charge_id"
                        />
                    </div>

                    {{-- Due Date --}}
                    <button type="button" wire:click="openDueDateModal" class="w-full flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors group">
                        <span class="text-xs text-[var(--ui-muted)]">Fällig</span>
                        <span class="text-xs font-medium" style="color: {{ $dueDateColor }}">
                            @if($task->due_date)
                                {{ $task->due_date->format('d.m.Y H:i') }}
                            @else
                                <span class="text-[var(--ui-muted)]/50">Kein Datum</span>
                            @endif
                        </span>
                    </button>

                    {{-- Story Points --}}
                    <div class="py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <span class="text-xs text-[var(--ui-muted)] block mb-1.5">Story Points</span>
                        <x-ui-input-select
                            name="task.story_points"
                            label=""
                            :options="\Platform\Planner\Enums\TaskStoryPoints::cases()"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="true"
                            nullLabel="–"
                            wire:model.live="task.story_points"
                        />
                    </div>

                    {{-- Frog --}}
                    <button type="button" wire:click="toggleFrog" class="w-full flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors group">
                        <span class="text-xs text-[var(--ui-muted)]">Frosch</span>
                        <span class="text-xs font-medium text-[var(--ui-secondary)]">
                            @if($task->is_frog)
                                🐸 Ja
                            @else
                                Nein
                            @endif
                        </span>
                    </button>

                    {{-- Separator --}}
                    <div class="border-t border-[var(--ui-border)]/40 my-2"></div>

                    {{-- Metadata (read-only) --}}
                    @if($task->team)
                        <div class="flex items-center justify-between py-1.5 px-3">
                            <span class="text-[10px] text-[var(--ui-muted)]">Team</span>
                            <span class="text-[10px] text-[var(--ui-secondary)]">{{ $task->team->name }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between py-1.5 px-3">
                        <span class="text-[10px] text-[var(--ui-muted)]">Erstellt</span>
                        <span class="text-[10px] text-[var(--ui-secondary)]">{{ $task->created_at->format('d.m.Y') }}</span>
                    </div>
                    @if(($task->postpone_count ?? 0) > 0)
                        <div class="flex items-center justify-between py-1.5 px-3">
                            <span class="text-[10px] text-[var(--ui-muted)]">Verschoben</span>
                            <span class="text-[10px] text-[var(--ui-secondary)]">{{ $task->postpone_count }}×</span>
                        </div>
                    @endif
                    @if($task->original_due_date)
                        <div class="flex items-center justify-between py-1.5 px-3">
                            <span class="text-[10px] text-[var(--ui-muted)]">Ursprünglich</span>
                            <span class="text-[10px] text-[var(--ui-secondary)]">{{ $task->original_due_date->format('d.m.Y') }}</span>
                        </div>
                    @endif
                    @if($this->contextFileCount > 0)
                        <div class="flex items-center justify-between py-1.5 px-3">
                            <span class="text-[10px] text-[var(--ui-muted)]">Anhänge</span>
                            <span class="text-[10px] text-[var(--ui-secondary)]">{{ $this->contextFileCount }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </x-ui-page-container>

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

    <!-- Print Modal -->
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
                        <span class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-[var(--planner-status-active)] bg-[var(--planner-status-active)]/10 rounded-lg border border-[var(--planner-status-active)]/20">
                            @svg('heroicon-o-clock', 'w-4 h-4')
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
