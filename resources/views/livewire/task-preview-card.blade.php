<x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.tasks.show', $task)">
    <!-- Team (eigene Zeile) -->
    @if($task->team)
        <div class="mb-2">
            <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-muted)]">
                @svg('heroicon-o-user-group','w-3.5 h-3.5')
                <span class="font-medium">{{ $task->team->name }}</span>
            </span>
        </div>
    @endif

    <!-- Meta: Projekt • Verantwortlicher • Story Points -->
    <div class="flex items-center justify-between mb-2 gap-2">
        <div class="flex items-center gap-2 text-xs text-[var(--ui-secondary)] min-w-0">
            @if($task->project)
                <span class="inline-flex items-center gap-1 min-w-0">
                    @svg('heroicon-o-folder','w-3.5 h-3.5')
                    <span class="truncate max-w-[9rem] font-medium">{{ $task->project->name }}</span>
                </span>
            @endif

            @php
                $owner = $task->assignee ?? ($task->user ?? null);
                $initials = $owner ? mb_strtoupper(mb_substr($owner->name ?? $owner->email ?? 'U', 0, 1)) : null;
            @endphp
            @if($owner)
                @if($task->project)
                    <span class="text-[var(--ui-muted)]">•</span>
                @endif
                <span class="inline-flex items-center gap-1 min-w-0">
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] text-[var(--ui-secondary)]">{{ $initials }}</span>
                    <span class="truncate max-w-[7rem]">{{ $owner->name ?? $owner->email }}</span>
                </span>
            @endif
        </div>

        <div class="flex items-center gap-1 flex-shrink-0">
            @if($task->story_points)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--ui-primary-5)] text-[color:var(--ui-primary)]">
                    @svg('heroicon-o-sparkles','w-3 h-3')
                    SP {{ is_object($task->story_points) ? ($task->story_points->points() ?? $task->story_points) : $task->story_points }}
                </span>
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