{{-- Kunden-Tor · eine Board-Spalte wie x-nx-kanban-column, Karte wie planner::livewire.task-preview-card. Daten: $col, $collapsible. --}}
@php
    $slotBg = "color-mix(in srgb, var(--nx-tone-{$col['tone']}) 6%, #ffffff)";
    $pillBg = "color-mix(in srgb, var(--nx-tone-{$col['tone']}) 16%, #ffffff)";
@endphp
<div class="gp-col" style="background-color: {{ $slotBg }}" wire:key="col-{{ $col['key'] }}">
    <div @class(['flex items-center justify-between px-1.5 py-1.5', 'opacity-80' => $col['muted']])>
        <div class="flex min-w-0 items-center gap-1.5">
            <span class="inline-flex min-w-0 items-center gap-1.5 rounded-[6px] px-2 py-0.5" style="background-color: {{ $pillBg }}">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: var(--nx-tone-{{ $col['tone'] }})"></span>
                <span class="truncate text-xs font-medium">{{ $col['title'] }}</span>
            </span>
            <span class="shrink-0 text-xs text-[color:var(--nx-faint)]">{{ $col['total'] ?? count($col['tasks']) }}</span>
        </div>
        @if ($collapsible ?? false)
            <button type="button" @click="setDone(false)" class="text-[color:var(--nx-faint)] transition-colors hover:text-[color:var(--nx-text)]" title="Einklappen">
                @svg('heroicon-o-chevron-double-right', 'w-4 h-4')
            </button>
        @endif
    </div>

    <div class="gp-col-body">
        @forelse ($col['tasks'] as $t)
            <button type="button" @click="task = @js($t)" wire:key="card-{{ $t['id'] }}" @class(['gp-card-task', 'opacity-60' => $t['done']])>
                <span class="gp-edge" style="background-color: {{ $t['edge'] }}"></span>
                <span @class(['block text-[13px] font-semibold leading-snug', 'line-through text-[color:var(--nx-muted)]' => $t['done']])>{{ $t['title'] }}</span>
                @php $hasMeta = ($t['due_phrase'] && ! $t['done']) || $t['done_at'] || $t['tag'] || $t['dod'] || $t['description'] || $t['person']; @endphp
                @if ($hasMeta)
                    <span class="mt-2 flex items-center gap-1.5 text-[10px] leading-none text-[color:var(--nx-muted)]">
                        @if ($t['due_phrase'] && ! $t['done'])
                            <span @class(['inline-flex shrink-0 items-center gap-0.5', 'font-medium text-[color:var(--nx-danger)]' => $t['overdue']]) title="Fällig {{ $t['due'] }}">
                                @svg('heroicon-o-clock', 'w-3 h-3 opacity-60') {{ $t['due_phrase'] }}
                            </span>
                        @elseif ($t['done_at'])
                            <span class="inline-flex shrink-0 items-center gap-0.5">@svg('heroicon-o-check', 'w-3 h-3 opacity-60') {{ $t['done_at'] }}</span>
                        @endif
                        @if ($t['tag'])
                            <span class="inline-flex max-w-[7rem] shrink-0 items-center truncate rounded-full px-2 py-0.5 text-[10px] font-semibold text-white" style="background-color: {{ $t['tag']['color'] }}" title="{{ $t['tag']['label'] }}">{{ $t['tag']['label'] }}</span>
                            @if ($t['tags_more'])
                                <span class="inline-flex h-4 min-w-[1.1rem] shrink-0 items-center justify-center rounded-full bg-[color:var(--nx-accent-soft)] px-1 text-[9px] font-bold tabular-nums text-[color:var(--nx-text)]">+{{ $t['tags_more'] }}</span>
                            @endif
                        @endif
                        @if ($t['dod'])
                            <span class="inline-flex shrink-0 items-center gap-1" title="Erledigt, wenn …: {{ $t['dod']['checked'] }}/{{ $t['dod']['total'] }}">
                                <span class="inline-block h-1 w-6 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                                    <span class="block h-full rounded-full {{ $t['dod']['pct'] >= 100 ? 'bg-[color:var(--nx-success)]' : 'bg-[color:var(--nx-accent)]' }}" style="width: {{ $t['dod']['pct'] }}%"></span>
                                </span>
                                <span class="tabular-nums">{{ $t['dod']['checked'] }}/{{ $t['dod']['total'] }}</span>
                            </span>
                        @endif
                        <span class="flex-1"></span>
                        @if ($t['description'])
                            @svg('heroicon-o-bars-3-bottom-left', 'w-3 h-3 shrink-0 opacity-60')
                        @endif
                        @if ($t['person'])
                            <span class="ml-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] bg-[color:var(--nx-accent-soft)] text-[9px] font-semibold text-[color:var(--nx-text)]" title="{{ $t['person'] }}">{{ $t['person_initials'] }}</span>
                        @endif
                    </span>
                @endif
            </button>
        @empty
            <div class="flex flex-col items-center justify-center py-8 text-[color:var(--nx-muted)]">
                @svg($col['key'] === 'done' ? 'heroicon-o-check-circle' : 'heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                <span class="text-xs">{{ $col['key'] === 'done' ? 'Noch nichts erledigt' : 'Keine Aufgaben' }}</span>
            </div>
        @endforelse
        @if (($col['total'] ?? 0) > count($col['tasks']))
            <div class="px-2 py-1 text-center text-[11px] text-[color:var(--nx-faint)]">+ {{ $col['total'] - count($col['tasks']) }} ältere im Verlauf</div>
        @endif
    </div>
</div>
