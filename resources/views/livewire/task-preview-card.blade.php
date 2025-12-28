@props(['task'])

@php
    $isDone = $task->is_done ?? false;
    $isFrog = $task->is_frog ?? false;
@endphp
<x-ui-kanban-card 
    :title="''" 
    :sortable-id="$task->id" 
    :href="route('planner.tasks.show', $task)"
>
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
                <span class="text-xs text-[var(--ui-muted)]">
                    {{ $task->due_date->format('d.m.Y') }}
                </span>
            @endif
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

    <!-- Meta: Projekt • Verantwortlicher -->
    <div class="flex items-start gap-2 mb-3 text-xs text-[var(--ui-muted)] min-w-0">
        @if($task->project)
            <span class="inline-flex items-start gap-1 min-w-0">
                @svg('heroicon-o-folder','w-2.5 h-2.5 mt-0.5')
                <span class="truncate max-w-[9rem]">{{ $task->project->name }}</span>
            </span>
        @endif

        @php
            $userInCharge = $task->userInCharge ?? null;
            $initials = $userInCharge ? mb_strtoupper(mb_substr($userInCharge->name ?? $userInCharge->email ?? 'U', 0, 1)) : null;
        @endphp
        @if($userInCharge)
            <span class="inline-flex items-start gap-1 min-w-0">
                @if($userInCharge->avatar)
                    <img src="{{ $userInCharge->avatar }}" alt="{{ $userInCharge->name ?? $userInCharge->email }}" class="w-3.5 h-3.5 rounded-full object-cover mt-0.5">
                @else
                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[10px] text-[var(--ui-muted)] mt-0.5">{{ $initials }}</span>
                @endif
                <span class="truncate max-w-[7rem]">{{ $userInCharge->name ?? $userInCharge->email }}</span>
            </span>
        @endif
    </div>

    <!-- Description (truncated) -->
    @if($task->description)
        <div class="text-xs text-[var(--ui-muted)] my-1.5 mb-3 line-clamp-2">
            {{ Str::limit($task->description, 80) }}
        </div>
    @endif
</x-ui-kanban-card>