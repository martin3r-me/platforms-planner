@php
    $total = ($openCount ?? 0) + ($doneCount ?? 0);
    $donePct = $total > 0 ? round(($doneCount / $total) * 100) : 0;
    $subline = collect([
        $project->project_type?->value,
        'seit ' . $project->created_at->format('d.m.Y'),
    ])->filter()->implode(' · ');

    // Canvas-Status: missing | red | yellow | green | unknown
    $canvasStatusTokens = [
        'green'   => ['color' => 'var(--nx-success)', 'bg' => 'rgba(47,158,68,0.10)',  'label' => 'OK'],
        'yellow'  => ['color' => 'var(--nx-warning)',                     'bg' => 'rgba(232,89,12,0.10)',  'label' => 'Lücken'],
        'red'     => ['color' => 'var(--nx-danger)','bg' => 'rgba(224,49,49,0.10)', 'label' => 'Kritisch'],
        'missing' => ['color' => 'var(--nx-muted)',              'bg' => 'var(--nx-bg)',    'label' => 'Fehlt'],
        'unknown' => ['color' => 'var(--nx-muted)',              'bg' => 'var(--nx-bg)',    'label' => '–'],
    ];
    $cs = $canvasStatusTokens[$canvasInfo['status'] ?? 'unknown'] ?? $canvasStatusTokens['unknown'];
@endphp

<div class="px-4 pt-3 pb-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)]">
    <div class="flex items-start justify-between gap-6">
        {{-- Left: Title + subline --}}
        <div class="min-w-0 flex-1">
            <h1 class="text-base font-semibold text-[var(--nx-text)] truncate m-0 leading-tight">
                {{ $project->name }}
            </h1>
            @if($subline || (isset($linkedEntities) && $linkedEntities->isNotEmpty()))
                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-[var(--nx-muted)] min-w-0">
                    @if($subline)
                        <span class="truncate">{{ $subline }}</span>
                    @endif
                    @if(isset($linkedEntities) && $linkedEntities->isNotEmpty())
                        <span class="text-[color:var(--nx-faint)]">·</span>
                        <div class="flex items-center gap-1 flex-wrap min-w-0">
                            @foreach($linkedEntities as $entity)
                                <span class="inline-flex items-center gap-1 text-[var(--nx-text)]">
                                    @svg('heroicon-o-link', 'w-3 h-3 opacity-60')
                                    <span class="truncate">{{ $entity['entity_name'] }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Right: live metrics --}}
        <div class="flex items-center gap-4 flex-shrink-0 text-[11px]">
            <span class="inline-flex items-center gap-1.5 text-[var(--nx-text)]">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--nx-info)]"></span>
                <span class="font-semibold tabular-nums">{{ $openCount ?? 0 }}</span>
                <span class="text-[var(--nx-muted)]">offen</span>
            </span>

            @if(($overdueCount ?? 0) > 0)
                <span class="inline-flex items-center gap-1.5 text-[var(--nx-danger)]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--nx-danger)]"></span>
                    <span class="font-semibold tabular-nums">{{ $overdueCount }}</span>
                    <span>überfällig</span>
                </span>
            @endif

            <span class="inline-flex items-center gap-1.5 text-[var(--nx-text)]">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--nx-success)]"></span>
                <span class="font-semibold tabular-nums">{{ $doneCount ?? 0 }}</span>
                <span class="text-[var(--nx-muted)]">erledigt</span>
            </span>

            {{-- Progress --}}
            @if($total > 0)
                <span class="inline-flex items-center gap-2">
                    <span class="text-[var(--nx-muted)] tabular-nums">{{ $donePct }}%</span>
                    <span class="w-24 h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                        <span class="block h-full rounded-full bg-[var(--nx-success)] transition-all duration-300" style="width: {{ $donePct }}%"></span>
                    </span>
                </span>
            @endif

            {{-- Canvas-Chip: prominent, status-farbig, klickbar --}}
            @if(isset($canvasInfo))
                @if($canvasInfo['exists'] && $canvasInfo['route'])
                    <a
                        href="{{ $canvasInfo['route'] }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 pl-2 pr-2.5 py-1 rounded-md border transition-all hover:shadow-sm hover:-translate-y-px"
                        style="border-color: {{ $cs['color'] }}33; background-color: {{ $cs['bg'] }}; color: {{ $cs['color'] }};"
                        title="Project Canvas öffnen"
                    >
                        @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5')
                        <span class="font-medium">Canvas</span>
                        @if($canvasInfo['completeness'] !== null)
                            <span class="tabular-nums font-semibold">{{ $canvasInfo['completeness'] }}%</span>
                        @endif
                        @if($canvasInfo['warnings_count'] > 0)
                            <span class="inline-flex items-center gap-0.5 pl-1.5 border-l border-current/20">
                                @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                <span class="tabular-nums">{{ $canvasInfo['warnings_count'] }}</span>
                            </span>
                        @endif
                    </a>
                @else
                    <button
                        type="button"
                        wire:click="openCanvas"
                        class="inline-flex items-center gap-1.5 pl-2 pr-2.5 py-1 rounded-md border border-dashed transition-all hover:shadow-sm"
                        style="border-color: {{ $cs['color'] }}66; color: {{ $cs['color'] }};"
                        title="Project Canvas anlegen"
                    >
                        @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5')
                        <span>Canvas anlegen</span>
                    </button>
                @endif
            @endif
        </div>
    </div>
</div>
