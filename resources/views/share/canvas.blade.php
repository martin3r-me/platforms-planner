{{-- Kunden-Tor · ein Canvas (nur lesen) — dieselben Blöcke wie intern. Daten: $canvas, $canvasData, $blockDefs. --}}
@if ($canvas->description)
    <p class="mb-6 max-w-3xl text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $canvas->description }}</p>
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
