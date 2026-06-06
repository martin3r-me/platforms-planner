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
    @include('planner::partials.planner-tokens')

    <x-slot name="navbar">
        <x-ui-page-navbar title="Erledigte Aufgaben" icon="heroicon-o-check-circle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Erledigte Aufgaben'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" icon="heroicon-o-funnel" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--ui-secondary)] leading-relaxed m-0">
                        Was wurde im gewählten Zeitraum abgeschlossen — geordnet nach Datum, optional pro Person.
                    </p>
                </section>

                {{-- ZEITRAUM --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Zeitraum</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach([7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr'] as $val => $label)
                            <button
                                type="button"
                                wire:click="$set('daysFilter', {{ $val }})"
                                class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $daysFilter == $val
                                    ? 'bg-[var(--planner-status-done)] text-white'
                                    : 'bg-[var(--planner-status-done)]/10 text-[var(--planner-status-done)] hover:bg-[var(--planner-status-done)]/20' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </section>

                {{-- PERSON --}}
                @if($availableUsers->isNotEmpty())
                    <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Person</h3>
                        <div class="space-y-0.5 max-h-56 overflow-y-auto">
                            <button
                                type="button"
                                wire:click="$set('userFilter', null)"
                                class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $userFilter === null ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                            >Alle Personen</button>
                            @foreach($availableUsers as $user)
                                @php $initial = mb_strtoupper(mb_substr($user->name ?? $user->email ?? 'U', 0, 1)); @endphp
                                <button
                                    type="button"
                                    wire:click="$set('userFilter', {{ $user->id }})"
                                    class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors flex items-center gap-2 {{ $userFilter == $user->id ? 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                                >
                                    @if($user->avatar)
                                        <img src="{{ $user->avatar }}" alt="" class="w-4 h-4 rounded-full object-cover flex-shrink-0">
                                    @else
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-secondary)] text-white text-[8px] font-semibold flex-shrink-0">{{ $initial }}</span>
                                    @endif
                                    <span class="truncate">{{ $user->fullname ?? $user->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- STATS --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Bilanz</h3>
                    <dl class="space-y-1.5 text-[11px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--ui-muted)]">Aufgaben</dt>
                            <dd class="text-[var(--ui-secondary)] font-semibold tabular-nums m-0">{{ $totalCount }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-[var(--ui-muted)]">Story Points</dt>
                            <dd class="text-[var(--ui-secondary)] font-semibold tabular-nums m-0">{{ $totalPoints }}</dd>
                        </div>
                        @if($totalCount > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[var(--ui-muted)]">⌀ pro Tag</dt>
                                <dd class="text-[var(--ui-secondary)] font-semibold tabular-nums m-0">{{ number_format($totalCount / max(1, $daysFilter), 1, ',', '.') }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Header --}}
        <div class="px-4 pt-3 pb-2 border-b border-[var(--ui-border)]/40 bg-white">
            <div class="flex items-start justify-between gap-6">
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-[var(--ui-secondary)] truncate m-0 leading-tight inline-flex items-center gap-2">
                        @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--planner-status-done)]')
                        Erledigte Aufgaben
                    </h1>
                    <p class="text-[11px] text-[var(--ui-muted)] mt-0.5 m-0">
                        {{ $rangeLabel }}
                        @if($userFilter)
                            · gefiltert nach {{ $availableUsers->firstWhere('id', $userFilter)?->name ?? 'Person' }}
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-done)]">
                        @svg('heroicon-o-check-circle', 'w-3 h-3')
                        <span class="font-semibold tabular-nums">{{ $totalCount }}</span>
                        <span class="text-[var(--ui-muted)]">erledigt</span>
                    </span>
                    @if($totalPoints > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                            <span class="font-semibold tabular-nums">{{ $totalPoints }}</span>
                            <span class="text-[var(--ui-muted)]">SP</span>
                        </span>
                    @endif
                    @if($totalCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                            <span class="font-semibold tabular-nums">{{ number_format($totalCount / max(1, $daysFilter), 1, ',', '.') }}</span>
                            <span class="text-[var(--ui-muted)]">⌀/Tag</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto bg-[var(--ui-muted-5)]">
            <div class="p-6 space-y-6">

                @if($groupedTasks->isEmpty())
                    <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm p-12 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--ui-muted-5)] mb-3">
                            @svg('heroicon-o-check-circle', 'w-7 h-7 text-[var(--ui-muted)]')
                        </div>
                        <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 mb-1">Keine erledigten Aufgaben</h3>
                        <p class="text-sm text-[var(--ui-muted)] m-0">In den {{ $rangeLabel }} wurde nichts abgeschlossen.</p>
                    </div>
                @else
                    @foreach($groupedTasks as $groupLabel => $tasks)
                        @php $groupPoints = $tasks->sum(fn($t) => $t->story_points?->points() ?? 0); @endphp
                        <section>
                            <div class="flex items-center gap-2 mb-2 px-1">
                                <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">{{ $groupLabel }}</h2>
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-status-done) 18%, transparent); color: var(--planner-status-done)">{{ $tasks->count() }}</span>
                                @if($groupPoints > 0)
                                    <span class="text-[10px] text-[var(--ui-muted)] tabular-nums">{{ $groupPoints }} SP</span>
                                @endif
                            </div>
                            <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                                @foreach($tasks as $i => $task)
                                    @php
                                        $uic = $task->userInCharge;
                                        $uicInitial = $uic ? mb_strtoupper(mb_substr($uic->name ?? $uic->email ?? 'U', 0, 1)) : null;
                                        $pColor = $task->project?->color ?? null;
                                    @endphp
                                    <a
                                        href="{{ route('planner.tasks.show', $task) }}?from=completed"
                                        wire:navigate
                                        class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors group {{ $i > 0 ? 'border-t border-[var(--ui-border)]/40' : '' }}"
                                    >
                                        {{-- Color edge: emerald für erledigt --}}
                                        <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full bg-[var(--planner-status-done)]"></span>

                                        {{-- Done circle (visuelles Check-Indicator) --}}
                                        <span class="flex-shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--planner-status-done)] text-white">
                                            @svg('heroicon-s-check', 'w-3 h-3')
                                        </span>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm text-[var(--ui-secondary)]/80 truncate line-through group-hover:text-[var(--planner-status-active)] group-hover:no-underline">{{ $task->title }}</span>
                                                @if($task->is_frog)
                                                    <span class="flex-shrink-0 text-xs" title="Frosch">🐸</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 mt-0.5 text-[10px] text-[var(--ui-muted)]">
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
                                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $pColor ?? 'var(--ui-muted)' }};"></span>
                                                        {{ $task->project->name }}
                                                    </span>
                                                @endif
                                                @if($uic)
                                                    <span>{{ $uic->fullname ?? $uic->name }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if($task->story_points)
                                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--planner-status-done)]/10 text-[var(--planner-status-done)] tabular-nums">
                                                {{ $task->story_points->points() }} SP
                                            </span>
                                        @endif

                                        @if($uic)
                                            @if($uic->avatar)
                                                <img src="{{ $uic->avatar }}" alt="" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                                            @else
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[var(--ui-secondary)] text-white text-[10px] font-semibold flex-shrink-0">{{ $uicInitial }}</span>
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
