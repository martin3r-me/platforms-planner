@props(['blockType', 'blocks', 'blockTypes'])

@php
    $block = $blocks[$blockType] ?? null;
    $config = $blockTypes[$blockType] ?? [];
    $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $blockType));
    $entries = $block['entries'] ?? [];
    $entryCount = count($entries);
@endphp

<div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] flex flex-col h-full overflow-hidden">
    {{-- Header --}}
    <div class="d-flex items-center justify-between px-3 py-2 border-b border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/50">
        <h4 class="text-xs font-bold text-[var(--ui-secondary)] uppercase tracking-wider truncate">{{ $label }}</h4>
        <x-ui-badge variant="secondary" size="sm">{{ $entryCount }}</x-ui-badge>
    </div>

    {{-- Body --}}
    <div class="flex-grow-1 overflow-y-auto p-2 space-y-1.5" style="max-height: 220px;">
        @if($entryCount > 0)
            @foreach($entries as $entry)
            <div class="p-2 rounded-md bg-[var(--ui-muted-5)]/50 border border-[var(--ui-border)]/30">
                <div class="d-flex items-center gap-1.5">
                    @if(!empty($entry['title']))
                    <div class="text-xs font-semibold text-[var(--ui-secondary)] leading-tight flex-grow-1">{{ $entry['title'] }}</div>
                    @endif
                    @if(!empty($entry['entry_type']) && $entry['entry_type'] !== 'text')
                    <x-ui-badge variant="secondary" size="sm">{{ ucfirst($entry['entry_type']) }}</x-ui-badge>
                    @endif
                </div>
                @if(!empty($entry['content']))
                <div class="text-[11px] text-[var(--ui-muted)] mt-0.5 leading-snug">{{ Str::limit($entry['content'], 120) }}</div>
                @endif
            </div>
            @endforeach
        @else
            <div class="p-3 text-center">
                <span class="text-[11px] text-[var(--ui-muted)] italic">Keine Eintraege</span>
            </div>
        @endif
    </div>
</div>
