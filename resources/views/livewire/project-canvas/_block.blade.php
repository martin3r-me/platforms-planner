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
    $stickyColors = [
        ['bg' => 'bg-yellow-50', 'border' => 'border-yellow-300', 'header' => 'bg-[#f2ca52]/20'],
        ['bg' => 'bg-blue-50', 'border' => 'border-blue-300', 'header' => 'bg-blue-100'],
        ['bg' => 'bg-green-50', 'border' => 'border-green-300', 'header' => 'bg-green-100'],
        ['bg' => 'bg-pink-50', 'border' => 'border-pink-300', 'header' => 'bg-pink-100'],
        ['bg' => 'bg-purple-50', 'border' => 'border-purple-300', 'header' => 'bg-purple-100'],
        ['bg' => 'bg-orange-50', 'border' => 'border-orange-300', 'header' => 'bg-orange-100'],
        ['bg' => 'bg-teal-50', 'border' => 'border-teal-300', 'header' => 'bg-teal-100'],
        ['bg' => 'bg-rose-50', 'border' => 'border-rose-300', 'header' => 'bg-rose-100'],
    ];
    $c = $stickyColors[$colorIndex];
@endphp

<div class="w-full rounded-2xl border-2 {{ $c['border'] }} {{ $c['bg'] }} flex flex-col overflow-hidden">
    {{-- Header --}}
    <div class="flex items-center justify-between px-5 py-3 border-b {{ $c['border'] }} {{ $c['header'] }}">
        <h4 class="text-base font-bold text-[#1a1a2e] truncate">{{ $label }}</h4>
        <span class="text-[10px] font-semibold text-gray-500 bg-white/60 rounded-full px-2 py-0.5">{{ $entryCount }}</span>
    </div>

    {{-- Body --}}
    <div class="grow p-4 space-y-2">
        @if($entryCount > 0)
            @foreach($entries as $entry)
            <div class="p-2.5 rounded-xl bg-white/80 border border-white hover:shadow-sm transition-shadow">
                <div class="flex items-start gap-2">
                    <div class="grow min-w-0">
                        @if(!empty($entry['title']))
                        <div class="text-xs font-semibold text-[#1a1a2e] leading-tight">{{ $entry['title'] }}</div>
                        @endif
                        @if(!empty($entry['content']))
                        <div class="text-[11px] text-gray-500 mt-1 leading-relaxed whitespace-pre-line">{{ $entry['content'] }}</div>
                        @endif
                    </div>
                    @if(($entry['entry_type'] ?? 'text') !== 'text')
                    <span class="shrink-0 text-[9px] font-medium text-gray-400 bg-white rounded px-1.5 py-0.5 uppercase tracking-wide">{{ $entry['entry_type'] }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        @else
            <div class="py-6 text-center">
                <span class="text-[11px] text-gray-400 italic">Keine Eintr&auml;ge</span>
            </div>
        @endif
    </div>
</div>
