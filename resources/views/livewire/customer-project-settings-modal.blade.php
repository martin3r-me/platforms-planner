<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        Kundenprojekt
    </x-slot>

    @if($project)
        <div class="p-4">
            <div class="grid grid-cols-2 gap-3">
                <x-ui-input-text 
                    name="companySearch"
                    label="Suche"
                    wire:model.live.debounce.300ms="companySearch"
                    placeholder="Firma suchen..."
                />
                <div>
                    <label class="block text-sm font-medium mb-1">Firma (CRM)</label>
                    <select class="w-full border rounded px-2 py-1 text-sm" wire:model.live="companyId">
                        <option value="">– wählen –</option>
                        @foreach($companyOptions as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Auswahl</label>
                    <div class="text-sm text-muted py-2">{{ $companyDisplay ?? '–' }}</div>
                </div>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <x-ui-button variant="secondary-outline" wire:click="closeModal">Schließen</x-ui-button>
        <x-ui-button variant="primary" wire:click="saveCompany">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>


