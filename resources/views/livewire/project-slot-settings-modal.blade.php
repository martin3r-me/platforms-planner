<x-nx-modal size="md" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--nx-accent)]/10 flex-shrink-0">
                @svg('heroicon-o-view-columns', 'w-5 h-5 text-[var(--nx-accent)]')
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 leading-tight">Spalten-Einstellungen</h3>
                <p class="text-[12px] text-[var(--nx-muted)] m-0 mt-0.5">Spalte bearbeiten und Farbe wählen</p>
            </div>
        </div>
    </x-slot>

    @if($projectSlot)
        <div class="space-y-6">
            <x-nx-input-text
                name="projectSlot.name"
                label="Spaltenname"
                wire:model="projectSlot.name"
                placeholder="Name der Spalte eingeben"
                errorKey="projectSlot.label"
            />

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Spaltenfarbe</label>
                @php
                    $toneOptions = [
                        ['key' => 'indigo',  'color' => '#6366f1', 'label' => 'Indigo'],
                        ['key' => 'sky',     'color' => '#0ea5e9', 'label' => 'Sky'],
                        ['key' => 'teal',    'color' => '#06b6d4', 'label' => 'Teal'],
                        ['key' => 'emerald', 'color' => '#10b981', 'label' => 'Emerald'],
                        ['key' => 'amber',   'color' => '#f59e0b', 'label' => 'Amber'],
                        ['key' => 'rose',    'color' => '#ef4444', 'label' => 'Rose'],
                        ['key' => 'pink',    'color' => '#ec4899', 'label' => 'Pink'],
                        ['key' => 'violet',  'color' => '#8b5cf6', 'label' => 'Violet'],
                        ['key' => 'slate',   'color' => '#94a3b8', 'label' => 'Slate'],
                    ];
                @endphp
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="$set('projectSlot.color', null)"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full border-2 transition-all
                            {{ empty($projectSlot->color) ? 'border-[var(--nx-accent)] ring-2 ring-[var(--nx-accent)]/30' : 'border-[color:var(--nx-line)] hover:border-[var(--nx-accent)]/60' }}"
                        title="Automatisch (Position)"
                    >
                        <span class="text-[10px] text-[var(--nx-muted)] font-semibold">A</span>
                    </button>
                    @foreach($toneOptions as $opt)
                        <button
                            type="button"
                            wire:click="$set('projectSlot.color', '{{ $opt['key'] }}')"
                            class="w-8 h-8 rounded-full border-2 transition-all
                                {{ ($projectSlot->color ?? null) === $opt['key']
                                    ? 'border-[var(--nx-accent)] ring-2 ring-[var(--nx-accent)]/30 scale-110'
                                    : 'border-white shadow-[var(--nx-shadow-card)] hover:scale-110' }}"
                            style="background-color: {{ $opt['color'] }};"
                            title="{{ $opt['label'] }}"
                        ></button>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-[var(--nx-muted)]">„A" = automatische Farbvergabe nach Spaltenposition.</p>
            </div>

            <div class="pt-4 border-t border-[color:var(--nx-line)]">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="projectSlot.blocked_until_previous_done"
                        class="mt-0.5 h-4 w-4 rounded border-[color:var(--nx-line)] text-[var(--nx-accent)] focus:ring-[var(--nx-accent)]/30"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-[var(--nx-text)]">Erst nach vorherigen Spalten</span>
                        <span class="block text-[11px] text-[var(--nx-muted)] mt-0.5">
                            Aufgaben dieser Spalte werden vom Agenten erst geholt, wenn alle Aufgaben in
                            links davorliegenden Spalten erledigt oder verworfen sind (Phasen-Sequenz).
                        </span>
                    </span>
                </label>
            </div>

            <div class="pt-4 border-t border-[color:var(--nx-line)]">
                <x-nx-button variant="danger" size="sm" wire:click="deleteProjectSlot" wire:confirm="Wirklich löschen?">Spalte löschen</x-nx-button>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <x-nx-button variant="secondary" size="sm" wire:click="closeModal">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" size="sm" wire:click="save">
                @svg('heroicon-o-check', 'w-3.5 h-3.5')
                <span>Speichern</span>
            </x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>
