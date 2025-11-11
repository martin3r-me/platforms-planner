<div class="space-y-4">
    {{-- Header mit Button --}}
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Wiederkehrende Aufgaben</h3>
        @if($project)
            <x-ui-button variant="success" size="sm" wire:click="openCreateForm">
                <span class="inline-flex items-center gap-2">
                    @svg('heroicon-o-plus','w-4 h-4')
                    <span>Neu</span>
                </span>
            </x-ui-button>
        @endif
    </div>

    {{-- Liste der wiederkehrenden Aufgaben --}}
    @if(count($recurringTasks) > 0)
        <div class="space-y-2">
            @foreach($recurringTasks as $rt)
                <div class="p-4 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] hover:bg-[var(--ui-muted-5)] transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <h4 class="font-medium text-[var(--ui-secondary)]">{{ $rt['title'] }}</h4>
                                @if(!$rt['is_active'])
                                    <span class="px-2 py-0.5 text-xs bg-gray-200 text-gray-600 rounded">Inaktiv</span>
                                @endif
                            </div>
                            
                            @if($rt['description'])
                                <p class="text-sm text-[var(--ui-muted)] mb-2">{{ Str::limit($rt['description'], 100) }}</p>
                            @endif

                            <div class="flex flex-wrap gap-4 text-xs text-[var(--ui-muted)]">
                                @if($rt['story_points'])
                                    <span>Story Points: <strong>{{ strtoupper($rt['story_points']) }}</strong></span>
                                @endif
                                @if($rt['priority'])
                                    <span>Priorität: <strong>{{ ucfirst($rt['priority']) }}</strong></span>
                                @endif
                                @if($rt['recurrence_type'])
                                    <span>
                                        Wiederholung: <strong>
                                            @if($rt['recurrence_interval'] > 1)
                                                Alle {{ $rt['recurrence_interval'] }} 
                                            @endif
                                            {{ match($rt['recurrence_type']) {
                                                'daily' => 'Tag(e)',
                                                'weekly' => 'Woche(n)',
                                                'monthly' => 'Monat(e)',
                                                'yearly' => 'Jahr(e)',
                                                default => $rt['recurrence_type']
                                            } }}
                                        </strong>
                                    </span>
                                @endif
                                @if($rt['next_due_date'])
                                    <span>Nächste Aufgabe: <strong>{{ \Carbon\Carbon::parse($rt['next_due_date'])->format('d.m.Y H:i') }}</strong></span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 ml-4">
                            <button 
                                wire:click="toggleActive({{ $rt['id'] }})"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="{{ $rt['is_active'] ? 'Deaktivieren' : 'Aktivieren' }}"
                            >
                                @if($rt['is_active'])
                                    @svg('heroicon-o-eye','w-5 h-5')
                                @else
                                    @svg('heroicon-o-eye-slash','w-5 h-5')
                                @endif
                            </button>
                            <button 
                                wire:click="openEditForm({{ $rt['id'] }})"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                                title="Bearbeiten"
                            >
                                @svg('heroicon-o-pencil','w-5 h-5')
                            </button>
                            <button 
                                wire:click="delete({{ $rt['id'] }})"
                                wire:confirm="Wirklich löschen?"
                                class="text-red-500 hover:text-red-700 transition-colors"
                                title="Löschen"
                            >
                                @svg('heroicon-o-trash','w-5 h-5')
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-8 text-center border border-[var(--ui-border)] rounded-lg bg-[var(--ui-muted-5)]">
            <p class="text-[var(--ui-muted)]">Noch keine wiederkehrenden Aufgaben vorhanden.</p>
            @if($project)
                <x-ui-button variant="secondary" size="sm" wire:click="openCreateForm" class="mt-4">
                    Erste Aufgabe erstellen
                </x-ui-button>
            @endif
        </div>
    @endif

    {{-- Inline Formular --}}
    @if($showCreateForm)
        <div class="mt-6 p-6 rounded-lg border-2 border-[var(--ui-primary)] bg-[var(--ui-surface)]">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold text-[var(--ui-secondary)]">
                    {{ $editingId ? 'Wiederkehrende Aufgabe bearbeiten' : 'Neue wiederkehrende Aufgabe' }}
                </h4>
                <button 
                    wire:click="closeForm"
                    class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                    title="Schließen"
                >
                    @svg('heroicon-o-x-mark','w-5 h-5')
                </button>
            </div>
            
            <x-ui-form-grid :cols="1" :gap="4">
                <x-ui-input-text 
                    name="form.title"
                    label="Titel"
                    wire:model="form.title"
                    placeholder="Titel der Aufgabe..."
                    required
                    :errorKey="'form.title'"
                />

                <x-ui-input-textarea 
                    name="form.description"
                    label="Beschreibung"
                    wire:model="form.description"
                    placeholder="Beschreibung der Aufgabe..."
                    :errorKey="'form.description'"
                />

                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-select
                        name="form.story_points"
                        label="Story Points"
                        wire:model="form.story_points"
                        :options="$storyPointsOptions"
                        :nullable="true"
                        :errorKey="'form.story_points'"
                    />

                    <x-ui-input-select
                        name="form.priority"
                        label="Priorität"
                        wire:model="form.priority"
                        :options="$priorityOptions"
                        :nullable="false"
                        :errorKey="'form.priority'"
                    />
                </div>

                <x-ui-input-text 
                    name="form.planned_minutes"
                    label="Geplante Minuten"
                    type="number"
                    min="0"
                    step="15"
                    wire:model="form.planned_minutes"
                    placeholder="z. B. 480 für 8 Stunden"
                    :errorKey="'form.planned_minutes'"
                />

                <x-ui-input-select
                    name="form.user_in_charge_id"
                    label="Verantwortlicher"
                    wire:model="form.user_in_charge_id"
                    :options="$teamUsers"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    :errorKey="'form.user_in_charge_id'"
                />

                <x-ui-input-select
                    name="form.project_slot_id"
                    label="Projekt Slot (optional)"
                    wire:model="form.project_slot_id"
                    :options="$projectSlots"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    :errorKey="'form.project_slot_id'"
                />

                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-select
                        name="form.recurrence_type"
                        label="Wiederholungstyp"
                        wire:model="form.recurrence_type"
                        :options="$recurrenceTypes"
                        :nullable="false"
                        :errorKey="'form.recurrence_type'"
                    />

                    <x-ui-input-text 
                        name="form.recurrence_interval"
                        label="Intervall"
                        type="number"
                        min="1"
                        wire:model="form.recurrence_interval"
                        placeholder="z. B. 1 = jede Woche, 2 = alle 2 Wochen"
                        required
                        :errorKey="'form.recurrence_interval'"
                    />
                </div>

                <x-ui-input-text 
                    name="recurrenceEndDateInput"
                    label="Enddatum (optional)"
                    type="datetime-local"
                    wire:model.live="recurrenceEndDateInput"
                    :errorKey="'form.recurrence_end_date'"
                />

                <x-ui-input-text 
                    name="nextDueDateInput"
                    label="Nächstes Fälligkeitsdatum"
                    type="datetime-local"
                    wire:model.live="nextDueDateInput"
                    required
                    :errorKey="'form.next_due_date'"
                />

                <div class="flex items-center gap-2">
                    <input 
                        type="checkbox" 
                        id="form.is_active"
                        wire:model="form.is_active"
                        class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                    />
                    <label for="form.is_active" class="text-sm text-[var(--ui-body-color)]">
                        Aktiv
                    </label>
                </div>
            </x-ui-form-grid>

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button variant="secondary" wire:click="closeForm">Abbrechen</x-ui-button>
                <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
            </div>
        </div>
    @endif
</div>

