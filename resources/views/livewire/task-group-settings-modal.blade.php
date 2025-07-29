<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        Aufgaben Gruppe Settings
    </x-slot>

    @if($taskGroup)
    <div class="grid grid-cols-2 gap-4">
        <label class="block text-sm font-medium text-slate-700">Projektname</label>
        <input type="text" wire:model="taskGroup.label"
               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
               placeholder="z. B. Neue Plattform, Website-Redesign">
        @error('taskGroup.label') <span class="text-sm text-red-500">{{ $message }}</span> @enderror

        <x-ui-confirm-button action="deleteTaskGroup" text="Spalte löschen" confirmText="Wirklich löschen?" />
    </div>
    @endif
    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>