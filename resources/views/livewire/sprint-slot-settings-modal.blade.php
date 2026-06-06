<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--planner-status-active)]/10 flex-shrink-0">
                @svg('heroicon-o-clock', 'w-5 h-5 text-[var(--planner-status-active)]')
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Sprint-Spalte</h3>
                <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Spalte im Sprint bearbeiten</p>
            </div>
        </div>
    </x-slot>

    @if($sprintSlot)
        <div class="space-y-4">
            <x-ui-input-text
                name="sprintSlot.name"
                label="Spaltenname"
                wire:model="sprintSlot.name"
                placeholder="Name der Spalte eingeben"
                errorKey="sprintSlot.label"
            />

            <div class="pt-3 border-t border-[var(--ui-border)]/40">
                <x-ui-confirm-button
                    action="deleteSprintSlot"
                    text="Spalte löschen"
                    confirmText="Wirklich löschen?"
                    variant="danger"
                />
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <x-ui-button variant="secondary-outline" size="sm" wire:click="closeModal">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" size="sm" wire:click="save">
                @svg('heroicon-o-check', 'w-3.5 h-3.5')
                <span>Speichern</span>
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
