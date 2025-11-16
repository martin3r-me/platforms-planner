<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Erledigte Aufgaben" icon="heroicon-o-check-circle" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                {{-- Zeitfilter --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Zeitraum</h3>
                    <div class="space-y-2">
                        <button 
                            type="button"
                            wire:click="$set('daysFilter', 7)"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $daysFilter == 7 ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                        >
                            Letzte 7 Tage
                        </button>
                        <button 
                            type="button"
                            wire:click="$set('daysFilter', 30)"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $daysFilter == 30 ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                        >
                            Letzte 30 Tage
                        </button>
                        <button 
                            type="button"
                            wire:click="$set('daysFilter', 90)"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $daysFilter == 90 ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                        >
                            Letzte 90 Tage
                        </button>
                        <button 
                            type="button"
                            wire:click="$set('daysFilter', 365)"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $daysFilter == 365 ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                        >
                            Letztes Jahr
                        </button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)] mb-1">Erledigte Aufgaben</div>
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $totalCount }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)] mb-1">Story Points</div>
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $totalPoints }} SP</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        @if($groupedTasks->isEmpty())
            <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
                <div class="p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[var(--ui-muted-5)] mb-4">
                        @svg('heroicon-o-check-circle', 'w-8 h-8 text-[var(--ui-muted)]')
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">Keine erledigten Aufgaben</h3>
                    <p class="text-sm text-[var(--ui-muted)]">
                        In den letzten {{ $daysFilter }} Tagen wurden keine Aufgaben erledigt.
                    </p>
                </div>
            </div>
        @else
            @foreach($groupedTasks as $groupLabel => $tasks)
                <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">
                            {{ $groupLabel }}
                            <span class="text-sm font-normal text-[var(--ui-muted)] ml-2">
                                ({{ $tasks->count() }} {{ $tasks->count() === 1 ? 'Aufgabe' : 'Aufgaben' }})
                            </span>
                        </h2>
                    </div>
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @foreach($tasks as $task)
                            <a 
                                href="{{ route('planner.tasks.show', $task) }}" 
                                wire:navigate
                                class="block p-4 hover:bg-[var(--ui-muted-5)] transition-colors"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start gap-3 mb-2">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <div class="w-5 h-5 rounded-full bg-[var(--ui-success)] flex items-center justify-center">
                                                    @svg('heroicon-o-check', 'w-3 h-3 text-white')
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="text-sm font-medium text-[var(--ui-secondary)] mb-1 line-through">
                                                    {{ $task->title }}
                                                </h3>
                                                @if($task->description)
                                                    <p class="text-xs text-[var(--ui-muted)] line-clamp-2 mb-2">
                                                        {{ Str::limit($task->description, 100) }}
                                                    </p>
                                                @endif
                                                <div class="flex flex-wrap items-center gap-3 text-xs text-[var(--ui-muted)]">
                                                    @if($task->done_at)
                                                        <span class="inline-flex items-center gap-1">
                                                            @svg('heroicon-o-clock', 'w-3 h-3')
                                                            Erledigt: {{ $task->done_at->format('d.m.Y H:i') }}
                                                        </span>
                                                    @elseif($task->updated_at)
                                                        <span class="inline-flex items-center gap-1">
                                                            @svg('heroicon-o-clock', 'w-3 h-3')
                                                            {{ $task->updated_at->diffForHumans() }}
                                                        </span>
                                                    @endif
                                                    @if($task->project)
                                                        <span class="inline-flex items-center gap-1">
                                                            @svg('heroicon-o-folder', 'w-3 h-3')
                                                            {{ $task->project->name }}
                                                        </span>
                                                    @endif
                                                    @if($task->userInCharge)
                                                        <span class="inline-flex items-center gap-1">
                                                            @svg('heroicon-o-user', 'w-3 h-3')
                                                            {{ $task->userInCharge->fullname ?? $task->userInCharge->name }}
                                                        </span>
                                                    @endif
                                                    @if($task->story_points)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                                            @svg('heroicon-o-sparkles', 'w-3 h-3')
                                                            {{ $task->story_points->points() }} SP
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </x-ui-page-container>
</x-ui-page>

