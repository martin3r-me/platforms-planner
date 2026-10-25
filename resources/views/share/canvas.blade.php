{{-- Kunden-Tor · ein Canvas (nur lesen) — dieselben Blöcke wie intern, oben was sich seit dem letzten Besuch geändert hat.
     Daten: $canvas, $canvasData, $blockDefs, $changes, $sinceIsVisit, $updatedAt. --}}
<div class="mb-6 flex flex-wrap items-baseline justify-between gap-2">
    @if ($canvas->description)
        <p class="max-w-3xl text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $canvas->description }}</p>
    @endif
    @if (! empty($updatedAt))
        <span class="text-xs text-[color:var(--nx-faint)]">Zuletzt aktualisiert {{ $updatedAt->copy()->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr</span>
    @endif
</div>
@if (! empty($changes))
    <div class="mb-6 max-w-5xl rounded-[12px] border border-[rgba(25,113,194,.25)] bg-[rgba(25,113,194,.05)] p-4">
        <div class="flex items-center gap-2 text-sm font-semibold">
            <span class="h-2 w-2 rounded-full bg-[color:var(--nx-info)]"></span>
            {{ $sinceIsVisit ? 'Neu oder geändert seit Ihrem letzten Besuch' : 'Neu oder geändert in den letzten 7 Tagen' }}
        </div>
        <ul class="mt-2 space-y-1">
            @foreach (array_slice($changes, 0, 12) as $ch)
                <li class="flex items-baseline gap-2 text-sm">
                    <span class="shrink-0 rounded-full px-1.5 py-px text-[10px] font-medium {{ $ch['new'] ? 'bg-[rgba(47,158,68,.12)] text-[color:var(--nx-success)]' : 'bg-[rgba(25,113,194,.12)] text-[color:var(--nx-info)]' }}">{{ $ch['new'] ? 'neu' : 'geändert' }}</span>
                    <span class="min-w-0"><span class="text-[color:var(--nx-muted)]">{{ $ch['block'] }}:</span> {{ $ch['title'] }}</span>
                    <span class="ml-auto shrink-0 text-[11px] text-[color:var(--nx-faint)]">{{ $ch['at']->copy()->locale('de')->isoFormat('D. MMM') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@php
    // Für Kunden nur Blöcke mit Inhalt — leere Felder sind interne Arbeitsstruktur
    $filled = collect($blockDefs)->filter(fn ($def) => ! empty($canvasData['blocks'][$def['key']]['entries'] ?? []));
@endphp
@if ($filled->isEmpty())
    <div class="max-w-3xl rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-10 text-center text-sm text-[color:var(--nx-faint)]">Dieses Canvas ist noch leer.</div>
@endif
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    @foreach ($filled as $i => $def)
        <div wire:key="cb-{{ $def['key'] }}">
            @include('planner::livewire.project-canvas._block', [
                'blockKey' => $def['key'],
                'blocks' => $canvasData['blocks'],
                'blockDefs' => $blockDefs,
                'blockIndex' => $i,
            ])
        </div>
    @endforeach
</div>
