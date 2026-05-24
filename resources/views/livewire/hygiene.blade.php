@include('planner::partials.planner-tokens')
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Hygiene" icon="heroicon-o-shield-check" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Projekte', 'href' => route('planner.dashboard'), 'icon' => 'clipboard-document-list'],
            ['label' => 'Hygiene'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-5">

                {{-- KPI Grid --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="px-3 py-2 rounded border {{ $staleProjectsCount > 0 ? 'border-[var(--planner-status-overdue)]/20 bg-[var(--planner-status-overdue)]/5' : 'border-[var(--planner-status-done)]/20 bg-[var(--planner-status-done)]/5' }}">
                        <div class="text-lg font-bold {{ $staleProjectsCount > 0 ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--planner-status-done)]' }}">{{ $staleProjectsCount }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Projekt-Leichen</div>
                    </div>
                    <div class="px-3 py-2 rounded border {{ $staleTasksCount > 0 ? 'border-amber-300/30 bg-amber-50' : 'border-[var(--planner-status-done)]/20 bg-[var(--planner-status-done)]/5' }}">
                        <div class="text-lg font-bold {{ $staleTasksCount > 0 ? 'text-amber-600' : 'text-[var(--planner-status-done)]' }}">{{ $staleTasksCount }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Task-Leichen</div>
                    </div>
                    <div class="px-3 py-2 rounded border {{ $staleOverdue > 0 ? 'border-[var(--planner-status-overdue)]/20 bg-[var(--planner-status-overdue)]/5' : 'border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]' }}">
                        <div class="text-lg font-bold {{ $staleOverdue > 0 ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-secondary)]' }}">{{ $staleOverdue }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">Davon überfällig</div>
                    </div>
                    <div class="px-3 py-2 rounded border border-[var(--planner-status-active)]/20 bg-[var(--planner-status-active)]/5">
                        <div class="text-lg font-bold text-[var(--planner-status-active)]">{{ $staleSP }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wide">SP vergessen</div>
                    </div>
                </div>

                {{-- Älteste Leichen --}}
                @if($oldestStaleProject || $oldestStaleTask)
                    <div class="space-y-1 text-xs">
                        @if($oldestStaleProject)
                            <div class="flex items-center justify-between py-1.5 px-2 rounded bg-[var(--planner-status-overdue)]/5">
                                <span class="text-[var(--ui-muted)] truncate">Ältestes Projekt</span>
                                <span class="font-semibold text-[var(--planner-status-overdue)] flex-shrink-0">{{ $oldestStaleProject->last_viewed_at ? $oldestStaleProject->last_viewed_at->diffForHumans() : 'Nie' }}</span>
                            </div>
                        @endif
                        @if($oldestStaleTask)
                            <div class="flex items-center justify-between py-1.5 px-2 rounded bg-amber-50">
                                <span class="text-[var(--ui-muted)] truncate">Ältester Task</span>
                                <span class="font-semibold text-amber-600 flex-shrink-0">{{ $oldestStaleTask->last_viewed_at ? $oldestStaleTask->last_viewed_at->diffForHumans() : 'Nie' }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Nie-angesehen Info --}}
                @if($neverViewedProjectsCount > 0 || $neverViewedTasksCount > 0)
                    <div class="text-[10px] text-[var(--ui-muted)] px-1 leading-relaxed bg-[var(--ui-muted-5)] rounded p-2">
                        @if($neverViewedProjectsCount > 0)
                            <span class="font-medium text-[var(--planner-status-overdue)]">{{ $neverViewedProjectsCount }}</span> Projekt{{ $neverViewedProjectsCount > 1 ? 'e' : '' }} nie angesehen.
                        @endif
                        @if($neverViewedTasksCount > 0)
                            <span class="font-medium text-amber-600">{{ $neverViewedTasksCount }}</span> Task{{ $neverViewedTasksCount > 1 ? 's' : '' }} nie angesehen.
                        @endif
                    </div>
                @endif

                <div class="border-t border-[var(--ui-border)]/40"></div>

                {{-- Tab-Switch --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-1">Ansicht</h3>
                    <div class="flex gap-1">
                        <button
                            wire:click="$set('tab', 'stale')"
                            class="flex-1 px-2 py-1.5 text-xs rounded transition-colors {{ $tab === 'stale' ? 'bg-[var(--planner-status-overdue)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}"
                        >Vergessen</button>
                        <button
                            wire:click="$set('tab', 'recent')"
                            class="flex-1 px-2 py-1.5 text-xs rounded transition-colors {{ $tab === 'recent' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}"
                        >Kürzlich</button>
                    </div>
                </div>

                {{-- Entity Type Filter --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-1">Anzeigen</h3>
                    <div class="flex gap-1">
                        <button wire:click="$set('entityType', 'all')" class="flex-1 px-2 py-1.5 text-[10px] rounded transition-colors {{ $entityType === 'all' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}">Alles</button>
                        <button wire:click="$set('entityType', 'projects')" class="flex-1 px-2 py-1.5 text-[10px] rounded transition-colors {{ $entityType === 'projects' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}">Projekte</button>
                        <button wire:click="$set('entityType', 'tasks')" class="flex-1 px-2 py-1.5 text-[10px] rounded transition-colors {{ $entityType === 'tasks' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted)]' }}">Tasks</button>
                    </div>
                </div>

                {{-- Project filter (nur im Stale-Tab) --}}
                @if($tab === 'stale' && $availableProjects->isNotEmpty())
                    <div>
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-1">Projekt</h3>
                        <div class="space-y-0.5">
                            <button
                                wire:click="$set('projectFilter', null)"
                                class="w-full text-left px-2.5 py-1.5 rounded text-xs transition-colors {{ $projectFilter === null ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                            >Alle</button>
                            @foreach($availableProjects as $proj)
                                <button
                                    wire:click="$set('projectFilter', {{ $proj->id }})"
                                    class="w-full text-left px-2.5 py-1.5 rounded text-xs transition-colors flex items-center gap-2 {{ $projectFilter == $proj->id ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >
                                    @if($proj->color)
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $proj->color }}"></span>
                                    @else
                                        @svg('heroicon-o-folder', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                                    @endif
                                    <span class="truncate">{{ $proj->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Info --}}
                <div class="text-[10px] text-[var(--ui-muted)] leading-relaxed px-1">
                    Projekte gelten nach {{ $projectHygieneDays }} Tagen ohne Besuch als vernachlässigt, Tasks nach {{ $taskHygieneDays }} Tagen.
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">

        @if($tab === 'stale')
            {{-- ========== VERGESSEN TAB ========== --}}

            @if($staleProjectsCount === 0 && $staleTasksCount === 0)
                <div class="py-16 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[var(--planner-status-done)]/10 mb-4">
                        @svg('heroicon-o-shield-check', 'w-8 h-8 text-[var(--planner-status-done)]')
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-1">Alles aufgeräumt</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Alle Projekte und Aufgaben wurden kürzlich besucht.</p>
                </div>
            @else

                {{-- Stale Projects --}}
                @if(($entityType === 'all' || $entityType === 'projects') && $staleProjects->isNotEmpty())
                    <div>
                        <div class="flex items-center gap-2 mb-3 px-1">
                            <span class="text-sm font-semibold text-[var(--planner-status-overdue)]">Vergessene Projekte</span>
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">{{ $staleProjectsCount }}</span>
                        </div>
                        <div class="rounded-lg border border-[var(--planner-status-overdue)]/20 overflow-hidden divide-y divide-[var(--planner-status-overdue)]/10">
                            @foreach($staleProjects as $project)
                                @php
                                    $daysSince = $project->last_viewed_at ? (int) now()->diffInDays($project->last_viewed_at) : null;
                                    $neverViewed = $project->last_viewed_at === null;
                                    $pColor = $project->color ?? null;
                                @endphp
                                <a href="{{ route('planner.projects.show', ['plannerProject' => $project->id]) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 bg-[var(--planner-card-overdue)] hover:bg-[var(--planner-status-overdue)]/8 transition-colors group">
                                    @if($pColor)
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $pColor }}"></span>
                                    @else
                                        @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--planner-status-overdue)] flex-shrink-0')
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-[var(--ui-secondary)] truncate group-hover:text-[var(--planner-status-overdue)]">{{ $project->name }}</div>
                                        <div class="flex items-center gap-3 text-[10px] text-[var(--ui-muted)] mt-0.5">
                                            <span>{{ $project->open_tasks_count }} offen / {{ $project->total_tasks_count }} gesamt</span>
                                            @if($project->open_tasks_count === 0 && $project->total_tasks_count > 0)
                                                <span class="text-[var(--planner-status-done)] font-medium">Alle erledigt</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($neverViewed)
                                        <span class="flex-shrink-0 text-[9px] font-semibold px-1.5 py-0.5 rounded bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">Nie angesehen</span>
                                    @elseif($daysSince !== null)
                                        <span class="flex-shrink-0 text-xs font-semibold text-[var(--planner-status-overdue)] tabular-nums">{{ $daysSince }}d</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Stale Tasks --}}
                @if(($entityType === 'all' || $entityType === 'tasks') && $staleTasks->isNotEmpty())
                    <div>
                        <div class="flex items-center gap-2 mb-3 px-1">
                            <span class="text-sm font-semibold text-amber-600">Vergessene Aufgaben</span>
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-600">{{ $staleTasksCount }}</span>
                        </div>

                        @php
                            $groupedStaleTasks = $staleTasks->groupBy(fn($t) => $t->project?->name ?? 'Ohne Projekt');
                        @endphp

                        <div class="space-y-4">
                            @foreach($groupedStaleTasks as $projectName => $tasks)
                                <div>
                                    <div class="flex items-center gap-2 mb-1.5 px-1">
                                        <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $projectName }}</span>
                                        <span class="text-[10px] text-[var(--ui-muted)]">{{ $tasks->count() }}</span>
                                    </div>
                                    <div class="rounded-lg border border-amber-200/60 overflow-hidden divide-y divide-amber-100">
                                        @foreach($tasks as $task)
                                            @php
                                                $daysSince = $task->last_viewed_at ? (int) now()->diffInDays($task->last_viewed_at) : null;
                                                $neverViewed = $task->last_viewed_at === null;
                                                $isOverdue = $task->due_date && $task->due_date->isPast();
                                                $priorityColor = match($task->priority?->value ?? null) {
                                                    'high' => 'var(--planner-priority-high)',
                                                    'normal' => 'var(--planner-priority-normal)',
                                                    'low' => 'var(--planner-priority-low)',
                                                    default => 'var(--ui-muted)',
                                                };
                                            @endphp
                                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5 {{ $isOverdue ? 'bg-[var(--planner-card-overdue)]' : 'bg-amber-50/50' }} hover:bg-amber-100/50 transition-colors group">
                                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }}"></span>
                                                <div class="flex-1 min-w-0">
                                                    <span class="text-sm text-[var(--ui-secondary)] truncate block group-hover:text-amber-700">{{ $task->title }}</span>
                                                    <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)] mt-0.5">
                                                        @if($task->userInCharge)
                                                            <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span>
                                                        @endif
                                                        @if($task->due_date)
                                                            <span class="{{ $isOverdue ? 'text-[var(--planner-status-overdue)] font-medium' : '' }}">{{ $task->due_date->format('d.m.Y') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if($isOverdue)
                                                    <span class="flex-shrink-0 text-[9px] font-semibold px-1.5 py-0.5 rounded bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">überfällig</span>
                                                @endif
                                                @if($neverViewed)
                                                    <span class="flex-shrink-0 text-[9px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-600">Nie</span>
                                                @elseif($daysSince !== null)
                                                    <span class="flex-shrink-0 text-xs font-semibold text-amber-600 tabular-nums">{{ $daysSince }}d</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            @endif

        @else
            {{-- ========== KÜRZLICH TAB ========== --}}

            {{-- Recent Projects --}}
            @if(($entityType === 'all' || $entityType === 'projects') && $recentProjects->isNotEmpty())
                <div>
                    <div class="flex items-center gap-2 mb-3 px-1">
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">Kürzlich besucht — Projekte</span>
                        <span class="text-[10px] text-[var(--ui-muted)]">letzte 14 Tage</span>
                    </div>
                    <div class="rounded-lg border border-[var(--ui-border)]/60 overflow-hidden divide-y divide-[var(--ui-border)]/40">
                        @foreach($recentProjects as $project)
                            @php $pColor = $project->color ?? null; @endphp
                            <a href="{{ route('planner.projects.show', ['plannerProject' => $project->id]) }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors group">
                                @if($pColor)
                                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $pColor }}"></span>
                                @else
                                    @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                @endif
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-[var(--ui-secondary)] truncate block">{{ $project->name }}</span>
                                    <span class="text-[10px] text-[var(--ui-muted)]">{{ $project->open_tasks_count }} offen</span>
                                </div>
                                <span class="flex-shrink-0 text-[10px] text-[var(--ui-muted)]">{{ $project->last_viewed_at->diffForHumans() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recent Tasks --}}
            @if(($entityType === 'all' || $entityType === 'tasks') && $recentTasks->isNotEmpty())
                <div>
                    <div class="flex items-center gap-2 mb-3 px-1">
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">Kürzlich besucht — Aufgaben</span>
                        <span class="text-[10px] text-[var(--ui-muted)]">letzte 7 Tage</span>
                    </div>
                    <div class="rounded-lg border border-[var(--ui-border)]/60 overflow-hidden divide-y divide-[var(--ui-border)]/40">
                        @foreach($recentTasks as $task)
                            @php
                                $priorityColor = match($task->priority?->value ?? null) {
                                    'high' => 'var(--planner-priority-high)',
                                    'normal' => 'var(--planner-priority-normal)',
                                    'low' => 'var(--planner-priority-low)',
                                    default => 'var(--ui-muted)',
                                };
                            @endphp
                            <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors group">
                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $priorityColor }}"></span>
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm text-[var(--ui-secondary)] truncate block">{{ $task->title }}</span>
                                    <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)] mt-0.5">
                                        @if($task->project)
                                            <span>{{ $task->project->name }}</span>
                                        @endif
                                        @if($task->userInCharge)
                                            <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="flex-shrink-0 text-[10px] text-[var(--ui-muted)]">{{ $task->last_viewed_at->diffForHumans() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(($entityType === 'all' || $entityType === 'projects') && $recentProjects->isEmpty() && ($entityType === 'all' || $entityType === 'tasks') && $recentTasks->isEmpty())
                <div class="py-16 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[var(--ui-muted-5)] mb-4">
                        @svg('heroicon-o-eye', 'w-8 h-8 text-[var(--ui-muted)]')
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-1">Nichts Kürzliches</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Keine kürzlich besuchten Projekte oder Aufgaben.</p>
                </div>
            @endif

        @endif

    </x-ui-page-container>
</x-ui-page>
