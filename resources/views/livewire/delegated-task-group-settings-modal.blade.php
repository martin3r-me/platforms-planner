<x-ui-modal size="md" model="modalShow" header="Delegierte Aufgaben Gruppe Settings">

    @if($taskGroup)
    <div class="p-4">
        <x-ui-form-grid :cols="1" :gap="4">
            <x-ui-input-text
                name="taskGroup.label"
                label="Gruppenname"
                wire:model="taskGroup.label"
                placeholder="Name der Gruppe eingeben"
                errorKey="taskGroup.label"
            />
            
            <div class="d-flex justify-end">
                <x-ui-confirm-button action="deleteTaskGroup" text="Spalte löschen" confirmText="Wirklich löschen?" />
            </div>
        </x-ui-form-grid>
    </div>
    @endif
    
    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>

