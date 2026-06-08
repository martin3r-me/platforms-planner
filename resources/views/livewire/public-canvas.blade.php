@php
    $statusBadge = match($canvas->status) {
        'open' => 'bg-blue-100 text-blue-700',
        'completed' => 'bg-green-100 text-green-700',
        'discarded' => 'bg-gray-100 text-gray-500',
        default => 'bg-gray-100 text-gray-600',
    };
    $statusLabel = \Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$canvas->status] ?? $canvas->status;
@endphp

<div class="h-screen flex flex-col overflow-hidden bg-[var(--ui-bg,#f8fafc)]">
    {{-- Shared Nav --}}
    @include('planner::livewire.partials.public-nav', [
        'project' => $project,
        'canvases' => $siblingCanvases,
        'current' => 'canvas:' . $canvas->id,
    ])

    {{-- Sub action bar (canvas-specific) --}}
    <div class="flex-shrink-0 bg-white border-b border-[var(--ui-border,#e2e8f0)]">
        <div class="px-4 sm:px-6 py-2 flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $statusBadge }}">
                {{ $statusLabel }}
            </span>
            @if($canvas->createdByUser?->name)
                <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-muted,#64748b)]">
                    @svg('heroicon-o-user', 'w-3.5 h-3.5')
                    {{ $canvas->createdByUser->name }}
                </span>
            @endif
            @if($canvas->created_at)
                <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-muted,#64748b)]">
                    @svg('heroicon-o-calendar', 'w-3.5 h-3.5')
                    {{ $canvas->created_at->format('d.m.Y') }}
                </span>
            @endif
        </div>

        @if($canvas->description)
            <div class="px-4 sm:px-6 pb-2 -mt-1">
                <p class="text-xs text-gray-500 leading-relaxed">{{ $canvas->description }}</p>
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
