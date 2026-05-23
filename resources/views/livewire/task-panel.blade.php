<div
    x-data="{
        isOpen: @entangle('open'),
        init() {
            this.$watch('isOpen', (val) => {
                if (val && {{ $task?->id ?? 'null' }}) {
                    history.pushState({}, '', '/planner/tasks/' + {{ $task?->id ?? 'null' }});
                } else if (!val) {
                    history.back();
                }
            });
        },
        close() {
            this.isOpen = false;
            $wire.closeTaskPanel();
        }
    }"
    x-show="isOpen"
    x-cloak
    @keydown.escape.window="if (isOpen) close()"
    class="fixed inset-0 z-50 flex justify-end"
>
    {{-- Backdrop --}}
    <div
        x-show="isOpen"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="absolute inset-0 bg-black/20"
    ></div>

    {{-- Panel --}}
    <div
        x-show="isOpen"
        x-transition:enter="transition-transform ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="relative w-full max-w-[480px] h-full bg-white border-l border-[var(--ui-border)] shadow-xl overflow-y-auto"
    >
        @if($task)
            {{-- Header --}}
            <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-3 bg-white border-b border-[var(--ui-border)]">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded {{ $task->is_done ? 'bg-[var(--ui-success)]/10 text-[var(--ui-success)]' : 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]' }}">
                        {{ $task->is_done ? 'Erledigt' : 'Offen' }}
                    </span>
                    @if($task->project)
                        <span class="text-xs text-[var(--ui-muted)] truncate max-w-[200px]">{{ $task->project->name }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <a
                        href="{{ route('planner.tasks.show', $task) }}"
                        class="inline-flex items-center gap-1 px-2 py-1 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] rounded hover:bg-[var(--ui-muted-5)] transition"
                        wire:navigate
                    >
                        @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5')
                        Vollansicht
                    </a>
                    <button
                        @click="close()"
                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] rounded hover:bg-[var(--ui-muted-5)] transition"
                    >
                        @svg('heroicon-o-x-mark', 'w-5 h-5')
                    </button>
                </div>
            </div>

            {{-- Content --}}
            <div class="px-5 py-5 space-y-6">

                {{-- Title --}}
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] leading-snug {{ $task->is_done ? 'line-through text-[var(--ui-muted)]' : '' }}">
                    {{ $task->title }}
                </h2>

                {{-- Metadata table --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-1.5">
                        <span class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Status</span>
                        <span class="text-sm text-[var(--ui-secondary)]">{{ $task->is_done ? 'Erledigt' : 'Offen' }}</span>
                    </div>

                    @if($task->priority)
                        <div class="flex items-center justify-between py-1.5 border-t border-[var(--ui-border)]/50">
                            <span class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Priorität</span>
                            <span class="text-sm text-[var(--ui-secondary)]">
                                {{ $task->priority->icon() }} {{ $task->priority->label() }}
                            </span>
                        </div>
                    @endif

                    @if($task->userInCharge)
                        <div class="flex items-center justify-between py-1.5 border-t border-[var(--ui-border)]/50">
                            <span class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Verantwortlich</span>
                            <span class="inline-flex items-center gap-1.5 text-sm text-[var(--ui-secondary)]">
                                @if($task->userInCharge->avatar)
                                    <img src="{{ $task->userInCharge->avatar }}" alt="" class="w-5 h-5 rounded-full object-cover">
                                @else
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-10)] text-[10px] font-medium text-[var(--ui-muted)]">
                                        {{ mb_strtoupper(mb_substr($task->userInCharge->name ?? 'U', 0, 1)) }}
                                    </span>
                                @endif
                                {{ $task->userInCharge->name ?? $task->userInCharge->email }}
                            </span>
                        </div>
                    @endif

                    @if($task->due_date)
                        @php $isOverdue = $task->due_date->isPast() && !$task->is_done; @endphp
                        <div class="flex items-center justify-between py-1.5 border-t border-[var(--ui-border)]/50">
                            <span class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Fällig</span>
                            <span class="text-sm {{ $isOverdue ? 'text-[var(--ui-danger)] font-medium' : 'text-[var(--ui-secondary)]' }}">
                                {{ $task->due_date->format('d.m.Y H:i') }}
                                @if($task->postpone_count > 0)
                                    <span class="text-xs text-[var(--ui-muted)]">({{ $task->postpone_count }}x verschoben)</span>
                                @endif
                            </span>
                        </div>
                    @endif

                    @if($task->story_points)
                        <div class="flex items-center justify-between py-1.5 border-t border-[var(--ui-border)]/50">
                            <span class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Story Points</span>
                            <span class="text-sm text-[var(--ui-secondary)]">
                                {{ is_object($task->story_points) ? $task->story_points->points() . ' SP' : $task->story_points }}
                            </span>
                        </div>
                    @endif

                    @if($task->is_frog)
                        <div class="flex items-center justify-between py-1.5 border-t border-[var(--ui-border)]/50">
                            <span class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Frosch</span>
                            <span class="text-sm text-[var(--ui-warning)]">
                                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 inline') Ja
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Description --}}
                @if($task->description)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Beschreibung</h3>
                        <div class="text-sm text-[var(--ui-secondary)] leading-relaxed whitespace-pre-wrap">{{ $task->description }}</div>
                    </div>
                @endif

                {{-- DoD Checklist --}}
                @if($task->has_dod)
                    @php $dodProgress = $task->dod_progress; @endphp
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2 flex items-center gap-2">
                            DoD Checkliste
                            <span class="text-[10px] font-normal">({{ $dodProgress['checked'] }}/{{ $dodProgress['total'] }})</span>
                        </h3>
                        <div class="space-y-1.5">
                            @foreach($task->dod_items as $item)
                                <div class="flex items-start gap-2 text-sm">
                                    @if($item['checked'] ?? false)
                                        @svg('heroicon-s-check-circle', 'w-4 h-4 text-[var(--ui-success)] flex-shrink-0 mt-0.5')
                                        <span class="text-[var(--ui-muted)] line-through">{{ $item['text'] }}</span>
                                    @else
                                        <span class="w-4 h-4 rounded-full border-2 border-[var(--ui-border)] flex-shrink-0 mt-0.5"></span>
                                        <span class="text-[var(--ui-secondary)]">{{ $item['text'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 h-1 bg-[var(--ui-muted-10)] rounded-full overflow-hidden">
                            <div
                                class="h-full {{ $dodProgress['isComplete'] ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }} rounded-full"
                                style="width: {{ $dodProgress['percentage'] }}%"
                            ></div>
                        </div>
                    </div>
                @endif

                {{-- Tags --}}
                @php $contextTags = $task->contextTags ?? collect(); @endphp
                @if($contextTags->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Tags</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($contextTags as $tag)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-md border"
                                    @if($tag->color)
                                        style="background-color: {{ $tag->color }}10; border-color: {{ $tag->color }}35; color: {{ $tag->color }}"
                                    @else
                                        class="bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border-[var(--ui-border)]/30"
                                    @endif
                                >
                                    @if($tag->color)
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $tag->color }}"></span>
                                    @endif
                                    {{ $tag->label }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Activities --}}
                @if($activities->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Aktivität</h3>
                        <div class="space-y-2">
                            @foreach($activities as $activity)
                                @php
                                    $eventLabel = match($activity->name) {
                                        'created' => 'erstellt',
                                        'updated' => 'aktualisiert',
                                        'deleted' => 'gelöscht',
                                        default => $activity->name,
                                    };
                                @endphp
                                <div class="flex items-start gap-2 text-xs text-[var(--ui-muted)]">
                                    <span class="w-1 h-1 rounded-full bg-[var(--ui-muted)] mt-1.5 flex-shrink-0"></span>
                                    <div>
                                        @if($activity->user)
                                            <span class="text-[var(--ui-secondary)]">{{ $activity->user->name }}</span>
                                        @endif
                                        hat Task {{ $eventLabel }}
                                        <span class="ml-1">{{ $activity->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        @else
            @if($open)
                <div class="flex items-center justify-center h-full text-sm text-[var(--ui-muted)]">
                    Aufgabe nicht gefunden.
                </div>
            @endif
        @endif
    </div>
</div>
