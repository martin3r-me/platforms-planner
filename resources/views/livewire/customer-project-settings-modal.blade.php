<x-ui-modal size="lg" model="modalShow" header="Kundenprojekt">

    @if($project)
        <div class="space-y-6">
            {{-- Firma auswählen --}}
            <div>
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
                </x-ui-form-grid>
            </div>

            {{-- Firmen-Daten anzeigen --}}
            @if($companyData && $companyId)
                <div class="bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)] p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Firmen-Daten</h3>
                        @if($companyData['url'])
                            <a href="{{ $companyData['url'] }}" target="_blank" class="text-xs text-[var(--ui-primary)] hover:underline">
                                @svg('heroicon-o-arrow-top-right-on-square','w-4 h-4')
                            </a>
                        @endif
                    </div>
                    <div class="text-sm text-[var(--ui-secondary)] font-medium">
                        {{ $companyData['name'] }}
                    </div>
                </div>
            @endif

            {{-- Verknüpfte Kontakte --}}
            @if($companyContacts && count($companyContacts) > 0)
                <div>
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Verknüpfte Kontakte</h3>
                    <div class="space-y-2">
                        @foreach($companyContacts as $contact)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $contact['name'] }}</span>
                                        @if($contact['is_primary'] ?? false)
                                            <x-ui-badge variant="primary" size="xs">Primär</x-ui-badge>
                                        @endif
                                    </div>
                                    @if($contact['position'] ?? null)
                                        <div class="text-xs text-[var(--ui-muted)]">{{ $contact['position'] }}</div>
                                    @endif
                                    @if($contact['email'] ?? null)
                                        <div class="text-xs text-[var(--ui-muted)] mt-1">
                                            <span class="inline-flex items-center gap-1">
                                                @svg('heroicon-o-envelope','w-3 h-3')
                                                {{ $contact['email'] }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($contact['relation_type'] ?? null)
                                        <div class="text-xs text-[var(--ui-muted)] mt-1">
                                            <x-ui-badge variant="secondary" size="xs">{{ $contact['relation_type'] }}</x-ui-badge>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif($companyId && count($companyContacts) === 0)
                <div class="p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] text-center">
                    <p class="text-sm text-[var(--ui-muted)]">Keine Kontakte verknüpft</p>
                </div>
            @endif
        </div>
    @endif

    <x-slot name="footer">
        <x-ui-button variant="secondary-outline" wire:click="closeModal">Schließen</x-ui-button>
        <x-ui-button variant="success" wire:click="saveCompany">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>
