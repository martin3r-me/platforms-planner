@props(['task'])

@php
    $isDone = $task->is_done ?? false;
    $isFrog = $task->is_frog ?? false;
    $contextColor = $task->color ?? null;
    $userInCharge = $task->userInCharge ?? null;
    $initials = $userInCharge ? mb_strtoupper(mb_substr($userInCharge->name ?? $userInCharge->email ?? 'U', 0, 1)) : null;
    $cardHref = ($publicMode ?? false)
        ? route('planner.public.task', ['token' => $publicToken ?? '', 'task' => $task->id])
        : route('planner.tasks.show', $task) . '?from=' . ($cardFrom ?? 'my-tasks');
    $priorityLabel = $task->priority?->label() ?? null;
    $spValue = is_object($task->story_points) ? $task->story_points->points() : $task->story_points;
    $dodProgress = $task->has_dod ? $task->dod_progress : null;
    $isOverdue = $task->due_date && $task->due_date->isPast() && !$isDone;

    // Priority color mapping
    $priorityColor = $task->priority?->color() ?? null;

    // Status-aware background class
    $statusBg = $isOverdue
        ? '!bg-[var(--planner-card-overdue)]'
        : ($isDone
            ? '!bg-[var(--planner-card-done)] opacity-60'
            : ($isFrog
                ? '!bg-[var(--planner-card-frog)]'
                : 'hover:!bg-[var(--planner-card-hover)]'));

    // Top accent color
    $accentColor = $isOverdue
        ? 'var(--planner-status-overdue)'
        : ($isDone
            ? 'var(--planner-status-done)'
            : ($contextColor ?? 'var(--planner-status-active)'));
@endphp
<x-ui-kanban-card
    :title="''"
    :sortable-id="$task->id"
    :href="$cardHref"
    class="group/card relative {{ $statusBg }} transition-all duration-150"
>
    {{-- Top accent bar (2px) --}}
    <div class="absolute top-0 left-0 right-0 h-0.5 rounded-t" style="background-color: {{ $accentColor }}"></div>

    {{-- Hover quick-actions (top right) --}}
    @if(!($publicMode ?? false))
        <div class="absolute top-1.5 right-1.5 opacity-0 group-hover/card:opacity-100 transition-opacity duration-150 z-10" @click.stop>
            <button
                type="button"
                wire:click.prevent.stop="quickToggleDone({{ $task->id }})"
                @click.stop.prevent
                class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $isDone ? 'bg-[var(--planner-status-done)] text-white' : 'bg-white border border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-[var(--planner-status-done)] hover:text-[var(--planner-status-done)]' }} shadow-sm transition-colors"
                title="{{ $isDone ? 'Als offen markieren' : 'Als erledigt markieren' }}"
            >
                @svg('heroicon-s-check', 'w-3.5 h-3.5')
            </button>
        </div>
    @endif

    {{-- Micro line: Project · Due date --}}
    <div class="flex items-center justify-between gap-2 text-[10px] text-[var(--ui-muted)] leading-none mt-1">
        @if($task->project)
            <span class="truncate">{{ $task->project->name }}</span>
        @else
            <span></span>
        @endif
        @if($task->due_date)
            <span class="flex-shrink-0 {{ $isOverdue ? 'text-[var(--planner-status-overdue)] font-semibold' : '' }}" title="{{ $task->due_date->format('d.m.Y H:i') }}">
                {{ $task->due_date->format('d.m.') }}
            </span>
        @endif
    </div>

    {{-- Title with priority dot --}}
    <div class="flex items-start gap-1.5 mt-1">
        @if($priorityColor)
            <span class="flex-shrink-0 w-2 h-2 rounded-full mt-1" style="background-color: {{ $priorityColor }}" title="{{ $priorityLabel }}"></span>
        @endif
        <h4 class="text-[13px] font-medium leading-snug text-[var(--ui-secondary)] m-0 {{ $isDone ? 'line-through text-[var(--ui-muted)]' : '' }}">
            {{ $task->title }}
        </h4>
    </div>

    {{-- Bottom bar: DoD + Frog + Postpone + Avatar --}}
    <div class="flex items-center gap-2 mt-1.5 text-[11px] text-[var(--ui-muted)]">
        {{-- DoD progress --}}
        @if($dodProgress)
            <span class="flex-shrink-0 inline-flex items-center gap-1" title="DoD: {{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}">
                <span class="w-8 h-1.5 bg-[var(--planner-track)] rounded-full overflow-hidden inline-block">
                    <span class="block h-full rounded-full {{ $dodProgress['isComplete'] ? 'bg-[var(--planner-status-done)]' : 'bg-[var(--planner-track-fill)]' }}" style="width: {{ $dodProgress['percentage'] }}%"></span>
                </span>
                <span class="text-[10px]">{{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}</span>
            </span>
        @endif

        {{-- Frog emoji --}}
        @if($isFrog)
            <span class="flex-shrink-0" title="Frosch">🐸</span>
        @endif

        {{-- Postpone count --}}
        @if(($task->postpone_count ?? 0) > 0)
            <span class="flex-shrink-0 inline-flex items-center gap-0.5" title="Verschoben: {{ $task->postpone_count }}x">
                {{ $task->postpone_count }}x↻
            </span>
        @endif

        {{-- Story Points --}}
        @if($spValue)
            <span class="flex-shrink-0 px-1 py-0.5 rounded bg-[var(--ui-muted-5)] font-medium text-[10px]">{{ $spValue }} SP</span>
        @endif

        {{-- Spacer --}}
        <span class="flex-1"></span>

        {{-- Assignee avatar --}}
        @if($userInCharge)
            <span class="flex-shrink-0" title="{{ $userInCharge->name ?? $userInCharge->email }}">
                @if($userInCharge->avatar)
                    <img src="{{ $userInCharge->avatar }}" alt="" class="w-4 h-4 rounded-full object-cover">
                @else
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-muted-10)] text-[9px] font-medium text-[var(--ui-muted)]">{{ $initials }}</span>
                @endif
            </span>
        @endif
    </div>
</x-ui-kanban-card>
