<x-ui-organisms-modal size="md" model="modalShow" header="Sprint Spalte Settings">

    @if($sprintSlot)
        <x-ui-form-grid :cols="1" :gap="4">
            <x-ui-input-text
                name="sprintSlot.name"
                label="Spaltenname"
                wire:model="sprintSlot.name"
                placeholder="Name der Spalte eingeben"
                errorKey="sprintSlot.label"
            />
            
            <div class="d-flex justify-end">
                <x-ui-confirm-button action="deleteSprintSlot" text="Spalte löschen" confirmText="Wirklich löschen?" />
            </div>
        </x-ui-form-grid>
    </div>
    @endif
    
    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-organisms-modal>