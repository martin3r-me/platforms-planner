<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        Kundenprojekt
    </x-slot>

    @if($project)
        <div class="p-4">
            <div class="grid grid-cols-2 gap-3">
                <x-ui-input-text 
                    name="companyId"
                    label="Company ID (CRM)"
                    wire:model.live.debounce.500ms="companyId"
                    placeholder="z.B. 123"
                />
                <div>
                    <label class="block text-sm font-medium mb-1">Firma</label>
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


