<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Spalten-Einstellungen</h3>
                <p class="text-sm text-gray-500">Spalte bearbeiten und verwalten</p>
            </div>
        </div>
    </x-slot>

    @if($projectSlot)
        <div class="space-y-6">
            <x-ui-input-text
                name="projectSlot.name"
                label="Spaltenname"
                wire:model="projectSlot.name"
                placeholder="Name der Spalte eingeben"
                errorKey="projectSlot.label"
            />

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Spaltenfarbe</label>
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
                            {{ empty($projectSlot->color) ? 'border-[var(--ui-primary)] ring-2 ring-[var(--ui-primary)]/30' : 'border-[var(--ui-border)] hover:border-[var(--ui-primary)]/60' }}"
                        title="Automatisch (Position)"
                    >
                        <span class="text-[10px] text-[var(--ui-muted)] font-semibold">A</span>
                    </button>
                    @foreach($toneOptions as $opt)
                        <button
                            type="button"
                            wire:click="$set('projectSlot.color', '{{ $opt['key'] }}')"
                            class="w-8 h-8 rounded-full border-2 transition-all
                                {{ ($projectSlot->color ?? null) === $opt['key']
                                    ? 'border-[var(--ui-primary)] ring-2 ring-[var(--ui-primary)]/30 scale-110'
                                    : 'border-white shadow-sm hover:scale-110' }}"
                            style="background-color: {{ $opt['color'] }};"
                            title="{{ $opt['label'] }}"
                        ></button>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-[var(--ui-muted)]">„A" = automatische Farbvergabe nach Spaltenposition.</p>
            </div>

            <div class="pt-4 border-t border-gray-200">
                <x-ui-confirm-button
                    action="deleteProjectSlot"
                    text="Spalte löschen"
                    confirmText="Wirklich löschen?"
                    variant="danger"
                />
            </div>
        </div>
    @endif
    
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-ui-button variant="secondary" wire:click="closeModal">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
