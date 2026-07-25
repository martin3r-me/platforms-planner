<x-nx-modal size="md" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--nx-accent)]/10 flex-shrink-0">
                @svg('heroicon-o-clock', 'w-5 h-5 text-[var(--nx-accent)]')
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 leading-tight">Sprint-Spalte</h3>
                <p class="text-[12px] text-[var(--nx-muted)] m-0 mt-0.5">Spalte im Sprint bearbeiten</p>
            </div>
        </div>
    </x-slot>

    @if($sprintSlot)
        <div class="space-y-4">
            <x-nx-input-text
                name="sprintSlot.name"
                label="Spaltenname"
                wire:model="sprintSlot.name"
                placeholder="Name der Spalte eingeben"
                errorKey="sprintSlot.label"
            />

            <div class="pt-3 border-t border-[color:var(--nx-line)]">
                <x-nx-button variant="danger" size="sm" wire:click="deleteSprintSlot" wire:confirm="Wirklich löschen?">Spalte löschen</x-nx-button>
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
