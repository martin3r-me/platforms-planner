@php
    $recurrenceLabel = function (string $type, int $interval) {
        $unit = match($type) {
            'daily'   => $interval === 1 ? 'Tag' : 'Tage',
            'weekly'  => $interval === 1 ? 'Woche' : 'Wochen',
            'monthly' => $interval === 1 ? 'Monat' : 'Monate',
            'yearly'  => $interval === 1 ? 'Jahr' : 'Jahre',
            default   => $type,
        };
        return $interval > 1 ? "Alle {$interval} {$unit}" : "Jeden {$unit}";
    };
    $priorityColors = [
        'high'   => 'var(--planner-priority-high)',
        'normal' => 'var(--planner-priority-normal)',
        'low'    => 'var(--planner-priority-low)',
    ];
@endphp

<div class="space-y-4">

    {{-- HEADER --}}
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0 inline-flex items-center gap-2">
                @svg('heroicon-o-arrow-path', 'w-4 h-4 text-[var(--planner-status-active)]')
                Wiederkehrende Aufgaben
            </h3>
            <p class="text-[12px] text-[var(--ui-muted)] mt-0.5 m-0">
                Aufgaben, die automatisch in gewählten Intervallen erstellt werden.
            </p>
        </div>
        @if($project && !$showCreateForm)
            <x-ui-button variant="primary" size="sm" wire:click="openCreateForm">
                @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                <span>Neu</span>
            </x-ui-button>
        @endif
    </div>

    {{-- LISTE --}}
    @if(!$showCreateForm)
        @if(count($recurringTasks) > 0)
            <div class="rounded-xl border border-[var(--ui-border)]/40 bg-white shadow-sm overflow-hidden">
                @foreach($recurringTasks as $i => $rt)
                    @php
                        $isActive = $rt['is_active'];
                        $priority = $rt['priority'] ?? 'normal';
                        $priorityColor = $priorityColors[$priority] ?? 'var(--ui-muted)';
                        $edgeColor = $isActive ? $priorityColor : 'var(--ui-muted)';
                        $nextDue = !empty($rt['next_due_date']) ? \Carbon\Carbon::parse($rt['next_due_date']) : null;
                        $endDate = !empty($rt['recurrence_end_date']) ? \Carbon\Carbon::parse($rt['recurrence_end_date']) : null;
                        $isPaused = !$isActive;
                    @endphp
                    <div class="relative flex items-start gap-3 pl-5 pr-3 py-3 {{ $i > 0 ? 'border-t border-[var(--ui-border)]/30' : '' }} {{ $isPaused ? 'opacity-60' : '' }} hover:bg-[var(--ui-muted-5)] transition-colors group">
                        <span class="absolute top-3 bottom-3 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] m-0 truncate">{{ $rt['title'] }}</h4>
                                @if($isPaused)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-[var(--ui-muted)] text-white uppercase tracking-wider">
                                        @svg('heroicon-o-pause', 'w-2.5 h-2.5')
                                        Pausiert
                                    </span>
                                @endif
                                {{-- Recurrence chip --}}
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)]">
                                    @svg('heroicon-o-arrow-path', 'w-3 h-3')
                                    {{ $recurrenceLabel($rt['recurrence_type'], $rt['recurrence_interval']) }}
                                </span>
                                {{-- Priority chip --}}
                                @if($priority && $priority !== 'normal')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium rounded-full" style="background-color: color-mix(in srgb, {{ $priorityColor }} 14%, white); color: {{ $priorityColor }};">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $priorityColor }};"></span>
                                        {{ ucfirst($priority) }}
                                    </span>
                                @endif
                                @if($rt['story_points'])
                                    <span class="text-[10px] text-[var(--ui-muted)] tabular-nums">{{ strtoupper($rt['story_points']) }}</span>
                                @endif
                            </div>

                            @if($rt['description'])
                                <p class="mt-1 text-[12px] text-[var(--ui-muted)] leading-snug truncate m-0">{{ Str::limit($rt['description'], 140) }}</p>
                            @endif

                            <div class="mt-2 flex items-center flex-wrap gap-x-4 gap-y-1 text-[10px] text-[var(--ui-muted)]">
                                @if($nextDue)
                                    <span class="inline-flex items-center gap-1">
                                        @svg('heroicon-o-clock', 'w-3 h-3 opacity-60')
                                        <span>Nächste: <span class="font-medium text-[var(--ui-secondary)] tabular-nums">{{ $nextDue->format('d.m.Y · H:i') }}</span>
                                        @if($nextDue->isFuture())
                                            <span class="text-[var(--ui-muted)]"> ({{ $nextDue->diffForHumans() }})</span>
                                        @endif
                                        </span>
                                    </span>
                                @endif
                                @if($endDate)
                                    <span class="inline-flex items-center gap-1">
                                        @svg('heroicon-o-calendar', 'w-3 h-3 opacity-60')
                                        Endet: <span class="tabular-nums">{{ $endDate->format('d.m.Y') }}</span>
                                    </span>
                                @endif
                                @if(!empty($rt['auto_delete_old_tasks']))
                                    <span class="inline-flex items-center gap-1" title="Alte Tasks werden vor neuer Erstellung gelöscht">
                                        @svg('heroicon-o-trash', 'w-3 h-3 opacity-60')
                                        Auto-Löschen
                                    </span>
                                @endif
                                @if(!empty($rt['auto_mark_as_done']))
                                    <span class="inline-flex items-center gap-1" title="Neue Tasks werden direkt als erledigt markiert">
                                        @svg('heroicon-s-check', 'w-3 h-3 opacity-60')
                                        Auto-Erledigt
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-0.5 flex-shrink-0">
                            <button
                                wire:click="toggleActive({{ $rt['id'] }})"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-white transition-colors"
                                title="{{ $isActive ? 'Pausieren' : 'Aktivieren' }}"
                            >
                                @if($isActive)
                                    @svg('heroicon-o-pause-circle', 'w-4 h-4')
                                @else
                                    @svg('heroicon-o-play-circle', 'w-4 h-4')
                                @endif
                            </button>
                            <button
                                wire:click="openEditForm({{ $rt['id'] }})"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--planner-status-active)] hover:bg-white transition-colors"
                                title="Bearbeiten"
                            >
                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                            </button>
                            <button
                                wire:click="delete({{ $rt['id'] }})"
                                wire:confirm="Wirklich löschen?"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--planner-status-overdue)] hover:bg-white transition-colors"
                                title="Löschen"
                            >
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-[var(--ui-border)] bg-[var(--ui-muted-5)]/40 p-10 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white border border-[var(--ui-border)]/40 mb-3">
                    @svg('heroicon-o-arrow-path', 'w-6 h-6 text-[var(--ui-muted)]')
                </div>
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] m-0 mb-1">Noch keine wiederkehrenden Aufgaben</h4>
                <p class="text-[12px] text-[var(--ui-muted)] m-0 mb-4 max-w-md mx-auto">
                    Lege eine Vorlage an, die in regelmäßigen Abständen automatisch eine neue Aufgabe in diesem Projekt erstellt.
                </p>
                @if($project)
                    <x-ui-button variant="primary" size="sm" wire:click="openCreateForm">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                        <span>Erste Vorlage anlegen</span>
                    </x-ui-button>
                @endif
            </div>
        @endif
    @endif

    {{-- INLINE-FORM (Card-Stil, gruppiert in Sektionen) --}}
    @if($showCreateForm)
        <div class="rounded-xl border-2 border-[var(--planner-status-active)]/30 bg-white shadow-sm overflow-hidden">
            {{-- Form Header --}}
            <div class="px-5 py-3 border-b border-[var(--ui-border)]/40 bg-[var(--planner-status-active)]/5 flex items-center gap-2">
                @svg($editingId ? 'heroicon-o-pencil-square' : 'heroicon-o-plus-circle', 'w-4 h-4 text-[var(--planner-status-active)]')
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">
                    {{ $editingId ? 'Vorlage bearbeiten' : 'Neue wiederkehrende Aufgabe' }}
                </h4>
                <button
                    type="button"
                    wire:click="closeForm"
                    class="ml-auto inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-white transition-colors"
                    title="Schließen"
                >
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                </button>
            </div>

            <div class="p-5 space-y-5">

                {{-- 1. AUFGABE --}}
                <section>
                    <h5 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 inline-flex items-center gap-1.5">
                        @svg('heroicon-o-document-text', 'w-3 h-3')
                        Aufgabe
                    </h5>
                    <div class="space-y-3">
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
                            label="Beschreibung (optional)"
                            wire:model="form.description"
                            placeholder="Was soll die Aufgabe enthalten?"
                            :errorKey="'form.description'"
                        />
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui-input-select
                                name="form.priority"
                                label="Priorität"
                                wire:model="form.priority"
                                :options="$priorityOptions"
                                :nullable="false"
                                :errorKey="'form.priority'"
                            />
                            <x-ui-input-select
                                name="form.story_points"
                                label="Story Points"
                                wire:model="form.story_points"
                                :options="$storyPointsOptions"
                                :nullable="true"
                                nullLabel="–"
                                :errorKey="'form.story_points'"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui-input-select
                                name="form.user_in_charge_id"
                                label="Verantwortlich"
                                wire:model="form.user_in_charge_id"
                                :options="$teamUsers"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="– Niemand –"
                                :errorKey="'form.user_in_charge_id'"
                            />
                            <x-ui-input-select
                                name="form.project_slot_id"
                                label="Spalte (optional)"
                                wire:model="form.project_slot_id"
                                :options="$projectSlots"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="Backlog"
                                :errorKey="'form.project_slot_id'"
                            />
                        </div>
                        <x-ui-input-text
                            name="form.planned_minutes"
                            label="Geplante Minuten (optional)"
                            type="number"
                            min="0"
                            step="15"
                            wire:model="form.planned_minutes"
                            placeholder="z. B. 480 für 8 Stunden"
                            :errorKey="'form.planned_minutes'"
                        />
                    </div>
                </section>

                {{-- 2. WIEDERHOLUNG --}}
                <section class="pt-5 border-t border-[var(--ui-border)]/40">
                    <h5 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 inline-flex items-center gap-1.5">
                        @svg('heroicon-o-arrow-path', 'w-3 h-3')
                        Wiederholung
                    </h5>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui-input-select
                                name="form.recurrence_type"
                                label="Rhythmus"
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
                                placeholder="1"
                                required
                                :errorKey="'form.recurrence_interval'"
                            />
                        </div>
                        <p class="text-[11px] text-[var(--ui-muted)] -mt-1">
                            Beispiel: Rhythmus „Wöchentlich" + Intervall „2" → alle zwei Wochen
                        </p>
                        <x-ui-input-text
                            name="nextDueDateInput"
                            label="Nächste Fälligkeit"
                            type="datetime-local"
                            wire:model.live="nextDueDateInput"
                            required
                            :errorKey="'form.next_due_date'"
                        />
                        <x-ui-input-text
                            name="recurrenceEndDateInput"
                            label="Endet am (optional)"
                            type="datetime-local"
                            wire:model.live="recurrenceEndDateInput"
                            :errorKey="'form.recurrence_end_date'"
                        />
                    </div>
                </section>

                {{-- 3. OPTIONEN --}}
                <section class="pt-5 border-t border-[var(--ui-border)]/40">
                    <h5 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 inline-flex items-center gap-1.5">
                        @svg('heroicon-o-cog-6-tooth', 'w-3 h-3')
                        Verhalten
                    </h5>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--planner-status-active)]/40 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model="form.is_active"
                                class="mt-0.5 rounded border-[var(--ui-border)] text-[var(--planner-status-active)] focus:ring-[var(--planner-status-active)]/30"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-medium text-[var(--ui-secondary)]">Aktiv</div>
                                <div class="text-[11px] text-[var(--ui-muted)] leading-snug">Wenn deaktiviert, werden keine neuen Aufgaben mehr erstellt — die Vorlage bleibt aber erhalten.</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--planner-status-active)]/40 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model="form.auto_delete_old_tasks"
                                class="mt-0.5 rounded border-[var(--ui-border)] text-[var(--planner-status-active)] focus:ring-[var(--planner-status-active)]/30"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-medium text-[var(--ui-secondary)]">Alte Aufgaben automatisch löschen</div>
                                <div class="text-[11px] text-[var(--ui-muted)] leading-snug">Beim Anlegen der neuen Aufgabe werden alle vorhergehenden Instanzen dieser Vorlage entfernt.</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--planner-status-active)]/40 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model="form.auto_mark_as_done"
                                class="mt-0.5 rounded border-[var(--ui-border)] text-[var(--planner-status-active)] focus:ring-[var(--planner-status-active)]/30"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-medium text-[var(--ui-secondary)]">Sofort als erledigt markieren</div>
                                <div class="text-[11px] text-[var(--ui-muted)] leading-snug">Neue Aufgaben werden direkt im Erledigt-Status erzeugt — nützlich für reine Logbuch-Einträge.</div>
                            </div>
                        </label>
                    </div>
                </section>
            </div>

            {{-- Form Footer --}}
            <div class="px-5 py-3 border-t border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/50 flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeForm">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                    <span>{{ $editingId ? 'Speichern' : 'Anlegen' }}</span>
                </x-ui-button>
            </div>
        </div>
    @endif
</div>
