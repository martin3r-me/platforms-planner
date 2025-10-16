<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Spalten-Einstellungen</h3>
                <p class="text-sm text-gray-500">Spalte bearbeiten und verwalten</p>
            </div>
        </div>
    </x-slot>

    @if($projectSlot)
        <div class="space-y-6">
            <x-ui-input-text
                name="projectSlot.name"
                label="Spaltenname"
                wire:model="projectSlot.name"
                placeholder="Name der Spalte eingeben"
                errorKey="projectSlot.label"
            />
            
            <div class="pt-4 border-t border-gray-200">
                <x-ui-confirm-button 
                    action="deleteProjectSlot" 
                    text="Spalte löschen" 
                    confirmText="Wirklich löschen?" 
                    variant="danger"
                />
            </div>
        </div>
    @endif
    
    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-ui-button variant="secondary" wire:click="closeModal">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
