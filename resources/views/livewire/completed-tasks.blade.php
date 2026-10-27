@php
    $rangeLabels = [
        7   => 'letzte 7 Tage',
        30  => 'letzte 30 Tage',
        90  => 'letzte 90 Tage',
        365 => 'letztes Jahr',
    ];
    $rangeLabel = $rangeLabels[$daysFilter] ?? "letzte {$daysFilter} Tage";
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Erledigte Aufgaben" icon="heroicon-o-check-circle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-planner::actionbar active="completed"
            :filters-active="((int) $daysFilter !== 30 ? 1 : 0) + ($userFilter ? 1 : 0)">
            <x-slot:meta>
                {{-- Bilanz als EIN Badge rechts (Projekt-Standard) --}}
                <x-nx-badge variant="success" title="{{ $totalCount }} erledigt{{ $totalPoints > 0 ? ' · ' . $totalPoints . ' SP' : '' }}{{ $totalCount > 0 ? ' · ⌀ ' . number_format($totalCount / max(1, $daysFilter), 1, ',', '.') . '/Tag' : '' }}">
                    @svg('heroicon-o-check-circle', 'w-3 h-3')
                    <span class="tabular-nums">{{ $totalCount }}</span>
                    @if($totalPoints > 0)
                        <span class="opacity-40" aria-hidden="true">·</span>
                        <span class="tabular-nums">{{ $totalPoints }} SP</span>
                    @endif
                </x-nx-badge>
            </x-slot:meta>

            <x-slot:filters>
                <x-planner::filter-group label="Zeitraum">
                    @foreach([7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr'] as $val => $label)
                        <x-planner::chip :active="$daysFilter == $val" wire:click="$set('daysFilter', {{ $val }})">{{ $label }}</x-planner::chip>
                    @endforeach
                </x-planner::filter-group>

                @if($availableUsers->isNotEmpty())
                    <x-planner::filter-group label="Person" :stack="true">
                        <div class="max-h-56 overflow-y-auto flex flex-col gap-0.5">
                            <x-planner::chip :row="true" :active="$userFilter === null" wire:click="$set('userFilter', null)">Alle Personen</x-planner::chip>
                            @foreach($availableUsers as $user)
                                <x-planner::chip :row="true" :active="$userFilter == $user->id" wire:click="$set('userFilter', {{ $user->id }})">{{ $user->fullname ?? $user->name }}</x-planner::chip>
                            @endforeach
                        </div>
                    </x-planner::filter-group>
                @endif
            </x-slot:filters>
        </x-planner::actionbar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto bg-[var(--nx-bg)]">
            <div class="px-3 sm:px-6 py-4 sm:py-6 space-y-6">

                {{-- Bilanz --}}
                <div class="space-y-3">
                    <p class="text-xs text-[var(--nx-muted)] m-0">
                        Abgeschlossen im Zeitraum <span class="font-medium text-[var(--nx-text)]">{{ $rangeLabel }}</span> — geordnet nach Datum, optional pro Person.
                    </p>
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <div class="rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">Aufgaben</div>
                            <div class="text-lg font-semibold tabular-nums text-[var(--nx-text)]">{{ $totalCount }}</div>
                        </div>
                        <div class="rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)] truncate">Story Points</div>
                            <div class="text-lg font-semibold tabular-nums text-[var(--nx-text)]">{{ $totalPoints }}</div>
                        </div>
                        <div class="rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">⌀ pro Tag</div>
                            <div class="text-lg font-semibold tabular-nums text-[var(--nx-text)]">{{ $totalCount > 0 ? number_format($totalCount / max(1, $daysFilter), 1, ',', '.') : '–' }}</div>
                        </div>
                    </div>
                </div>

                @if($groupedTasks->isEmpty())
                    <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] p-8 sm:p-12 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--nx-bg)] mb-3">
                            @svg('heroicon-o-check-circle', 'w-7 h-7 text-[var(--nx-muted)]')
                        </div>
                        <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 mb-1">Keine erledigten Aufgaben</h3>
                        <p class="text-sm text-[var(--nx-muted)] m-0">In den {{ $rangeLabel }} wurde nichts abgeschlossen.</p>
                    </div>
                @else
                    @foreach($groupedTasks as $groupLabel => $tasks)
                        @php $groupPoints = $tasks->sum(fn($t) => $t->story_points?->points() ?? 0); @endphp
                        <section>
                            <div class="flex items-center gap-2 mb-2 px-1">
                                <h2 class="text-sm font-semibold text-[var(--nx-text)] m-0">{{ $groupLabel }}</h2>
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--nx-success) 18%, transparent); color: var(--nx-success)">{{ $tasks->count() }}</span>
                                @if($groupPoints > 0)
                                    <span class="text-[10px] text-[var(--nx-muted)] tabular-nums">{{ $groupPoints }} SP</span>
                                @endif
                            </div>
                            <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                                @foreach($tasks as $i => $task)
                                    @php
                                        $uic = $task->userInCharge;
                                        $uicInitial = $uic ? mb_strtoupper(mb_substr($uic->name ?? $uic->email ?? 'U', 0, 1)) : null;
                                        $pColor = $task->project?->color ?? null;
                                    @endphp
                                    <a
                                        href="{{ route('planner.tasks.show', $task) }}?from=completed"
                                        wire:navigate
                                        class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--nx-bg)] transition-colors group {{ $i > 0 ? 'border-t border-[color:var(--nx-line)]' : '' }}"
                                    >
                                        {{-- Color edge: emerald für erledigt --}}
                                        <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full bg-[var(--nx-success)]"></span>

                                        {{-- Done circle (visuelles Check-Indicator) --}}
                                        <span class="flex-shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--nx-success)] text-white">
                                            @svg('heroicon-s-check', 'w-3 h-3')
                                        </span>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm text-[var(--nx-text)]/80 truncate line-through group-hover:text-[var(--nx-accent)] group-hover:no-underline">{{ $task->title }}</span>
                                                @if($task->is_frog)
                                                    <span class="flex-shrink-0 text-xs" title="Frosch">🐸</span>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5 text-[10px] text-[var(--nx-muted)]">
                                                @if($task->done_at)
                                                    <span class="inline-flex items-center gap-1 tabular-nums">
                                                        @svg('heroicon-o-clock', 'w-3 h-3 opacity-60')
                                                        {{ $task->done_at->format('d.m. H:i') }}
                                                    </span>
                                                @elseif($task->updated_at)
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-clock', 'w-3 h-3 opacity-60')
                                                        {{ $task->updated_at->diffForHumans() }}
                                                    </span>
                                                @endif
                                                @if($task->project)
                                                    <span class="inline-flex items-center gap-1">
                                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $pColor ?? 'var(--nx-muted)' }};"></span>
                                                        {{ $task->project->name }}
                                                    </span>
                                                @endif
                                                @if($uic)
                                                    <span>{{ $uic->fullname ?? $uic->name }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if($task->story_points)
                                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--nx-success)]/10 text-[var(--nx-success)] tabular-nums">
                                                {{ $task->story_points->points() }} SP
                                            </span>
                                        @endif

                                        @if($uic)
                                            @if($uic->avatar)
                                                <img src="{{ method_exists($uic, 'avatarUrl') ? $uic->avatarUrl() : $uic->avatar }}" loading="lazy" alt="" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                                            @else
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[var(--nx-text)] text-white text-[10px] font-semibold flex-shrink-0">{{ $uicInitial }}</span>
                                            @endif
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif

            </div>
        </div>
    </div>
</x-ui-page>
