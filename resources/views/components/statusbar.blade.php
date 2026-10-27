{{-- Kennzahlen der Seite: schmale Zeile direkt unter der Actionbar, im Inhalt (die Actionbar
     selbst bleibt auf allen Seiten identisch). Erstes Element der Hauptspalte. --}}
<div {{ $attributes->merge(['class' => 'shrink-0 flex flex-wrap items-center gap-2 px-3 sm:px-6 py-1.5 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-xs text-[color:var(--nx-muted)]']) }}>
    {{ $slot }}
</div>
