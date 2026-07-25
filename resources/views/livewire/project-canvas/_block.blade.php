@props(['blockKey', 'blocks', 'blockDefs', 'blockIndex' => null])

@php
    $block = $blocks[$blockKey] ?? null;
    $config = collect($blockDefs)->firstWhere('key', $blockKey) ?? [];
    $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $blockKey));
    $entries = $block['entries'] ?? [];
    $entryCount = count($entries);

    // Sticky-note color rotation
    $colorIndex = $blockIndex ?? collect($blockDefs)->search(fn($d) => ($d['key'] ?? '') === $blockKey);
    $colorIndex = $colorIndex !== false ? $colorIndex % 8 : 0;
    // Sticky-Farben aus der zentralen nx-Tone-Palette (gedämpft, kalm) — 8 rotierende Töne
    $stickyColors = [
        ['bg' => 'bg-[var(--nx-tone-amber)]/10',   'border' => 'border-[var(--nx-tone-amber)]/30',   'header' => 'bg-[var(--nx-tone-amber)]/15'],
        ['bg' => 'bg-[var(--nx-tone-sky)]/10',      'border' => 'border-[var(--nx-tone-sky)]/30',     'header' => 'bg-[var(--nx-tone-sky)]/15'],
        ['bg' => 'bg-[var(--nx-tone-emerald)]/10',  'border' => 'border-[var(--nx-tone-emerald)]/30', 'header' => 'bg-[var(--nx-tone-emerald)]/15'],
        ['bg' => 'bg-[var(--nx-tone-pink)]/10',     'border' => 'border-[var(--nx-tone-pink)]/30',    'header' => 'bg-[var(--nx-tone-pink)]/15'],
        ['bg' => 'bg-[var(--nx-tone-violet)]/10',   'border' => 'border-[var(--nx-tone-violet)]/30',  'header' => 'bg-[var(--nx-tone-violet)]/15'],
        ['bg' => 'bg-[var(--nx-tone-indigo)]/10',   'border' => 'border-[var(--nx-tone-indigo)]/30',  'header' => 'bg-[var(--nx-tone-indigo)]/15'],
        ['bg' => 'bg-[var(--nx-tone-teal)]/10',     'border' => 'border-[var(--nx-tone-teal)]/30',    'header' => 'bg-[var(--nx-tone-teal)]/15'],
        ['bg' => 'bg-[var(--nx-tone-rose)]/10',     'border' => 'border-[var(--nx-tone-rose)]/30',    'header' => 'bg-[var(--nx-tone-rose)]/15'],
    ];
    $c = $stickyColors[$colorIndex];
@endphp

<div class="w-full rounded-2xl border-2 {{ $c['border'] }} {{ $c['bg'] }} flex flex-col overflow-hidden">
    {{-- Header --}}
    <div class="flex items-center justify-between px-5 py-3 border-b {{ $c['border'] }} {{ $c['header'] }}">
        <h4 class="text-base font-bold text-[color:var(--nx-text)] truncate">{{ $label }}</h4>
        <span class="text-[10px] font-semibold text-[color:var(--nx-muted)] bg-[color:var(--nx-surface)]/60 rounded-full px-2 py-0.5">{{ $entryCount }}</span>
    </div>

    {{-- Body --}}
    <div class="grow p-4 space-y-2">
        @if($entryCount > 0)
            @foreach($entries as $entry)
            <div class="p-2.5 rounded-xl bg-[color:var(--nx-surface)]/80 border border-[color:var(--nx-line)] hover:shadow-[var(--nx-shadow-card)] transition-shadow">
                <div class="flex items-start gap-2">
                    <div class="grow min-w-0">
                        @if(!empty($entry['title']))
                        <div class="text-xs font-semibold text-[color:var(--nx-text)] leading-tight">{{ $entry['title'] }}</div>
                        @endif
                        @if(!empty($entry['content']))
                        <div class="text-[11px] text-[color:var(--nx-muted)] mt-1 leading-relaxed whitespace-pre-line">{{ $entry['content'] }}</div>
                        @endif
                    </div>
                    @if(($entry['entry_type'] ?? 'text') !== 'text')
                    <span class="shrink-0 text-[9px] font-medium text-[color:var(--nx-faint)] bg-[color:var(--nx-surface)] rounded px-1.5 py-0.5 uppercase tracking-wide">{{ $entry['entry_type'] }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        @else
            <div class="py-6 text-center">
                <span class="text-[11px] text-[color:var(--nx-faint)] italic">Keine Eintr&auml;ge</span>
            </div>
        @endif
    </div>
</div>
