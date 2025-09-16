<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        Kundenprojekt
    </x-slot>

    @if($project)
        <div class="p-4 text-sm text-muted">
            Einstellungen folgen.
        </div>
    @endif

    <x-slot name="footer">
        <x-ui-button variant="secondary-outline" wire:click="closeModal">Schließen</x-ui-button>
    </x-slot>
</x-ui-modal>


