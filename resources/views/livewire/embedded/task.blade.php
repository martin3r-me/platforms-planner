<div class="h-full">
    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check">
                {{-- Simple Navigation für Embedded --}}
                <div class="flex items-center space-x-2">
                    @if($task->project)
                        <a href="{{ route('planner.embedded.project', $task->project) }}" class="text-sm underline text-[var(--ui-secondary)] hover:text-[var(--ui-primary)]">
                            Zurück zum Projekt
                        </a>
                    @endif
                </div>
                
                @if($printingAvailable)
                    <x-ui-button variant="secondary" size="sm" wire:click="printTask()">
                        @svg('heroicon-o-printer', 'w-4 h-4')
                        <span class="hidden sm:inline ml-1">Drucken</span>
                    </x-ui-button>
                @endif
                <x-ui-confirm-button 
                    action="deleteTaskAndReturnToProject" 
                    text="Löschen" 
                    confirmText="Wirklich löschen?" 
                    variant="danger"
                    :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                />
            </x-ui-page-navbar>
        </x-slot>

        <x-ui-page-container spacing="space-y-4" class="p-4">
            <div class="bg-white rounded-lg border p-4">
                <x-ui-form-grid :cols="1" :gap="4" class="md:grid-cols-2">
                    <div>
                        <x-ui-input-text
                            name="task.title"
                            label="Titel"
                            wire:model.live.debounce.500ms="task.title"
                            placeholder="Aufgabentitel eingeben..."
                            required
                            :errorKey="'task.title'"
                        />
                    </div>
                    <div>
                        <x-ui-input-select
                            name="task.priority"
                            label="Priorität"
                            :options="\Platform\Planner\Enums\TaskPriority::cases()"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="false"
                            wire:model.live="task.priority"
                        />
                    </div>
                    <div>
                        <x-ui-input-datetime
                            name="dueDateInput"
                            label="Fälligkeitsdatum"
                            :value="$dueDateInput"
                            wire:model="dueDateInput"
                            placeholder="Fälligkeitsdatum auswählen..."
                            :nullable="true"
                            :errorKey="'dueDateInput'"
                        />
                    </div>
                    <div>
                        <x-ui-input-select
                            name="task.user_in_charge_id"
                            label="Verantwortlicher"
                            :options="($teamUsers ?? collect([]))"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Verantwortlichen auswählen –"
                            wire:model.live="task.user_in_charge_id"
                        />
                    </div>
                </x-ui-form-grid>

                <div class="mt-6 pt-6 border-t">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui-input-checkbox
                            model="task.is_done"
                            checked-label="Erledigt"
                            unchecked-label="Als erledigt markieren"
                            size="md"
                            block="true"
                        />
                        <x-ui-input-checkbox
                            model="task.is_frog"
                            checked-label="Frosch (wichtig & unangenehm)"
                            unchecked-label="Als Frosch markieren"
                            size="md"
                            block="true"
                        />
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t">
                    <x-ui-input-textarea
                        name="task.description"
                        label="Beschreibung"
                        wire:model.live.debounce.500ms="task.description"
                        placeholder="Aufgabenbeschreibung (optional)"
                        rows="3"
                        :errorKey="'task.description'"
                    />
                </div>
            </div>
        </x-ui-page-container>
    </x-ui-page>

    {{-- Print Modal --}}
    @if($printingAvailable && $printModalShow)
        <x-ui-modal wire:model="printModalShow" title="Aufgabe drucken">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Druckziel auswählen</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" wire:model="printTarget" value="printer" class="mr-2">
                            <span>Einzelner Drucker</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" wire:model="printTarget" value="group" class="mr-2">
                            <span>Druckergruppe</span>
                        </label>
                    </div>
                </div>

                @if($printTarget === 'printer')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Drucker auswählen</label>
                        <select wire:model="selectedPrinterId" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">-- Drucker auswählen --</option>
                            @foreach($printers as $printer)
                                <option value="{{ $printer->id }}">{{ $printer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($printTarget === 'group')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Druckergruppe auswählen</label>
                        <select wire:model="selectedPrinterGroupId" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">-- Gruppe auswählen --</option>
                            @foreach($printerGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <x-ui-button variant="secondary" wire:click="closePrintModal">Abbrechen</x-ui-button>
                <x-ui-button 
                    variant="primary" 
                    wire:click="printTaskConfirm"
                    :disabled="($printTarget === 'printer' && !$selectedPrinterId) || ($printTarget === 'group' && !$selectedPrinterGroupId)"
                >
                    Drucken
                </x-ui-button>
            </x-slot>
        </x-ui-modal>
    @endif
</div>


