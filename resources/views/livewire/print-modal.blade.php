<x-ui-modal model="modalShow" size="md">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--planner-status-active)]/10 flex-shrink-0">
                @svg('heroicon-o-printer', 'w-5 h-5 text-[var(--planner-status-active)]')
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Aufgabe drucken</h3>
                <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Druckziel und Layout wählen</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">

        {{-- DRUCKANSICHT --}}
        <section>
            <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Ansicht</h4>
            <div class="inline-flex rounded-md border border-[var(--ui-border)] overflow-hidden w-full">
                <button
                    type="button"
                    wire:click="$set('printView', 'simple')"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium transition-colors {{ $printView === 'simple' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                >
                    @svg('heroicon-o-document', 'w-3.5 h-3.5')
                    Einfach
                </button>
                <button
                    type="button"
                    wire:click="$set('printView', 'detailed')"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium border-l border-[var(--ui-border)] transition-colors {{ $printView === 'detailed' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                >
                    @svg('heroicon-o-document-text', 'w-3.5 h-3.5')
                    Detailliert
                </button>
            </div>
        </section>

        {{-- DRUCKZIEL --}}
        <section>
            <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Druckziel</h4>
            <div class="inline-flex rounded-md border border-[var(--ui-border)] overflow-hidden w-full">
                <button
                    type="button"
                    wire:click="$set('printTarget', 'printer')"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium transition-colors {{ $printTarget === 'printer' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                >
                    @svg('heroicon-o-printer', 'w-3.5 h-3.5')
                    Einzelner Drucker
                </button>
                <button
                    type="button"
                    wire:click="$set('printTarget', 'group')"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium border-l border-[var(--ui-border)] transition-colors {{ $printTarget === 'group' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                >
                    @svg('heroicon-o-rectangle-stack', 'w-3.5 h-3.5')
                    Drucker-Gruppe
                </button>
            </div>
        </section>

        {{-- AUSWAHL: EINZELNER DRUCKER --}}
        @if($printTarget === 'printer')
            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Drucker auswählen</h4>
                @if($printers && count($printers) > 0)
                    <div class="space-y-1">
                        @foreach($printers as $printer)
                            @php $pid = $printer->id ?? $printer['id'] ?? null; $pname = $printer->name ?? $printer['name'] ?? 'Drucker'; @endphp
                            <label class="flex items-center gap-2.5 px-3 py-2 rounded-md border cursor-pointer transition-all
                                {{ $selectedPrinterId == $pid
                                    ? 'border-[var(--planner-status-active)]/60 bg-[var(--planner-status-active)]/5'
                                    : 'border-[var(--ui-border)]/40 hover:border-[var(--planner-status-active)]/30 hover:bg-[var(--ui-muted-5)]' }}">
                                <input
                                    type="radio"
                                    name="selectedPrinterId"
                                    value="{{ $pid }}"
                                    wire:model.live="selectedPrinterId"
                                    class="accent-[var(--planner-status-active)]"
                                />
                                @svg('heroicon-o-printer', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-[13px] text-[var(--ui-secondary)] flex-1 truncate">{{ $pname }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 px-3 py-2 rounded-md bg-[var(--ui-muted-5)]">
                        Keine aktiven Drucker verfügbar
                    </p>
                @endif
            </section>

            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Format</h4>
                <div class="inline-flex rounded-md border border-[var(--ui-border)] overflow-hidden">
                    <button
                        type="button"
                        wire:click="$set('paper', 'a4')"
                        class="inline-flex items-center px-3 h-7 text-[11px] font-medium transition-colors {{ $paper === 'a4' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                    >A4</button>
                    <button
                        type="button"
                        wire:click="$set('paper', 'letter')"
                        class="inline-flex items-center px-3 h-7 text-[11px] font-medium border-l border-[var(--ui-border)] transition-colors {{ $paper === 'letter' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                    >Letter</button>
                </div>
            </section>
        @endif

        {{-- AUSWAHL: DRUCKER-GRUPPE --}}
        @if($printTarget === 'group')
            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Gruppe auswählen</h4>
                @if($printerGroups && count($printerGroups) > 0)
                    <div class="space-y-1">
                        @foreach($printerGroups as $group)
                            @php $gid = $group->id ?? $group['id'] ?? null; $gname = $group->name ?? $group['name'] ?? 'Gruppe'; @endphp
                            <label class="flex items-center gap-2.5 px-3 py-2 rounded-md border cursor-pointer transition-all
                                {{ $selectedPrinterGroupId == $gid
                                    ? 'border-[var(--planner-status-active)]/60 bg-[var(--planner-status-active)]/5'
                                    : 'border-[var(--ui-border)]/40 hover:border-[var(--planner-status-active)]/30 hover:bg-[var(--ui-muted-5)]' }}">
                                <input
                                    type="radio"
                                    name="selectedPrinterGroupId"
                                    value="{{ $gid }}"
                                    wire:model.live="selectedPrinterGroupId"
                                    class="accent-[var(--planner-status-active)]"
                                />
                                @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-[13px] text-[var(--ui-secondary)] flex-1 truncate">{{ $gname }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 px-3 py-2 rounded-md bg-[var(--ui-muted-5)]">
                        Keine aktiven Drucker-Gruppen verfügbar
                    </p>
                @endif
            </section>

            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Ausrichtung</h4>
                <div class="inline-flex rounded-md border border-[var(--ui-border)] overflow-hidden">
                    <button
                        type="button"
                        wire:click="$set('orientation', 'portrait')"
                        class="inline-flex items-center px-3 h-7 text-[11px] font-medium transition-colors {{ $orientation === 'portrait' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                    >Hochformat</button>
                    <button
                        type="button"
                        wire:click="$set('orientation', 'landscape')"
                        class="inline-flex items-center px-3 h-7 text-[11px] font-medium border-l border-[var(--ui-border)] transition-colors {{ $orientation === 'landscape' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                    >Querformat</button>
                </div>
            </section>
        @endif

        {{-- INFO --}}
        <div class="flex items-start gap-2 px-3 py-2 rounded-lg bg-[var(--planner-status-active)]/5 border border-[var(--planner-status-active)]/20 text-[12px]">
            @svg('heroicon-o-information-circle', 'w-4 h-4 text-[var(--planner-status-active)] flex-shrink-0 mt-0.5')
            <span class="text-[var(--ui-secondary)]">
                {{ match($printTarget) {
                    'printer' => 'Der Task wird auf dem ausgewählten Drucker gedruckt.',
                    'group'   => 'Der Task wird auf allen aktiven Druckern der Gruppe gedruckt.',
                    default   => 'Wähle einen Drucker oder eine Gruppe aus.',
                } }}
            </span>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closePrintModal">
                Abbrechen
            </x-ui-button>
            <x-ui-button type="button" variant="primary" size="sm" wire:click="printTaskConfirm">
                @svg('heroicon-o-printer', 'w-3.5 h-3.5')
                <span>Drucken</span>
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
