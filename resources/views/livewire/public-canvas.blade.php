@php
    $statusBadge = match($canvas->status) {
        'open' => 'bg-[var(--nx-info)]/10 text-[color:var(--nx-info)]',
        'completed' => 'bg-[var(--nx-success)]/10 text-[color:var(--nx-success)]',
        'discarded' => 'bg-[color:var(--nx-line)] text-[color:var(--nx-muted)]',
        default => 'bg-[color:var(--nx-line)] text-[color:var(--nx-muted)]',
    };
    $statusLabel = \Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$canvas->status] ?? $canvas->status;
@endphp

<div class="h-screen flex flex-col overflow-hidden bg-[var(--nx-bg)]">
    {{-- Shared Nav --}}
    @include('planner::livewire.partials.public-nav', [
        'project' => $project,
        'canvases' => $siblingCanvases,
        'current' => 'canvas:' . $canvas->id,
    ])

    {{-- Sub action bar (canvas-specific) --}}
    <div class="flex-shrink-0 bg-[color:var(--nx-surface)] border-b border-[var(--nx-line)]">
        <div class="px-4 sm:px-6 py-2 flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $statusBadge }}">
                {{ $statusLabel }}
            </span>
            @if($canvas->createdByUser?->name)
                <span class="inline-flex items-center gap-1 text-xs text-[var(--nx-muted)]">
                    @svg('heroicon-o-user', 'w-3.5 h-3.5')
                    {{ $canvas->createdByUser->name }}
                </span>
            @endif
            @if($canvas->created_at)
                <span class="inline-flex items-center gap-1 text-xs text-[var(--nx-muted)]">
                    @svg('heroicon-o-calendar', 'w-3.5 h-3.5')
                    {{ $canvas->created_at->format('d.m.Y') }}
                </span>
            @endif
        </div>

        @if($canvas->description)
            <div class="px-4 sm:px-6 pb-2 -mt-1">
                <p class="text-xs text-[color:var(--nx-muted)] leading-relaxed">{{ $canvas->description }}</p>
            </div>
        @endif
    </div>

    {{-- Main --}}
    <main class="flex-1 min-h-0 overflow-y-auto">
        <div class="p-4 sm:p-6 space-y-6 max-w-6xl mx-auto">
            @foreach($blockDefs as $i => $def)
                <div id="block-{{ $def['key'] }}">
                    @include('planner::livewire.project-canvas._block', [
                        'blockKey' => $def['key'],
                        'blocks' => $canvasData['blocks'],
                        'blockDefs' => $blockDefs,
                        'blockIndex' => $i,
                    ])
                </div>
            @endforeach
        </div>
    </main>
</div>
