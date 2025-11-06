<div class="space-y-6">
    <div class="rounded-lg border border-[var(--ui-border)]/60 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Zeiterfassung</h3>
                <p class="text-sm text-[var(--ui-muted)]">Arbeitszeit für dieses Projekt erfassen und planen.</p>
            </div>

            <div class="flex flex-col items-end gap-1 text-sm">
                <span class="text-[var(--ui-secondary)]">
                    <strong>{{ number_format($this->totalMinutes / 60, 2, ',', '.') }} h</strong>
                    erfasst
                </span>
                @if($project->planned_minutes)
                    <span class="text-[var(--ui-muted)]">
                        Plan: {{ number_format($project->planned_minutes / 60, 2, ',', '.') }} h
                    </span>
                @endif
                @if($this->totalAmountCents)
                    <span class="text-[var(--ui-secondary)]">
                        Wert: {{ number_format($this->totalAmountCents / 100, 2, ',', '.') }}&nbsp;€
                    </span>
                @endif
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-[var(--ui-muted)]">Datum</label>
                <input
                    type="date"
                    wire:model.live="workDate"
                    class="w-full rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20"
                />
                @error('workDate')
                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[var(--ui-muted)]">Dauer</label>
                <select
                    wire:model.live="minutes"
                    class="w-full rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20"
                >
                    @foreach($this->minuteOptions as $option)
                        <option value="{{ $option }}">{{ number_format($option / 60, 2, ',', '.') }} h</option>
                    @endforeach
                </select>
                @error('minutes')
                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[var(--ui-muted)]">Stundensatz (optional)</label>
                <input
                    type="text"
                    inputmode="decimal"
                    placeholder="z. B. 120,00"
                    wire:model.live="rate"
                    class="w-full rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20"
                />
                @error('rate')
                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1 flex items-end">
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--ui-primary)] px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:bg-[var(--ui-primary-80)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove>Speichern</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Speichern…
                    </span>
                </button>
            </div>
        </div>

        <div class="mt-4">
            <label class="mb-1 block text-xs font-medium text-[var(--ui-muted)]">Notiz</label>
            <textarea
                wire:model.live="note"
                rows="2"
                class="w-full rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20"
                placeholder="Optionaler Kommentar"
            ></textarea>
            @error('note')
                <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="rounded-lg border border-[var(--ui-border)]/60 bg-white shadow-sm">
        <div class="border-b border-[var(--ui-border)]/60 px-6 py-3">
            <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Letzte Buchungen</h4>
        </div>

        <div class="divide-y divide-[var(--ui-border)]/40">
            @forelse($this->entries as $entry)
                <div class="flex flex-col gap-2 px-6 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-1">
                        <span class="font-medium text-[var(--ui-secondary)]">{{ $entry->work_date?->format('d.m.Y') }}</span>
                        @if($entry->note)
                            <span class="text-[var(--ui-muted)]">{{ $entry->note }}</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-[var(--ui-secondary)]">
                        <span>{{ number_format($entry->minutes / 60, 2, ',', '.') }} h</span>
                        @if($entry->amount_cents)
                            <span>{{ number_format($entry->amount_cents / 100, 2, ',', '.') }}&nbsp;€</span>
                        @elseif($entry->rate_cents)
                            <span>{{ number_format($entry->rate_cents / 100, 2, ',', '.') }}&nbsp;€/h</span>
                        @endif
                        <span class="text-[var(--ui-muted)]">{{ $entry->user?->name }}</span>
                    </div>
                </div>
            @empty
                <div class="px-6 py-6 text-sm text-[var(--ui-muted)]">Noch keine Zeiten erfasst.</div>
            @endforelse
        </div>
    </div>
</div>

