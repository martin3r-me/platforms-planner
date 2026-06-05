@php
    $total = ($openCount ?? 0) + ($doneCount ?? 0);
    $donePct = $total > 0 ? round(($doneCount / $total) * 100) : 0;
    $subline = collect([
        $project->project_type?->value,
        'seit ' . $project->created_at->format('d.m.Y'),
    ])->filter()->implode(' · ');
@endphp

<div class="px-4 pt-3 pb-2 border-b border-[var(--ui-border)]/40 bg-[var(--ui-surface)]">
    <div class="flex items-start justify-between gap-6">
        {{-- Left: Title + subline --}}
        <div class="min-w-0 flex-1">
            <h1 class="text-base font-semibold text-[var(--ui-secondary)] truncate m-0 leading-tight">
                {{ $project->name }}
            </h1>
            @if($subline || (isset($linkedEntities) && $linkedEntities->isNotEmpty()))
                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-[var(--ui-muted)] min-w-0">
                    @if($subline)
                        <span class="truncate">{{ $subline }}</span>
                    @endif
                    @if(isset($linkedEntities) && $linkedEntities->isNotEmpty())
                        <span class="text-[var(--ui-border)]">·</span>
                        <div class="flex items-center gap-1 flex-wrap min-w-0">
                            @foreach($linkedEntities as $entity)
                                <span class="inline-flex items-center gap-1 text-[var(--ui-secondary)]">
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
            <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-active)]"></span>
                <span class="font-semibold tabular-nums">{{ $openCount ?? 0 }}</span>
                <span class="text-[var(--ui-muted)]">offen</span>
            </span>

            @if(($overdueCount ?? 0) > 0)
                <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-overdue)]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-overdue)]"></span>
                    <span class="font-semibold tabular-nums">{{ $overdueCount }}</span>
                    <span>überfällig</span>
                </span>
            @endif

            <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-done)]"></span>
                <span class="font-semibold tabular-nums">{{ $doneCount ?? 0 }}</span>
                <span class="text-[var(--ui-muted)]">erledigt</span>
            </span>

            {{-- Progress --}}
            @if($total > 0)
                <span class="inline-flex items-center gap-2">
                    <span class="text-[var(--ui-muted)] tabular-nums">{{ $donePct }}%</span>
                    <span class="w-24 h-1 rounded-full bg-[var(--planner-track)] overflow-hidden">
                        <span class="block h-full rounded-full bg-[var(--planner-status-done)] transition-all duration-300" style="width: {{ $donePct }}%"></span>
                    </span>
                </span>
            @endif
        </div>
    </div>
</div>
