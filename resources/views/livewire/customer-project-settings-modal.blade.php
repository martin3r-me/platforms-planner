<x-nx-modal size="lg" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--nx-accent)]/10 flex-shrink-0">
                @svg('heroicon-o-briefcase', 'w-5 h-5 text-[var(--nx-accent)]')
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 leading-tight">Kundenprojekt</h3>
                <p class="text-[12px] text-[var(--nx-muted)] m-0 mt-0.5">Firma und Kontakte aus dem CRM verknüpfen</p>
            </div>
        </div>
    </x-slot>


    @if($project)
        <div class="space-y-6">
            {{-- Firma auswählen --}}
            <div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-nx-input-text 
                        name="companySearch"
                        label="Suche"
                        wire:model.live.debounce.300ms="companySearch"
                        placeholder="Firma suchen..."
                    />
                    
                    <x-nx-input-select
                        name="companyId"
                        label="Firma (CRM)"
                        :options="$companyOptions"
                        wire:model.live="companyId"
                        nullable="true"
                        nullLabel="– wählen –"
                    />
                </div>
            </div>

            {{-- Firmen-Daten anzeigen --}}
            @if($companyData && $companyId)
                <div class="bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)] p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-[var(--nx-text)]">Firmen-Daten</h3>
                        @if($companyData['url'])
                            <a href="{{ $companyData['url'] }}" target="_blank" class="text-xs text-[var(--nx-accent)] hover:underline">
                                @svg('heroicon-o-arrow-top-right-on-square','w-4 h-4')
                            </a>
                        @endif
                    </div>
                    <div class="text-sm text-[var(--nx-text)] font-medium">
                        {{ $companyData['name'] }}
                    </div>
                </div>
            @endif

            {{-- Kontakte auswählen --}}
            @if($companyId && $companyContacts && count($companyContacts) > 0)
                <div>
                    <h3 class="text-sm font-semibold text-[var(--nx-text)] mb-3">Kontakte mit Projekt verknüpfen</h3>
                    <p class="text-xs text-[var(--nx-muted)] mb-3">Wählen Sie einen oder mehrere Kontakte aus, die mit diesem Projekt verknüpft werden sollen.</p>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($companyContacts as $contact)
                            @php
                                $isSelected = in_array($contact['id'], $selectedContactIds ?? []);
                                $isLinked = collect($projectContacts ?? [])->contains('id', $contact['id']);
                            @endphp
                            <label class="flex items-start gap-3 p-3 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-bg)] hover:bg-[var(--nx-line)] transition-colors cursor-pointer {{ $isSelected ? 'ring-2 ring-[var(--nx-accent)]' : '' }}">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedContactIds"
                                    value="{{ $contact['id'] }}"
                                    class="mt-1 rounded border-[color:var(--nx-line)] text-[var(--nx-accent)] focus:ring-[var(--nx-accent)]"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-medium text-[var(--nx-text)]">{{ $contact['name'] }}</span>
                                        @if($contact['is_primary'] ?? false)
                                            <x-nx-badge variant="accent">Primär</x-nx-badge>
                                        @endif
                                        @if($isLinked)
                                            <x-nx-badge variant="success">Verknüpft</x-nx-badge>
                                        @endif
                                    </div>
                                    @if($contact['position'] ?? null)
                                        <div class="text-xs text-[var(--nx-muted)]">{{ $contact['position'] }}</div>
                                    @endif
                                    @if($contact['email'] ?? null)
                                        <div class="text-xs text-[var(--nx-muted)] mt-1">
                                            <span class="inline-flex items-center gap-1">
                                                @svg('heroicon-o-envelope','w-3 h-3')
                                                {{ $contact['email'] }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($contact['relation_type'] ?? null)
                                        <div class="text-xs text-[var(--nx-muted)] mt-1">
                                            <x-nx-badge variant="neutral">{{ $contact['relation_type'] }}</x-nx-badge>
                                        </div>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @elseif($companyId && count($companyContacts) === 0)
                <div class="p-4 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-bg)] text-center">
                    <p class="text-sm text-[var(--nx-muted)]">Keine Kontakte für diese Firma verfügbar</p>
                </div>
            @endif

            {{-- Bereits verknüpfte Kontakte (wenn keine Company ausgewählt) --}}
            @if(!$companyId && $projectContacts && count($projectContacts) > 0)
                <div>
                    <h3 class="text-sm font-semibold text-[var(--nx-text)] mb-3">Verknüpfte Kontakte</h3>
                    <div class="space-y-2">
                        @foreach($projectContacts as $contact)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-bg)]">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-medium text-[var(--nx-text)]">{{ $contact['name'] }}</span>
                                    </div>
                                    @if($contact['email'] ?? null)
                                        <div class="text-xs text-[var(--nx-muted)] mt-1">
                                            <span class="inline-flex items-center gap-1">
                                                @svg('heroicon-o-envelope','w-3 h-3')
                                                {{ $contact['email'] }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <x-slot name="footer">
        <x-nx-button variant="secondary" wire:click="closeModal">Schließen</x-nx-button>
        <x-nx-button variant="primary" wire:click="saveCompany">Speichern</x-nx-button>
    </x-slot>
</x-nx-modal>
