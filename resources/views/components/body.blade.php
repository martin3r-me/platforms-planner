{{-- Inhaltsfläche aller Planner-Seiten: volle Breite, gleicher Grund, gleiche Abstände.
     :flush=true → ohne Innenabstand (Board, Canvas, Tabellen mit eigener Toolbar). --}}
@props(['flush' => false])
<div {{ $attributes->merge(['class' => 'flex-1 min-w-0 min-h-0 overflow-y-auto overflow-x-hidden bg-[color:var(--nx-bg)] text-[color:var(--nx-text)]']) }}>
    @if($flush)
        {{ $slot }}
    @else
        <div class="w-full px-3 sm:px-6 py-4 sm:py-6 space-y-4 sm:space-y-6">
            {{ $slot }}
        </div>
    @endif
</div>
