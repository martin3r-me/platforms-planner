@props(['task'])

@php
    $isDone = $task->is_done ?? false;
    $isFrog = $task->is_frog ?? false;
    $contextColor = $task->color ?? null;
    $userInCharge = $task->userInCharge ?? null;
    $initials = $userInCharge ? mb_strtoupper(mb_substr($userInCharge->name ?? $userInCharge->email ?? 'U', 0, 1)) : null;
    $usePanel = $usePanel ?? false;
    $cardHref = ($publicMode ?? false)
        ? route('planner.public.task', ['token' => $publicToken ?? '', 'task' => $task->id])
        : ($usePanel ? null : route('planner.tasks.show', $task));
    $priorityIcon = $task->priority?->icon() ?? null;
    $priorityLabel = $task->priority?->label() ?? null;
    $spValue = is_object($task->story_points) ? $task->story_points->points() : $task->story_points;
    $dodProgress = $task->has_dod ? $task->dod_progress : null;
    $isOverdue = $task->due_date && $task->due_date->isPast() && !$isDone;
@endphp
@if($usePanel)
<div
    x-data
    x-on:click.prevent="$dispatch('openTaskPanel', { taskId: {{ $task->id }} })"
    style="cursor: pointer;"
>
@endif
<x-ui-kanban-card
    :title="''"
    :sortable-id="$task->id"
    :href="$cardHref"
    class="relative"
>
    {{-- Subtle left color edge --}}
    @if($contextColor)
        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l" style="background-color: {{ $contextColor }}"></div>
    @endif

    {{-- Title (prominent, first element) --}}
    <div class="mb-2 {{ $contextColor ? 'pl-2' : '' }}">
        <h4 class="text-sm font-semibold leading-snug text-[var(--ui-secondary)] m-0 {{ $isDone ? 'line-through text-[var(--ui-muted)]' : '' }}">
            {{ $task->title }}
        </h4>
    </div>

    {{-- Single-line compact metadata row --}}
    <div class="flex items-center gap-2 text-[11px] text-[var(--ui-muted)] {{ $contextColor ? 'pl-2' : '' }}">
        {{-- Priority icon --}}
        @if($priorityIcon)
            <span class="flex-shrink-0" title="{{ $priorityLabel }}">{{ $priorityIcon }}</span>
        @endif

        {{-- Frog icon --}}
        @if($isFrog)
            <span class="flex-shrink-0 text-[var(--ui-warning)]" title="Frosch">
                @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')
            </span>
        @endif

        {{-- Assignee avatar (4x4) --}}
        @if($userInCharge)
            <span class="flex-shrink-0" title="{{ $userInCharge->name ?? $userInCharge->email }}">
                @if($userInCharge->avatar)
                    <img src="{{ $userInCharge->avatar }}" alt="" class="w-4 h-4 rounded-full object-cover">
                @else
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-muted-10)] text-[9px] font-medium text-[var(--ui-muted)]">{{ $initials }}</span>
                @endif
            </span>
        @endif

        {{-- Due date badge --}}
        @if($task->due_date)
            <span class="flex-shrink-0 inline-flex items-center gap-0.5 {{ $isOverdue ? 'text-[var(--ui-danger)] font-medium' : '' }}" title="{{ $task->due_date->format('d.m.Y H:i') }}">
                {{ $task->due_date->format('d.m.') }}
                @if($task->postpone_count > 0)
                    <span title="Verschoben: {{ $task->postpone_count }}x">
                        @svg('heroicon-o-arrow-path', 'w-3 h-3')
                    </span>
                @endif
            </span>
        @endif

        {{-- Story Points badge --}}
        @if($spValue)
            <span class="flex-shrink-0 px-1 py-0.5 rounded bg-[var(--ui-muted-5)] font-medium text-[10px]">{{ $spValue }} SP</span>
        @endif

        {{-- DoD progress (mini) --}}
        @if($dodProgress)
            <span class="flex-shrink-0 inline-flex items-center gap-1" title="DoD: {{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}">
                <span class="w-8 h-1 bg-[var(--ui-muted-10)] rounded-full overflow-hidden inline-block">
                    <span class="block h-full {{ $dodProgress['isComplete'] ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }}" style="width: {{ $dodProgress['percentage'] }}%"></span>
                </span>
                <span class="text-[10px]">{{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}</span>
            </span>
        @endif
    </div>
</x-ui-kanban-card>
@if($usePanel)
</div>
@endif
