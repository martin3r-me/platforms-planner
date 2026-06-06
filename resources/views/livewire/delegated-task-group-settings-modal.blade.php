<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--planner-status-active)]/10 flex-shrink-0">
                @svg('heroicon-o-user-group', 'w-5 h-5 text-[var(--planner-status-active)]')
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Gruppen-Einstellungen</h3>
                <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Delegierte-Aufgaben-Gruppe bearbeiten</p>
            </div>
        </div>
    </x-slot>

    @if($taskGroup)
        <div class="space-y-4">
            <x-ui-input-text
                name="taskGroup.label"
                label="Gruppenname"
                wire:model="taskGroup.label"
                placeholder="Name der Gruppe eingeben"
                errorKey="taskGroup.label"
            />

            <div class="pt-3 border-t border-[var(--ui-border)]/40">
                <x-ui-confirm-button
                    action="deleteTaskGroup"
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
