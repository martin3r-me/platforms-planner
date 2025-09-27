<x-ui-organisms-modal model="modalShow" size="md" header="Task drucken">

        <!-- Auswahl-Typ -->
        <x-ui-radio-group
            name="printTarget"
            label="Druckziel wählen:"
            :options="[
                ['value' => 'printer', 'label' => 'Einzelner Drucker'],
                ['value' => 'group', 'label' => 'Drucker-Gruppe']
            ]"
        />

        <!-- Drucker-Auswahl -->
        @if($printTarget === 'printer')
            <x-ui-radio-list
                name="selectedPrinterId"
                label="Drucker auswählen:"
                :items="$printers"
                emptyMessage="Keine aktiven Drucker verfügbar"
            />
        @endif

        <!-- Gruppen-Auswahl -->
        @if($printTarget === 'group')
            <x-ui-radio-list
                name="selectedPrinterGroupId"
                label="Gruppe auswählen:"
                :items="$printerGroups"
                emptyMessage="Keine aktiven Drucker-Gruppen verfügbar"
            />
        @endif

        <!-- Info -->
        <x-ui-info-banner
            :message="match($printTarget) {
                'printer' => 'Der Task wird auf dem ausgewählten Drucker gedruckt.',
                'group' => 'Der Task wird auf allen aktiven Druckern der Gruppe gedruckt.',
                default => 'Wählen Sie einen Drucker oder eine Gruppe aus.'
            }"
            variant="info"
        />

    <x-slot name="footer">
            <x-ui-button type="button" variant="secondary-outline" wire:click="closePrintModal">
                Abbrechen
            </x-ui-button>
            <x-ui-button type="button" variant="primary" wire:click="printTaskConfirm">
                Drucken
            </x-ui-button>
    </x-slot>
</x-ui-organisms-modal>