@php
    $isDone = $task->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::COMPLETED;
    $contextTags = $task->contextTags ?? collect();
    $contextColor = $task->color ?? null;
@endphp

<div class="h-screen flex flex-col overflow-hidden bg-[var(--nx-bg)]">
    {{-- Header with back button --}}
    <header class="flex-shrink-0 border-b border-[var(--nx-line)] bg-[color:var(--nx-surface)]">
        <div class="px-6 py-3">
            <div class="flex items-center gap-4">
                <a
                    href="{{ route('planner.public.show', $token) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-[var(--nx-line)] text-[var(--nx-muted)] bg-[color:var(--nx-surface)] hover:bg-[var(--nx-bg)] hover:text-[var(--nx-text)] transition-colors"
                >
                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                    <span>Zurück zum Board</span>
                </a>
                <div class="h-5 w-px bg-[var(--nx-line)]"></div>
                <span class="text-sm text-[var(--nx-muted)] truncate">
                    {{ $project->name }}
                </span>
            </div>
        </div>
    </header>

    {{-- Content --}}
    <div class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-4xl mx-auto px-6 py-8 space-y-6">
            {{-- Task Header Card --}}
            <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[var(--nx-line)]/60 shadow-[var(--nx-shadow-card)] overflow-hidden">
                <div class="p-6 lg:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            {{-- Context Tags --}}
                            @if($contextColor || $contextTags->isNotEmpty())
                                <div class="mb-3 flex items-center gap-1.5 flex-wrap">
                                    @if($contextColor)
                                        <span class="w-3 h-3 rounded-full flex-shrink-0 ring-1 ring-white/60 shadow-[var(--nx-shadow-card)]" style="background-color: {{ $contextColor }}"></span>
                                    @endif
                                    @foreach($contextTags as $tag)
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-md
                                                {{ $tag->color
                                                    ? 'border shadow-[var(--nx-shadow-card)]'
                                                    : 'bg-[var(--nx-bg)] text-[var(--nx-muted)] border border-[var(--nx-line)]/30'
                                                }}"
                                            @if($tag->color)
                                                style="background-color: {{ $tag->color }}10; border-color: {{ $tag->color }}35; color: {{ $tag->color }}"
                                            @endif
                                        >
                                            @if($tag->color)
                                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $tag->color }}"></span>
                                            @endif
                                            {{ $tag->label }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Title --}}
                            <h1 class="text-2xl font-bold text-[var(--nx-text)] tracking-tight leading-tight {{ $isDone ? 'line-through text-[var(--nx-muted)]' : '' }}">
                                {{ $task->title }}
                            </h1>

                            {{-- Meta --}}
                            <div class="mt-4 space-y-2">
                                <div class="flex flex-wrap items-center gap-6 text-sm text-[var(--nx-muted)]">
                                    @if($task->userInCharge)
                                        <span class="flex items-center gap-2">
                                            @svg('heroicon-o-user', 'w-4 h-4')
                                            <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name ?? $task->userInCharge->email }}</span>
                                        </span>
                                    @endif
                                    @if($task->due_date)
                                        @php
                                            $isOverdue = $task->due_date->isPast() && !$isDone;
                                            $dueDateColor = $isOverdue ? 'text-[var(--nx-danger)]' : '';
                                        @endphp
                                        <span class="flex items-center gap-2 {{ $dueDateColor }}">
                                            @svg('heroicon-o-calendar', 'w-4 h-4')
                                            <span>{{ $task->due_date->format('d.m.Y') }}</span>
                                            @if(($task->postpone_count ?? 0) > 0)
                                                <span class="flex items-center gap-0.5 text-[var(--nx-muted)]">
                                                    @svg('heroicon-o-arrow-path', 'w-3 h-3')
                                                    {{ $task->postpone_count }}x
                                                </span>
                                            @endif
                                        </span>
                                    @endif
                                    @if($task->story_points)
                                        <span class="flex items-center gap-2">
                                            @svg('heroicon-o-sparkles', 'w-4 h-4')
                                            <span>{{ is_object($task->story_points) ? $task->story_points->points() : $task->story_points }} SP</span>
                                        </span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-6 text-sm text-[var(--nx-muted)]">
                                    @if($task->team)
                                        <span class="flex items-center gap-2">
                                            @svg('heroicon-o-user-group', 'w-4 h-4')
                                            <span>{{ $task->team->name }}</span>
                                        </span>
                                    @endif
                                    @if($task->project)
                                        <span class="flex items-center gap-2">
                                            @svg('heroicon-o-folder', 'w-4 h-4')
                                            <span>{{ $task->project->name }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Status Badges --}}
                        <div class="flex flex-col items-end gap-2 flex-shrink-0">
                            @if($isDone)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-[var(--nx-success)]/10 text-[color:var(--nx-success)] border border-[var(--nx-success)]/30">
                                    @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                                    Erledigt
                                </span>
                            @endif
                            @if($task->is_frog ?? false)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-[var(--nx-danger)]/10 text-[color:var(--nx-danger)] border border-[var(--nx-danger)]/30">
                                    @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')
                                    Frosch
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            @if($task->description)
                <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[var(--nx-line)]/60 shadow-[var(--nx-shadow-card)] overflow-hidden">
                    <div class="p-6 lg:p-8">
                        <h2 class="text-sm font-semibold text-[var(--nx-text)] uppercase tracking-wide mb-3">
                            Beschreibung
                        </h2>
                        <div class="prose prose-sm max-w-none text-[var(--nx-text)]">
                            {!! nl2br(e($task->description)) !!}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Definition of Done --}}
            @if(count($dodItems) > 0)
                <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[var(--nx-line)]/60 shadow-[var(--nx-shadow-card)] overflow-hidden">
                    <div class="p-6 lg:p-8">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-[var(--nx-text)] uppercase tracking-wide">
                                Definition of Done
                            </h2>
                            @if($dodProgress)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-medium text-[var(--nx-muted)]">
                                        {{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }}
                                    </span>
                                    <div class="w-20 h-1.5 bg-[var(--nx-bg)] rounded-full overflow-hidden">
                                        <div
                                            class="h-full {{ $dodProgress['isComplete'] ? 'bg-[color:var(--nx-success)]' : 'bg-[var(--nx-accent)]' }}"
                                            style="width: {{ $dodProgress['percentage'] }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="space-y-2">
                            @foreach($dodItems as $item)
                                <div class="flex items-start gap-3 p-3 rounded-lg border border-[var(--nx-line)]/60 {{ ($item['checked'] ?? false) ? 'bg-[var(--nx-success)]/10' : 'bg-[var(--nx-bg)]' }}">
                                    <span class="flex-shrink-0 w-5 h-5 mt-0.5 rounded border-2 flex items-center justify-center
                                        {{ ($item['checked'] ?? false) ? 'bg-[color:var(--nx-success)] border-[var(--nx-success)]/30 text-white' : 'border-[var(--nx-line)]' }}">
                                        @if($item['checked'] ?? false)
                                            @svg('heroicon-s-check', 'w-3 h-3')
                                        @endif
                                    </span>
                                    <span class="text-sm {{ ($item['checked'] ?? false) ? 'line-through text-[var(--nx-muted)]' : 'text-[var(--nx-text)]' }}">
                                        {{ $item['text'] ?? '' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
