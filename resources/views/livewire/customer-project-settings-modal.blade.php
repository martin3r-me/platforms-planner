<x-ui-organisms-modal size="md" model="modalShow" header="Kundenprojekt">

    @if($project)
            <x-ui-form-grid :cols="2" :gap="3">
                <x-ui-input-text 
                    name="companySearch"
                    label="Suche"
                    wire:model.live.debounce.300ms="companySearch"
                    placeholder="Firma suchen..."
                />
                
                <x-ui-input-select
                    name="companyId"
                    label="Firma (CRM)"
                    :options="$companyOptions"
                    wire:model.live="companyId"
                    nullable="true"
                    nullLabel="– wählen –"
                />
                
                <x-ui-info-display
                    label="Auswahl"
                    :value="$companyDisplay"
                />
            </x-ui-form-grid>
    @endif

    <x-slot name="footer">
        <x-ui-button variant="secondary-outline" wire:click="closeModal">Schließen</x-ui-button>
        <x-ui-button variant="success" wire:click="saveCompany">Speichern</x-ui-button>
    </x-slot>
</x-ui-organisms-modal>


