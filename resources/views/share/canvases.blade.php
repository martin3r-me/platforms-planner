{{-- Kunden-Tor · Canvases eines Projekts (Liste → Unterseite canvas-{id}). Daten: $canvases, $link(sub). --}}
<div class="mx-auto max-w-[1200px] px-4 py-6 sm:px-8">
    <div class="max-w-3xl overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
        @foreach ($canvases as $c)
            <a href="{{ $link('canvas-' . $c['id']) }}" wire:navigate wire:key="cv-{{ $c['id'] }}"
               class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-squares-2x2', 'w-5 h-5 shrink-0 text-[color:var(--nx-faint)]')
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ $c['name'] }}</div>
                    <div class="mt-0.5 truncate text-xs text-[color:var(--nx-muted)]">
                        {{ collect([$c['description'] ?: null, $c['updated_at'] ? 'Aktualisiert ' . $c['updated_at']->copy()->locale('de')->diffForHumans() : null])->filter()->implode(' · ') }}
                    </div>
                </div>
                <x-nx-badge :variant="$c['done'] ? 'success' : 'info'">{{ $c['status_label'] }}</x-nx-badge>
                @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] group-hover:text-[color:var(--nx-text)]')
            </a>
        @endforeach
    </div>
</div>
