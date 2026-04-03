<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Frösche" icon="heroicon-o-exclamation-triangle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Planner', 'href' => route('planner.dashboard'), 'icon' => 'clipboard-document-list'],
            ['label' => 'Frösche'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                {{-- Personenfilter --}}
                @if($availableUsers->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Person</h3>
                        <div class="space-y-2">
                            <button
                                type="button"
                                wire:click="$set('userFilter', null)"
                                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $userFilter === null ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                            >
                                Alle Personen
                            </button>
                            @foreach($availableUsers as $user)
                                <button
                                    type="button"
                                    wire:click="$set('userFilter', {{ $user->id }})"
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $userFilter == $user->id ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                                >
                                    <div class="flex items-center gap-2">
                                        @if($user->avatar)
                                            <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="w-5 h-5 rounded-full object-cover">
                                        @else
                                            <div class="w-5 h-5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 flex items-center justify-center text-xs text-[var(--ui-secondary)]">
                                                {{ mb_strtoupper(mb_substr($user->name ?? $user->email ?? 'U', 0, 1)) }}
                                            </div>
                                        @endif
                                        <span class="truncate">{{ $user->fullname ?? $user->name }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Projektfilter --}}
                @if($availableProjects->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Projekt</h3>
                        <div class="space-y-2">
                            <button
                                type="button"
                                wire:click="$set('projectFilter', null)"
                                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $projectFilter === null ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                            >
                                Alle Projekte
                            </button>
                            @foreach($availableProjects as $project)
                                <button
                                    type="button"
                                    wire:click="$set('projectFilter', {{ $project->id }})"
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors {{ $projectFilter == $project->id ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)]' }}"
                                >
                                    <div class="flex items-center gap-2">
                                        @if($project->color)
                                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $project->color }}"></span>
                                        @else
                                            @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--ui-muted)]')
                                        @endif
                                        <span class="truncate">{{ $project->name }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)] mb-1">Frösche</div>
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $totalCount }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)] mb-1">Zwangs-Frösche</div>
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $forcedFrogCount }}</div>
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
                        @svg('heroicon-o-exclamation-triangle', 'w-8 h-8 text-[var(--ui-muted)]')
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">Keine Frösche</h3>
                    <p class="text-sm text-[var(--ui-muted)]">
                        Aktuell gibt es keine offenen Frog-Tasks.
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
                                ({{ $tasks->count() }} {{ $tasks->count() === 1 ? 'Frosch' : 'Frösche' }})
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
                                                <div class="w-5 h-5 rounded-full bg-amber-500 flex items-center justify-center">
                                                    @svg('heroicon-o-exclamation-triangle', 'w-3 h-3 text-white')
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h3 class="text-sm font-medium text-[var(--ui-secondary)]">
                                                        {{ $task->title }}
                                                    </h3>
                                                    @if($task->is_forced_frog)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-700 border border-red-200">
                                                            Zwangs-Frosch
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex flex-wrap items-center gap-3 text-xs text-[var(--ui-muted)]">
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
                                                    @if($task->due_date)
                                                        <span class="inline-flex items-center gap-1 {{ $task->due_date->isPast() ? 'text-red-500' : '' }}">
                                                            @svg('heroicon-o-calendar', 'w-3 h-3')
                                                            {{ $task->due_date->format('d.m.Y') }}
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
