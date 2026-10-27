{{-- Auswahl-Chip im Filter-Popover. :active markiert die Auswahl, dot = Farbpunkt, count = Anzahl rechts.
     :row=true → volle Breite (für Listen, z. B. Personen/Projekte). --}}
@props(['active' => false, 'dot' => null, 'count' => null, 'row' => false, 'icon' => null])
<button type="button" {{ $attributes->merge(['class' =>
    ($row ? 'w-full flex justify-between px-2 py-1.5 sm:py-1 rounded-md text-left ' : 'inline-flex px-2.5 py-1.5 sm:py-1 rounded-full ')
    . 'items-center gap-1.5 text-[12px] sm:text-[11px] font-medium transition-colors '
    . ($active
        ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]'
        : 'bg-[color:var(--nx-bg)] text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]')
]) }}>
    <span class="inline-flex items-center gap-1.5 min-w-0">
        @if($dot)<span class="w-2 h-2 rounded-full shrink-0 {{ str_starts_with($dot, 'bg-') ? $dot : '' }}" @unless(str_starts_with($dot, 'bg-')) style="background-color: {{ $dot }}" @endunless></span>@endif
        @if($icon) @svg($icon, 'w-3.5 h-3.5 shrink-0') @endif
        <span class="truncate">{{ $slot }}</span>
    </span>
    @if($count !== null)<span class="tabular-nums opacity-70 text-[10px] shrink-0">{{ $count }}</span>@endif
</button>
