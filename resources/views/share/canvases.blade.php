{{-- Kunden-Tor · Canvases eines Projekts — der inhaltliche Stand. Karte je Canvas mit Vorschau der gefüllten Felder,
     Füllstand und „geändert seit Ihrem letzten Besuch"; Klick → Unterseite canvas-{id}. Daten: $canvases, $link(sub). --}}
<div class="mx-auto max-w-[1120px] px-4 py-6 sm:px-8">
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        @foreach ($canvases as $c)
            @php
                $fill = $c['blocks_total'] ? (int) round($c['blocks_filled'] / $c['blocks_total'] * 100) : 0;
            @endphp
            <a href="{{ $link('canvas-' . $c['id']) }}" wire:navigate wire:key="cv-{{ $c['id'] }}"
               class="group flex flex-col rounded-[12px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-4 transition-colors hover:border-[color:var(--nx-line-strong)] hover:bg-[color:var(--nx-hover)]">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-[rgba(25,113,194,.1)] text-[color:var(--nx-info)]">@svg('heroicon-o-squares-2x2', 'w-5 h-5')</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-semibold">{{ $c['name'] }}</span>
                            <x-nx-badge :variant="$c['done'] ? 'success' : 'info'">{{ $c['status_label'] }}</x-nx-badge>
                            @if ($c['changed'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-[rgba(25,113,194,.1)] px-2 py-0.5 text-[11px] font-medium text-[color:var(--nx-info)]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-info)]"></span>{{ $c['changed'] }} neu/geändert
                                </span>
                            @endif
                        </div>
                        @if ($c['description'])
                            <div class="mt-0.5 line-clamp-2 text-xs text-[color:var(--nx-muted)]">{{ $c['description'] }}</div>
                        @endif
                    </div>
                    @svg('heroicon-o-chevron-right', 'mt-1 w-4 h-4 shrink-0 text-[color:var(--nx-faint)] group-hover:text-[color:var(--nx-text)]')
                </div>

                @if ($c['preview'])
                    <div class="mt-3 space-y-2 border-t border-[color:var(--nx-line)] pt-3">
                        @foreach ($c['preview'] as $block)
                            <div>
                                <div class="text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $block['label'] }}</div>
                                <ul class="mt-0.5 space-y-0.5">
                                    @foreach ($block['items'] as $text)
                                        <li class="flex gap-1.5 text-[13px] leading-snug"><span class="text-[color:var(--nx-faint)]">–</span><span class="line-clamp-1">{{ $text }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-3 border-t border-[color:var(--nx-line)] pt-3 text-[13px] text-[color:var(--nx-faint)]">Noch keine Inhalte.</div>
                @endif

                <div class="mt-auto pt-3">
                    <div class="flex items-center justify-between text-[11px] text-[color:var(--nx-faint)]">
                        <span>{{ $c['blocks_filled'] }} von {{ $c['blocks_total'] }} Feldern gefüllt · {{ $c['entries'] }} {{ $c['entries'] === 1 ? 'Eintrag' : 'Einträge' }}</span>
                        <span>{{ $c['updated_at'] ? 'aktualisiert ' . $c['updated_at']->copy()->locale('de')->diffForHumans() : '' }}</span>
                    </div>
                    <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                        <div class="h-full rounded-full bg-[color:var(--nx-info)]" style="width: {{ $fill }}%"></div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>
