@props(['task'])

@php
    $isDone = $task->is_done ?? false;
    $isFrog = $task->is_frog ?? false;
    $contextColor = $task->color ?? null;
    // HasTags Trait stellt contextTags Accessor bereit (nutzt eager-geladene tags Relation)
    $contextTags = $task->contextTags ?? collect();
@endphp
<x-ui-kanban-card
    :title="''"
    :sortable-id="$task->id"
    :href="route('planner.tasks.show', $task)"
>
    <!-- Kontext Farbe und Tags (ganz oben, schlicht) -->
    @if($contextColor || $contextTags->isNotEmpty())
        <div class="mb-2 flex items-center gap-1.5 flex-wrap">
            @if($contextColor)
                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $contextColor }}"></span>
            @endif
            @foreach($contextTags->take(3) as $tag)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/30">
                    @if($tag->color)
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $tag->color }}"></span>
                    @endif
                    {{ $tag->label }}
                </span>
            @endforeach
            @if($contextTags->count() > 3)
                <span class="text-[10px] text-[var(--ui-muted)]">+{{ $contextTags->count() - 3 }}</span>
            @endif
        </div>
    @endif

    <!-- Story Points, Frosch und Datum (oben in eigene Zeile) -->
    @if($isFrog || $task->story_points || $task->due_date)
        <div class="mb-3 flex items-start justify-between gap-2">
            <div class="flex items-start gap-2">
                @if($isFrog)
                    <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)]">
                        @svg('heroicon-o-exclamation-triangle','w-3 h-3 mt-0.5')
                        <span>Frosch</span>
                    </span>
                @endif
                @if($task->story_points)
                    <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)]">
                        @svg('heroicon-o-sparkles','w-3 h-3 mt-0.5')
                        <span>SP {{ is_object($task->story_points) ? ($task->story_points->points() ?? $task->story_points) : $task->story_points }}</span>
                    </span>
                @endif
            </div>
            @if($task->due_date)
                <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-muted)]">
                    <span>{{ $task->due_date->format('d.m.Y') }}</span>
                    @if($task->postpone_count > 0)
                        <span class="inline-flex items-center gap-0.5" title="Verschoben: {{ $task->postpone_count }}x">
                            @svg('heroicon-o-arrow-path','w-3 h-3')
                            <span>{{ $task->postpone_count }}</span>
                        </span>
                    @endif
                </span>
            @endif
        </div>
    @endif

    <!-- Verantwortlicher (eigene Zeile) -->
    @php
        $userInCharge = $task->userInCharge ?? null;
        $initials = $userInCharge ? mb_strtoupper(mb_substr($userInCharge->name ?? $userInCharge->email ?? 'U', 0, 1)) : null;
    @endphp
    @if($userInCharge)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)] min-w-0">
                @if($userInCharge->avatar)
                    <img src="{{ $userInCharge->avatar }}" alt="{{ $userInCharge->name ?? $userInCharge->email }}" class="w-3.5 h-3.5 rounded-full object-cover mt-0.5">
                @else
                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[10px] text-[var(--ui-muted)] mt-0.5">{{ $initials }}</span>
                @endif
                <span class="truncate max-w-[7rem]">{{ $userInCharge->name ?? $userInCharge->email }}</span>
            </span>
        </div>
    @endif

    <!-- Titel (durchgestrichen wenn erledigt) -->
    <div class="mb-4">
        <h4 class="text-sm font-medium text-[var(--ui-secondary)] m-0 {{ $isDone ? 'line-through text-[var(--ui-muted)]' : '' }}">
            {{ $task->title }}
        </h4>
    </div>

    <!-- Meta: Team -->
    @if($task->team)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)]">
                @svg('heroicon-o-user-group','w-3 h-3 mt-0.5')
                <span>{{ $task->team->name }}</span>
            </span>
        </div>
    @endif

    <!-- Meta: Projekt -->
    @if($task->project)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)] min-w-0">
                @svg('heroicon-o-folder','w-2.5 h-2.5 mt-0.5')
                <span class="truncate max-w-[9rem]">{{ $task->project->name }}</span>
            </span>
        </div>
    @endif

    <!-- Description (truncated) -->
    @if($task->description)
        <div class="text-xs text-[var(--ui-muted)] my-1.5 mb-3 line-clamp-2">
            {{ Str::limit($task->description, 80) }}
        </div>
    @endif

    <!-- DoD Progress -->
    @if($task->has_dod)
        @php
            $dodProgress = $task->dod_progress;
        @endphp
        <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)]">
            @svg('heroicon-o-clipboard-document-check', 'w-3 h-3')
            <span>DoD: {{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}</span>
            <div class="flex-1 h-1 bg-[var(--ui-muted-5)] rounded-full overflow-hidden">
                <div
                    class="h-full {{ $dodProgress['isComplete'] ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }}"
                    style="width: {{ $dodProgress['percentage'] }}%"
                ></div>
            </div>
        </div>
    @endif
</x-ui-kanban-card>