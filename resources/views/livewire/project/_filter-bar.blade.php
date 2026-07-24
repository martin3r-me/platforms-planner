@php
    $hasTagFilters = $availableFilterTags->isNotEmpty();
    $hasColorFilters = $availableFilterColors->isNotEmpty();
    $shouldRender = $hasTagFilters || $hasColorFilters || $hasActiveFilters;
@endphp

@if($shouldRender)
    <div class="flex items-center gap-1.5 px-4 h-9 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-[11px]">
        <span class="text-[var(--nx-muted)] flex-shrink-0 mr-1">
            @svg('heroicon-o-funnel', 'w-3.5 h-3.5')
        </span>

        {{-- Active tag chips --}}
        @foreach($availableFilterTags->filter(fn($t) => in_array($t['id'], $filterTagIds)) as $tag)
            <button
                type="button"
                wire:click="toggleTagFilter({{ $tag['id'] }})"
                class="inline-flex items-center gap-1 pl-1.5 pr-1 py-0.5 rounded border border-[var(--nx-text)]/20 bg-[var(--nx-text)]/5 text-[var(--nx-text)] hover:border-[var(--nx-danger)]/40 hover:bg-[var(--nx-danger)]/5 hover:text-[var(--nx-danger)] transition-colors group/chip"
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
                class="inline-flex items-center gap-1 pl-1 pr-1 py-0.5 rounded border border-[var(--nx-text)]/20 bg-[var(--nx-text)]/5 text-[var(--nx-text)] hover:border-[var(--nx-danger)]/40 hover:bg-[var(--nx-danger)]/5 hover:text-[var(--nx-danger)] transition-colors group/chip"
                title="Filter entfernen"
            >
                <span class="w-3 h-3 rounded-full flex-shrink-0 border border-[color:var(--nx-line-strong)]/60 ring-1 ring-[var(--nx-line-strong)]/40" style="background-color: {{ $filterColor }}"></span>
                @svg('heroicon-o-x-mark', 'w-3 h-3 opacity-50 group-hover/chip:opacity-100')
            </button>
        @endif

        {{-- + Tag popover --}}
        @if($hasTagFilters)
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-dashed border-[var(--nx-line-strong)] text-[var(--nx-muted)] hover:border-[var(--nx-accent)] hover:text-[var(--nx-accent)] transition-colors"
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
                    class="absolute top-full left-0 mt-1 w-56 bg-[color:var(--nx-surface)] border border-[var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)] z-30 p-2"
                >
                    <div class="text-[10px] uppercase tracking-wide text-[var(--nx-muted)] px-1 mb-1">Tags</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($availableFilterTags as $tag)
                            <button
                                type="button"
                                wire:click="toggleTagFilter({{ $tag['id'] }})"
                                @click="open = false"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border transition-colors
                                    {{ in_array($tag['id'], $filterTagIds)
                                        ? 'bg-[var(--nx-text)] text-white border-[var(--nx-text)]'
                                        : 'bg-transparent text-[var(--nx-text)] border-[color:var(--nx-line)] hover:border-[var(--nx-accent)]/60' }}"
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
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-dashed border-[var(--nx-line-strong)] text-[var(--nx-muted)] hover:border-[var(--nx-accent)] hover:text-[var(--nx-accent)] transition-colors"
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
                    class="absolute top-full left-0 mt-1 w-auto bg-[color:var(--nx-surface)] border border-[var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)] z-30 p-2"
                >
                    <div class="text-[10px] uppercase tracking-wide text-[var(--nx-muted)] px-1 mb-1.5">Farben</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($availableFilterColors as $color)
                            <button
                                type="button"
                                wire:click="toggleColorFilter('{{ $color }}')"
                                @click="open = false"
                                class="w-5 h-5 rounded-full border-2 transition-all
                                    {{ $filterColor === $color
                                        ? 'border-[var(--nx-accent)] ring-2 ring-[var(--nx-accent)]/30'
                                        : 'border-[color:var(--nx-line)] hover:border-[var(--nx-accent)]/60' }}"
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
                class="ml-auto inline-flex items-center gap-1 text-[var(--nx-muted)] hover:text-[var(--nx-danger)] transition-colors"
            >
                @svg('heroicon-o-x-mark', 'w-3 h-3')
                <span>Reset</span>
            </button>
        @endif
    </div>
@endif
