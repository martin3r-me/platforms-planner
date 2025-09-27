<x-ui-organisms-modal size="md" model="modalShow" header="Project Spalte Settings" wire:click.away="closeModal">

    @if($projectSlot)
        <x-ui-form-grid :cols="1" :gap="4">
            <x-ui-input-text
                name="projectSlot.name"
                label="Spaltenname"
                wire:model="projectSlot.name"
                placeholder="Name der Spalte eingeben"
                errorKey="projectSlot.label"
            />
            
            <div class="d-flex justify-end">
                <x-ui-confirm-button action="deleteProjectSlot" text="Spalte löschen" confirmText="Wirklich löschen?" />
            </div>
        </x-ui-form-grid>
    @endif
    
    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-organisms-modal>
