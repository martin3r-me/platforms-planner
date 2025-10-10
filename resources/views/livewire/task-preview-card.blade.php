<x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.tasks.show', $task)">
    <!-- Meta Row: Projekt • Verantwortlicher • Story Points / Priority -->
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2 text-[var(--ui-secondary)] text-xs min-w-0">
            @if($task->project)
                <span class="inline-flex items-center gap-1 min-w-0">
                    @svg('heroicon-o-folder','w-3.5 h-3.5')
                    <span class="truncate max-w-[9rem]">{{ $task->project->name }}</span>
                </span>
            @endif

            @php
                $owner = $task->assignee ?? ($task->user ?? null);
                $initials = $owner ? mb_strtoupper(mb_substr($owner->name ?? $owner->email ?? 'U', 0, 1)) : null;
            @endphp
            @if($owner)
                <span class="inline-flex items-center gap-1">
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-muted-5)] text-[10px] text-[var(--ui-secondary)]">{{ $initials }}</span>
                    <span class="truncate max-w-[7rem]">{{ $owner->name ?? $owner->email }}</span>
                </span>
            @endif
        </div>

        <div class="flex items-center gap-1 flex-shrink-0">
            @if($task->story_points)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">SP {{ is_object($task->story_points) ? ($task->story_points->points() ?? $task->story_points) : $task->story_points }}</span>
            @endif
            @if($task->priority)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ strtoupper($task->priority->value) }}</span>
            @endif
        </div>
    </div>

    <!-- Description (truncated) -->
    @if($task->description)
        <div class="text-xs text-[var(--ui-muted)] mb-2 line-clamp-2">
            {{ Str::limit($task->description, 80) }}
        </div>
    @endif

    <!-- Due date / Flags -->
    <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
        @if($task->due_date)
            <span class="inline-flex items-center gap-1">
                @svg('heroicon-o-calendar','w-3 h-3')
                {{ $task->due_date->format('d.m.Y') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1">
                @svg('heroicon-o-calendar','w-3 h-3')
                Keine Fälligkeit
            </span>
        @endif

        <div class="flex items-center gap-1 ml-auto">
            @if(($task->is_frog ?? false))
                <span class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-warning)]">
                    @svg('heroicon-o-exclamation-triangle','w-3 h-3') Frosch
                </span>
            @endif
            @if(($task->is_done ?? false))
                <span class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-success)]">
                    @svg('heroicon-o-check-circle','w-3 h-3') Erledigt
                </span>
            @endif
        </div>
    </div>
</x-ui-kanban-card>