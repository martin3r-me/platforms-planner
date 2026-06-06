@php
    $recurrenceLabel = function (array $rt) {
        $type = $rt['recurrence_type'] ?? 'daily';
        $interval = (int) ($rt['recurrence_interval'] ?? 1);

        // Spezialmuster zuerst
        if ($type === 'monthly' && !empty($rt['monthly_pattern'])) {
            if ($rt['monthly_pattern'] === 'day_of_month') {
                $day = (int) ($rt['monthly_day_of_month'] ?? 1);
                $dayLabel = $day === -1 ? 'letzten Tag' : "{$day}.";
                return $interval > 1 ? "Alle {$interval} Monate am {$dayLabel}" : "Jeden Monat am {$dayLabel}";
            }
            if ($rt['monthly_pattern'] === 'ordinal_weekday') {
                $ordinals = [1 => 'ersten', 2 => 'zweiten', 3 => 'dritten', 4 => 'vierten', -1 => 'letzten'];
                $weekdays = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
                $ord = $ordinals[(int) ($rt['monthly_ordinal'] ?? 1)] ?? 'ersten';
                $wd  = $weekdays[(int) ($rt['monthly_weekday'] ?? 0)] ?? 'Montag';
                return $interval > 1
                    ? "Alle {$interval} Monate am {$ord} {$wd}"
                    : "Jeden {$ord} {$wd} im Monat";
            }
        }

        if (in_array($type, ['daily','weekly']) && !empty($rt['weekday_mask'])) {
            $mask = (int) $rt['weekday_mask'];
            $labels = ['Mo','Di','Mi','Do','Fr','Sa','So'];
            $picked = [];
            foreach ([0,1,2,3,4,5,6] as $iso) {
                if (($mask & (1 << $iso)) !== 0) $picked[] = $labels[$iso];
            }
            return count($picked) > 0 ? implode(' · ', $picked) : 'Wochentage';
        }

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
                                    {{ $recurrenceLabel($rt) }}
                                </span>
                                @if(!empty($rt['lead_time_days']))
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]" title="Vorlauf">
                                        @svg('heroicon-o-clock', 'w-2.5 h-2.5 opacity-60')
                                        {{ $rt['lead_time_days'] }}d Vorlauf
                                    </span>
                                @endif
                                @if(!empty($rt['chain_on_complete']))
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]" title="Bei Erledigen kommt sofort die nächste">
                                        @svg('heroicon-o-link', 'w-2.5 h-2.5 opacity-60')
                                        Chain
                                    </span>
                                @endif
                                @if(!empty($rt['skip_weekends']))
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]" title="Sa/So werden auf Montag verschoben">
                                        @svg('heroicon-o-calendar', 'w-2.5 h-2.5 opacity-60')
                                        Wochentage
                                    </span>
                                @endif
                                @if(!empty($rt['max_occurrences']))
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] tabular-nums" title="Limit">
                                        {{ (int) ($rt['occurrences_count'] ?? 0) }} / {{ $rt['max_occurrences'] }}
                                    </span>
                                @endif
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

                    {{-- Typ + Intervall --}}
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui-input-select
                            name="form.recurrence_type"
                            label="Rhythmus"
                            wire:model.live="form.recurrence_type"
                            :options="$recurrenceTypes"
                            :nullable="false"
                            :errorKey="'form.recurrence_type'"
                        />
                        <x-ui-input-text
                            name="form.recurrence_interval"
                            label="Intervall"
                            type="number"
                            min="1"
                            wire:model.live="form.recurrence_interval"
                            placeholder="1"
                            required
                            :errorKey="'form.recurrence_interval'"
                        />
                    </div>
                    <p class="mt-1 text-[11px] text-[var(--ui-muted)]">
                        Beispiel: „Wöchentlich" + Intervall „2" → alle zwei Wochen
                    </p>

                    {{-- Wochentag-Maske (für daily + weekly) --}}
                    @if(in_array($form['recurrence_type'], ['daily', 'weekly']))
                        @php
                            $weekdays = [
                                0 => 'Mo', 1 => 'Di', 2 => 'Mi', 3 => 'Do', 4 => 'Fr', 5 => 'Sa', 6 => 'So',
                            ];
                            $weekdayBits = [0=>1,1=>2,2=>4,3=>8,4=>16,5=>32,6=>64];
                            $mask = (int) ($form['weekday_mask'] ?? 0);
                        @endphp
                        <div class="mt-3">
                            <label class="block text-[11px] font-medium text-[var(--ui-secondary)] mb-1.5">
                                Wochentage
                                <span class="text-[10px] text-[var(--ui-muted)] font-normal">— optional, leer = alle</span>
                            </label>
                            <div class="flex flex-wrap gap-1 mb-1.5">
                                @foreach($weekdays as $iso => $label)
                                    @php $active = ($mask & $weekdayBits[$iso]) !== 0; @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleWeekday({{ $iso }})"
                                        class="inline-flex items-center justify-center w-9 h-7 text-[11px] font-semibold rounded-md transition-colors {{ $active
                                            ? 'bg-[var(--planner-status-active)] text-white'
                                            : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}"
                                    >{{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <button type="button" wire:click="applyWeekdayPreset('workdays')" class="text-[10px] text-[var(--planner-status-active)] hover:underline">Werktage</button>
                                <span class="text-[10px] text-[var(--ui-muted)]">·</span>
                                <button type="button" wire:click="applyWeekdayPreset('weekend')" class="text-[10px] text-[var(--planner-status-active)] hover:underline">Wochenende</button>
                                <span class="text-[10px] text-[var(--ui-muted)]">·</span>
                                <button type="button" wire:click="applyWeekdayPreset('all')" class="text-[10px] text-[var(--planner-status-active)] hover:underline">Alle</button>
                                <span class="text-[10px] text-[var(--ui-muted)]">·</span>
                                <button type="button" wire:click="applyWeekdayPreset('')" class="text-[10px] text-[var(--ui-muted)] hover:underline">Leer</button>
                            </div>
                        </div>
                    @endif

                    {{-- Monatsmuster --}}
                    @if($form['recurrence_type'] === 'monthly')
                        <div class="mt-3">
                            <label class="block text-[11px] font-medium text-[var(--ui-secondary)] mb-1.5">Monatsmuster</label>
                            <div class="inline-flex rounded-md border border-[var(--ui-border)] overflow-hidden mb-2">
                                <button
                                    type="button"
                                    wire:click="setMonthlyPattern(null)"
                                    class="inline-flex items-center px-2.5 h-7 text-[11px] font-medium transition-colors {{ empty($form['monthly_pattern']) ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >Gleicher Tag</button>
                                <button
                                    type="button"
                                    wire:click="setMonthlyPattern('day_of_month')"
                                    class="inline-flex items-center px-2.5 h-7 text-[11px] font-medium border-l border-[var(--ui-border)] transition-colors {{ ($form['monthly_pattern'] ?? '') === 'day_of_month' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >Fester Tag</button>
                                <button
                                    type="button"
                                    wire:click="setMonthlyPattern('ordinal_weekday')"
                                    class="inline-flex items-center px-2.5 h-7 text-[11px] font-medium border-l border-[var(--ui-border)] transition-colors {{ ($form['monthly_pattern'] ?? '') === 'ordinal_weekday' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >N-ter Wochentag</button>
                            </div>

                            @if(($form['monthly_pattern'] ?? '') === 'day_of_month')
                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui-input-text
                                        name="form.monthly_day_of_month"
                                        label="Tag im Monat (1–31, −1 = letzter)"
                                        type="number"
                                        min="-1"
                                        max="31"
                                        wire:model.live="form.monthly_day_of_month"
                                        placeholder="z. B. 5 oder -1"
                                    />
                                </div>
                                <p class="mt-1 text-[10px] text-[var(--ui-muted)]">
                                    Wenn der Monat den Tag nicht hat (z. B. 31. Februar), wird der letzte Tag verwendet.
                                </p>
                            @elseif(($form['monthly_pattern'] ?? '') === 'ordinal_weekday')
                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui-input-select
                                        name="form.monthly_ordinal"
                                        label="Welcher"
                                        wire:model.live="form.monthly_ordinal"
                                        :options="collect([
                                            ['value' => 1, 'label' => 'Erster'],
                                            ['value' => 2, 'label' => 'Zweiter'],
                                            ['value' => 3, 'label' => 'Dritter'],
                                            ['value' => 4, 'label' => 'Vierter'],
                                            ['value' => -1, 'label' => 'Letzter'],
                                        ])"
                                        :nullable="false"
                                    />
                                    <x-ui-input-select
                                        name="form.monthly_weekday"
                                        label="Wochentag"
                                        wire:model.live="form.monthly_weekday"
                                        :options="collect([
                                            ['value' => 0, 'label' => 'Montag'],
                                            ['value' => 1, 'label' => 'Dienstag'],
                                            ['value' => 2, 'label' => 'Mittwoch'],
                                            ['value' => 3, 'label' => 'Donnerstag'],
                                            ['value' => 4, 'label' => 'Freitag'],
                                            ['value' => 5, 'label' => 'Samstag'],
                                            ['value' => 6, 'label' => 'Sonntag'],
                                        ])"
                                        :nullable="false"
                                    />
                                </div>
                                <p class="mt-1 text-[10px] text-[var(--ui-muted)]">
                                    Beispiel: „Erster Montag" oder „Letzter Freitag" — gilt jeden Monat.
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- Wochenend-Skip --}}
                    @if(in_array($form['recurrence_type'], ['daily', 'weekly', 'monthly', 'yearly']))
                        <label class="flex items-center gap-2 mt-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model.live="form.skip_weekends"
                                class="rounded border-[var(--ui-border)] text-[var(--planner-status-active)] focus:ring-[var(--planner-status-active)]/30"
                            />
                            <span class="text-[11px] text-[var(--ui-secondary)]">
                                Wochenend-Termine auf nächsten Montag verschieben
                            </span>
                        </label>
                    @endif

                    {{-- Datumsangaben --}}
                    <div class="mt-3 space-y-3">
                        <x-ui-input-text
                            name="nextDueDateInput"
                            label="Nächste Fälligkeit"
                            type="datetime-local"
                            wire:model.live="nextDueDateInput"
                            required
                            :errorKey="'form.next_due_date'"
                        />
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui-input-text
                                name="recurrenceEndDateInput"
                                label="Endet am (optional)"
                                type="datetime-local"
                                wire:model.live="recurrenceEndDateInput"
                                :errorKey="'form.recurrence_end_date'"
                            />
                            <x-ui-input-text
                                name="form.max_occurrences"
                                label="Max. Wiederholungen (optional)"
                                type="number"
                                min="1"
                                wire:model.live="form.max_occurrences"
                                placeholder="∞"
                                :errorKey="'form.max_occurrences'"
                            />
                        </div>
                    </div>

                    {{-- LIVE-VORSCHAU --}}
                    @php $preview = $this->previewOccurrences; @endphp
                    @if(!empty($preview))
                        <div class="mt-4 p-3 rounded-lg border border-[var(--planner-status-active)]/20 bg-[var(--planner-status-active)]/5">
                            <div class="flex items-center gap-1.5 mb-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--planner-status-active)]">
                                @svg('heroicon-o-eye', 'w-3 h-3')
                                Nächste 3 Termine
                            </div>
                            <ul class="space-y-1.5">
                                @foreach($preview as $i => $date)
                                    @php
                                        $weekdayLabel = ['Mo','Di','Mi','Do','Fr','Sa','So'][($date->dayOfWeek + 6) % 7];
                                    @endphp
                                    <li class="flex items-center gap-2 text-[11px]">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--planner-status-active)] text-white text-[9px] font-bold tabular-nums">{{ $i + 1 }}</span>
                                        <span class="text-[var(--ui-secondary)] font-medium tabular-nums">{{ $weekdayLabel }}, {{ $date->format('d.m.Y · H:i') }}</span>
                                        <span class="text-[var(--ui-muted)] ml-auto">{{ $date->diffForHumans() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                {{-- 3. VERHALTEN --}}
                <section class="pt-5 border-t border-[var(--ui-border)]/40">
                    <h5 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 inline-flex items-center gap-1.5">
                        @svg('heroicon-o-cog-6-tooth', 'w-3 h-3')
                        Verhalten
                    </h5>

                    {{-- Vorlauf --}}
                    <div class="mb-3">
                        <x-ui-input-text
                            name="form.lead_time_days"
                            label="Vorlauf"
                            type="number"
                            min="0"
                            max="365"
                            wire:model.live="form.lead_time_days"
                            placeholder="0 = erst am Fälligkeitstag"
                            :errorKey="'form.lead_time_days'"
                        />
                        <p class="mt-1 text-[10px] text-[var(--ui-muted)]">
                            Tage vor der Fälligkeit, an denen die Aufgabe automatisch angelegt wird (0 = exakt am Tag).
                        </p>
                    </div>

                    {{-- Toggle-Cards --}}
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--planner-status-active)]/40 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="form.is_active"
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
                                wire:model.live="form.chain_on_complete"
                                class="mt-0.5 rounded border-[var(--ui-border)] text-[var(--planner-status-active)] focus:ring-[var(--planner-status-active)]/30"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-medium text-[var(--ui-secondary)]">Bei Erledigen sofort nächste anlegen</div>
                                <div class="text-[11px] text-[var(--ui-muted)] leading-snug">
                                    Wenn die zuletzt erzeugte Instanz erledigt oder gelöscht wird, springt die nächste Fälligkeit unmittelbar als neue Aufgabe in den Backlog — ohne auf den Cron zu warten.
                                </div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--planner-status-active)]/40 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="form.auto_delete_old_tasks"
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
                                wire:model.live="form.auto_mark_as_done"
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
