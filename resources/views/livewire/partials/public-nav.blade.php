@props([
    'project',
    'canvases' => collect(),
    'current' => 'board',
    'taskCount' => null,
])

@php
    $boardActive = $current === 'board';
    $tabBase = 'group inline-flex items-center gap-1.5 px-3 py-2.5 text-[13px] font-medium transition-colors whitespace-nowrap border-b-2 -mb-px';
    $tabActive = 'border-[#f2ca52] text-[#1a1a2e]';
    $tabIdle = 'border-transparent text-[var(--ui-muted,#64748b)] hover:text-[#1a1a2e] hover:border-gray-200';

    $statusDot = fn ($status) => match($status) {
        'open' => 'bg-blue-400',
        'completed' => 'bg-green-500',
        'discarded' => 'bg-gray-300',
        default => 'bg-gray-300',
    };
@endphp

<header class="flex-shrink-0 bg-white border-b border-[var(--ui-border,#e2e8f0)]">
    <div class="px-4 sm:px-6 pt-4">
        {{-- Title row --}}
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-semibold text-[var(--ui-secondary,#1e293b)] truncate">
                        {{ $project->name }}
                    </h1>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-50 text-blue-600 border border-blue-100">
                        @svg('heroicon-o-globe-alt', 'w-3 h-3')
                        <span>Öffentlich geteilt</span>
                    </span>
                </div>
                @if($project->description)
                    <p class="mt-0.5 text-xs text-[var(--ui-muted,#64748b)] truncate max-w-3xl">
                        {{ $project->description }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Tab strip --}}
        <nav class="mt-3 flex items-center gap-0 overflow-x-auto border-b border-transparent">
            {{-- Board tab --}}
            <a
                href="{{ route('planner.public.show', $project->public_token) }}"
                class="{{ $tabBase }} {{ $boardActive ? $tabActive : $tabIdle }}"
            >
                @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                <span>Board</span>
                @if(!is_null($taskCount))
                    <span class="ml-1 inline-flex items-center justify-center min-w-[18px] px-1.5 rounded-full text-[10px] font-semibold {{ $boardActive ? 'bg-[#f2ca52]/20 text-[#1a1a2e]' : 'bg-gray-100 text-gray-500' }}">
                        {{ $taskCount }}
                    </span>
                @endif
            </a>

            {{-- Canvas tabs --}}
            @foreach($canvases as $c)
                @php $active = $current === ('canvas:' . $c->id); @endphp
                <a
                    href="{{ route('planner.public.canvas', ['token' => $project->public_token, 'canvas' => $c->id]) }}"
                    class="{{ $tabBase }} {{ $active ? $tabActive : $tabIdle }}"
                >
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4')
                    <span class="max-w-[180px] truncate">{{ $c->name }}</span>
                    <span class="inline-block w-1.5 h-1.5 rounded-full {{ $statusDot($c->status) }}"></span>
                </a>
            @endforeach
        </nav>
    </div>
</header>
