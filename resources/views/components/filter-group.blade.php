{{-- Gruppe im Filter-Popover der Planner-Actionbar. :stack=true → Liste untereinander statt Chips. --}}
@props(['label', 'stack' => false])
<section>
    <div class="text-[10px] uppercase tracking-wider font-semibold text-[color:var(--nx-muted)] mb-1.5">{{ $label }}</div>
    <div class="{{ $stack ? 'flex flex-col gap-0.5' : 'flex flex-wrap gap-1.5' }}">
        {{ $slot }}
    </div>
</section>
