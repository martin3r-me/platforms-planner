@props(['task'])

@php
    $isDone = $task->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::COMPLETED;
    $isFrog = $task->is_frog ?? false;
    $userInCharge = $task->userInCharge ?? null;
    $initials = $userInCharge ? mb_strtoupper(mb_substr($userInCharge->name ?? $userInCharge->email ?? 'U', 0, 1)) : null;
    $cardHref = ($publicMode ?? false)
        ? route('planner.public.task', ['token' => $publicToken ?? '', 'task' => $task->id])
        : route('planner.tasks.show', $task) . '?from=' . ($cardFrom ?? 'my-tasks');

    $priorityLabel = $task->priority?->label() ?? null;
    $priorityColor = $task->priority?->color() ?? null;
    $spValue = is_object($task->story_points) ? $task->story_points->points() : $task->story_points;
    $dodProgress = $task->has_dod ? $task->dod_progress : null;
    $isOverdue = $task->due_date && $task->due_date->isPast() && !$isDone;

    // Project name only when not already on the project board
    $showProjectName = ($cardFrom ?? null) !== 'project' && $task->project;

    // MeisterTask-Style: linke Edge ist immer da, Farbe nach Status/Priorität-Hierarchie
    $edgeColor = match (true) {
        $isOverdue           => 'var(--planner-status-overdue)',
        $isDone              => 'var(--planner-col-done)',
        $isFrog              => 'var(--planner-frog)',
        (bool) $priorityColor => $priorityColor,
        (bool) ($task->color ?? null) => $task->color,
        default              => 'var(--planner-status-active)',
    };

    // Due-date phrase
    $duePhrase = null;
    if ($task->due_date) {
        $diff = (int) now()->startOfDay()->diffInDays($task->due_date->copy()->startOfDay(), false);
        if ($diff < 0)      $duePhrase = abs($diff) . 'd zu spät';
        elseif ($diff === 0) $duePhrase = 'heute';
        elseif ($diff === 1) $duePhrase = 'morgen';
        elseif ($diff < 7)   $duePhrase = 'in ' . $diff . 'd';
        else                 $duePhrase = $task->due_date->format('d.m.');
    }

    // Tag preview: first one labelled, rest as +N
    $tagCount = $task->tags?->count() ?? 0;
    $firstTag = $tagCount > 0 ? $task->tags->first() : null;

    // Card bleibt immer weiß. Done signalisiert sich nur über Opazität.
    $surface = $isDone ? 'opacity-60' : '';
@endphp

<x-ui-kanban-card
    :title="''"
    :sortable-id="$task->id"
    :href="$cardHref"
    class="group/card relative {{ $surface }}"
>
    {{-- Vertikales Color-Band links (Spiegel zum Spalten-Top-Band) --}}
    <div
        class="absolute top-2.5 bottom-2.5 left-1.5 w-[3px] rounded-full"
        style="background-color: {{ $edgeColor }};"
    ></div>
    {{-- Optional micro-line: project name (only on cross-project boards) --}}
    @if($showProjectName)
        <div class="text-[10px] text-[var(--ui-muted)] leading-none truncate mb-1 pl-3">
            {{ $task->project->name }}
        </div>
    @endif

    {{-- Title row: title + hover quick-done --}}
    <div class="flex items-start gap-2 pr-6 pl-3">
        <h4 class="text-[13px] font-semibold leading-snug text-[var(--ui-secondary)] m-0 {{ $isDone ? 'line-through text-[var(--ui-muted)]' : '' }}">
            {{ $task->title }}
        </h4>

        {{-- Quick-done (hover only). Klick triggert nur wenn echter Klick — kein Drag-Drop --}}
        @if(!($publicMode ?? false))
            <button
                type="button"
                x-data="{ press: null }"
                @mousedown.stop="press = { x: $event.clientX, y: $event.clientY }"
                @click.stop.prevent="
                    const ok = press && Math.abs($event.clientX - press.x) < 5 && Math.abs($event.clientY - press.y) < 5;
                    press = null;
                    if (ok) $wire.quickToggleDone({{ $task->id }});
                "
                class="absolute top-2 right-2 opacity-0 group-hover/card:opacity-100 transition-opacity inline-flex items-center justify-center w-5 h-5 rounded-full {{ $isDone ? 'bg-[var(--planner-status-done)] text-white' : 'bg-white border border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-[var(--planner-status-done)] hover:text-[var(--planner-status-done)]' }}"
                title="{{ $isDone ? 'Als offen markieren' : 'Als erledigt markieren' }}"
            >
                @svg('heroicon-s-check', 'w-3 h-3')
            </button>
        @endif
    </div>

    {{-- Meta line: due · sp · tag · dod · spacer · postpone · frog · avatar --}}
    @php
        $hasMeta = $duePhrase || $spValue || $firstTag || $dodProgress || ($task->postpone_count ?? 0) > 0 || ($isFrog && $isDone) || $userInCharge;
    @endphp
    @if($hasMeta)
        <div class="mt-2 flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)] leading-none pl-3">
            @if($duePhrase)
                <span
                    class="inline-flex items-center gap-0.5 flex-shrink-0 {{ $isOverdue ? 'text-[var(--planner-status-overdue)] font-medium' : '' }}"
                    title="{{ $task->due_date->format('d.m.Y H:i') }}"
                >
                    @svg('heroicon-o-clock', 'w-3 h-3 opacity-60')
                    <span>{{ $duePhrase }}</span>
                </span>
            @endif

            @if($spValue)
                <span class="flex-shrink-0 tabular-nums" title="Story Points">{{ $spValue }} SP</span>
            @endif

            @if($firstTag)
                @php $tColor = $firstTag->color ?: '#94a3b8'; @endphp
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold text-white flex-shrink-0 truncate max-w-[7rem]"
                    style="background-color: {{ $tColor }};"
                    title="{{ $firstTag->label }}"
                >
                    {{ $firstTag->label }}
                </span>
                @if($tagCount > 1)
                    <span class="inline-flex items-center justify-center min-w-[1.1rem] h-4 px-1 rounded-full text-[9px] font-bold tabular-nums bg-[var(--ui-muted-10)] text-[var(--ui-secondary)] flex-shrink-0">+{{ $tagCount - 1 }}</span>
                @endif
            @endif

            @if($dodProgress)
                <span
                    class="inline-flex items-center gap-1 flex-shrink-0"
                    title="DoD: {{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}"
                >
                    <span class="w-6 h-1 rounded-full bg-[var(--planner-track)] overflow-hidden inline-block">
                        <span
                            class="block h-full rounded-full {{ $dodProgress['isComplete'] ? 'bg-[var(--planner-status-done)]' : 'bg-[var(--planner-track-fill)]' }}"
                            style="width: {{ $dodProgress['percentage'] }}%"
                        ></span>
                    </span>
                    <span class="tabular-nums">{{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}</span>
                </span>
            @endif

            <span class="flex-1"></span>

            @if(($task->postpone_count ?? 0) > 0)
                <span class="flex-shrink-0 inline-flex items-center gap-0.5 tabular-nums" title="Verschoben: {{ $task->postpone_count }}x">
                    @svg('heroicon-o-arrow-path', 'w-3 h-3 opacity-60')
                    {{ $task->postpone_count }}
                </span>
            @endif

            @if($isFrog && $isDone)
                <span class="flex-shrink-0" title="Frosch (erledigt)">🐸</span>
            @endif

            @if($userInCharge)
                <span class="flex-shrink-0 ml-0.5" title="{{ $userInCharge->name ?? $userInCharge->email }}">
                    @if($userInCharge->avatar)
                        <img src="{{ $userInCharge->avatar }}" alt="" class="w-4 h-4 rounded-full object-cover">
                    @else
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-secondary)] text-white text-[9px] font-semibold">{{ $initials }}</span>
                    @endif
                </span>
            @endif
        </div>
    @endif
</x-ui-kanban-card>
