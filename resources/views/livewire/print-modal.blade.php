<x-ui-modal model="modalShow" size="md" header="Task drucken">

        <!-- Auswahl-Typ -->
        {{-- Radio Gruppe lokal --}}
        <div class="space-y-2">
            <label class="block text-sm font-medium">Druckansicht</label>
            <div class="space-y-1">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="print_view" value="simple" wire:model.live="printView" class="accent-[rgb(var(--ui-primary-rgb))]">
                    <span>Einfach</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="print_view" value="detailed" wire:model.live="printView" class="accent-[rgb(var(--ui-primary-rgb))]">
                    <span>Detailiert</span>
                </label>
            </div>
        </div>
            name="printTarget"
            label="Druckziel wählen:"
            :options="[
                ['value' => 'printer', 'label' => 'Einzelner Drucker'],
                ['value' => 'group', 'label' => 'Drucker-Gruppe']
            ]"
        />

        <!-- Drucker-Auswahl -->
        @if($printTarget === 'printer')
            {{-- Radio Liste lokal --}}
            <div class="space-y-1">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="paper" value="a4" wire:model.live="paper" class="accent-[rgb(var(--ui-primary-rgb))]">
                    <span>A4</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="paper" value="letter" wire:model.live="paper" class="accent-[rgb(var(--ui-primary-rgb))]">
                    <span>Letter</span>
                </label>
            </div>
                name="selectedPrinterId"
                label="Drucker auswählen:"
                :items="$printers"
                emptyMessage="Keine aktiven Drucker verfügbar"
            />
        @endif

        <!-- Gruppen-Auswahl -->
        @if($printTarget === 'group')
            <div class="space-y-1">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="orientation" value="portrait" wire:model.live="orientation" class="accent-[rgb(var(--ui-primary-rgb))]">
                    <span>Hochformat</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="orientation" value="landscape" wire:model.live="orientation" class="accent-[rgb(var(--ui-primary-rgb))]">
                    <span>Querformat</span>
                </label>
            </div>
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
</x-ui-modal>