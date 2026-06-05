@php
    $hasTagFilters = $availableFilterTags->isNotEmpty();
    $hasColorFilters = $availableFilterColors->isNotEmpty();
    $shouldRender = $hasTagFilters || $hasColorFilters || $hasActiveFilters;
@endphp

@if($shouldRender)
    <div class="flex items-center gap-1.5 px-4 h-9 border-b border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[11px]">
        <span class="text-[var(--ui-muted)] flex-shrink-0 mr-1">
            @svg('heroicon-o-funnel', 'w-3.5 h-3.5')
        </span>

        {{-- Active tag chips --}}
        @foreach($availableFilterTags->filter(fn($t) => in_array($t['id'], $filterTagIds)) as $tag)
            <button
                type="button"
                wire:click="toggleTagFilter({{ $tag['id'] }})"
                class="inline-flex items-center gap-1 pl-1.5 pr-1 py-0.5 rounded border border-[var(--ui-secondary)]/20 bg-[var(--ui-secondary)]/5 text-[var(--ui-secondary)] hover:border-[var(--ui-danger)]/40 hover:bg-[var(--ui-danger)]/5 hover:text-[var(--ui-danger)] transition-colors group/chip"
                title="Filter entfernen"
            >
                @if($tag['color'])
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></span>
                @endif
                <span>{{ $tag['label'] }}</span>
                @svg('heroicon-o-x-mark', 'w-3 h-3 opacity-50 group-hover/chip:opacity-100')
            </button>
        @endforeach

        {{-- Active color chip --}}
        @if($filterColor)
            <button
                type="button"
                wire:click="toggleColorFilter('{{ $filterColor }}')"
                class="inline-flex items-center gap-1 pl-1 pr-1 py-0.5 rounded border border-[var(--ui-secondary)]/20 bg-[var(--ui-secondary)]/5 text-[var(--ui-secondary)] hover:border-[var(--ui-danger)]/40 hover:bg-[var(--ui-danger)]/5 hover:text-[var(--ui-danger)] transition-colors group/chip"
                title="Filter entfernen"
            >
                <span class="w-3 h-3 rounded-full flex-shrink-0 border border-white/60 ring-1 ring-[var(--ui-border)]/40" style="background-color: {{ $filterColor }}"></span>
                @svg('heroicon-o-x-mark', 'w-3 h-3 opacity-50 group-hover/chip:opacity-100')
            </button>
        @endif

        {{-- + Tag popover --}}
        @if($hasTagFilters)
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-dashed border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition-colors"
                >
                    @svg('heroicon-o-plus', 'w-3 h-3')
                    <span>Tag</span>
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    class="absolute top-full left-0 mt-1 w-56 bg-white border border-[var(--ui-border)] rounded-lg shadow-lg z-30 p-2"
                >
                    <div class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)] px-1 mb-1">Tags</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($availableFilterTags as $tag)
                            <button
                                type="button"
                                wire:click="toggleTagFilter({{ $tag['id'] }})"
                                @click="open = false"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border transition-colors
                                    {{ in_array($tag['id'], $filterTagIds)
                                        ? 'bg-[var(--ui-secondary)] text-white border-[var(--ui-secondary)]'
                                        : 'bg-transparent text-[var(--ui-secondary)] border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/60' }}"
                            >
                                @if($tag['color'])
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></span>
                                @endif
                                <span>{{ $tag['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- + Farbe popover --}}
        @if($hasColorFilters)
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-dashed border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition-colors"
                >
                    @svg('heroicon-o-plus', 'w-3 h-3')
                    <span>Farbe</span>
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    class="absolute top-full left-0 mt-1 w-auto bg-white border border-[var(--ui-border)] rounded-lg shadow-lg z-30 p-2"
                >
                    <div class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)] px-1 mb-1.5">Farben</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($availableFilterColors as $color)
                            <button
                                type="button"
                                wire:click="toggleColorFilter('{{ $color }}')"
                                @click="open = false"
                                class="w-5 h-5 rounded-full border-2 transition-all
                                    {{ $filterColor === $color
                                        ? 'border-[var(--ui-primary)] ring-2 ring-[var(--ui-primary)]/30'
                                        : 'border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/60' }}"
                                style="background-color: {{ $color }}"
                                title="{{ $color }}"
                            ></button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Reset --}}
        @if($hasActiveFilters)
            <button
                type="button"
                wire:click="clearFilters"
                class="ml-auto inline-flex items-center gap-1 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
            >
                @svg('heroicon-o-x-mark', 'w-3 h-3')
                <span>Reset</span>
            </button>
        @endif
    </div>
@endif
