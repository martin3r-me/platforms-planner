{{-- Kennzahlen der Statuszeile — überall gleich (Projekt, Meine Aufgaben, Delegiert):
     offene Komplexität (Story Points + ungeschätzte Aufgaben) und Anzahl offen/überfällig/erledigt.
     $open / $done: Collections der offenen bzw. erledigten Aufgaben. --}}
@props(['open', 'done', 'showFrogs' => false])
@php
    $openPoints = $open->sum(fn ($t) => $t->story_points?->points() ?? 0);
    $donePoints = $done->sum(fn ($t) => $t->story_points?->points() ?? 0);
    $unestimated = $open->filter(fn ($t) => ! $t->story_points)->count();
    $overdue = $open->filter(fn ($t) => $t->due_date && $t->due_date->isPast() && $t->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::ACTIVE)->count();
    $frogs = $showFrogs ? $open->filter(fn ($t) => $t->is_frog)->count() : 0;
@endphp

<x-nx-badge variant="neutral"
    title="Offene Komplexität {{ $openPoints }} SP{{ $unestimated ? ' · ' . $unestimated . ' offene Aufgabe(n) ohne Schätzung' : '' }} · erledigt {{ $donePoints }} SP">
    <span class="inline-flex items-center gap-1 text-[color:var(--nx-text)]">
        @svg('heroicon-o-puzzle-piece', 'w-3.5 h-3.5 text-[color:var(--nx-muted)]')
        <span class="tabular-nums font-semibold">{{ $openPoints }}</span>
        <span>SP offen</span>
    </span>
    @if($unestimated > 0)
        <span class="opacity-30" aria-hidden="true">·</span>
        <span class="inline-flex items-center gap-1 text-[color:var(--nx-warning)]">
            <span class="tabular-nums">{{ $unestimated }}</span>
            <span>ohne Schätzung</span>
        </span>
    @endif
</x-nx-badge>
<x-nx-badge variant="neutral"
    title="offen {{ $open->count() }} · überfällig {{ $overdue }} · erledigt {{ $done->count() }}">
    <span class="inline-flex items-center gap-1 text-[color:var(--nx-text)]">
        <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-info)]"></span>
        <span class="tabular-nums">{{ $open->count() }}</span>
        <span>offen</span>
    </span>
    @if($overdue > 0)
        <span class="inline-flex items-center gap-1 text-[color:var(--nx-danger)]">
            <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-danger)]"></span>
            <span class="tabular-nums">{{ $overdue }}</span>
            <span>überfällig</span>
        </span>
    @endif
    @if($frogs > 0)
        <span class="inline-flex items-center gap-1" title="Frösche"><span>🐸</span><span class="tabular-nums">{{ $frogs }}</span></span>
    @endif
    <span class="inline-flex items-center gap-1 text-[color:var(--nx-text)]">
        <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-success)]"></span>
        <span class="tabular-nums">{{ $done->count() }}</span>
        <span>erledigt</span>
    </span>
</x-nx-badge>
