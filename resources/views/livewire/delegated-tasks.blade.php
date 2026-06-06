@php
    $allTasks = $groups->flatMap(fn($g) => $g->tasks);
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasks = $groups->filter(fn($g) => ($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);

    $headerOpenCount = $openTasks->count();
    $headerDoneCount = $doneTasks->count();
    $headerOverdueCount = $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count();
    $frogCount = $openTasks->filter(fn($t) => $t->is_frog)->count();
    $openPoints = $openTasks->sum(fn($t) => $t->story_points?->points() ?? 0);
    $withoutDueDate = $openTasks->filter(fn($t) => !$t->due_date)->count();
    $totalCount = $headerOpenCount + $headerDoneCount;
    $donePct = $totalCount > 0 ? round(($headerDoneCount / $totalCount) * 100) : 0;

    // Pro Person Aufschlüsselung (offene Tasks)
    $byPerson = $openTasks
        ->groupBy(fn($t) => $t->userInCharge?->id ?? 0)
        ->map(function ($tasks) {
            $u = $tasks->first()->userInCharge;
            return [
                'id'    => $u?->id,
                'name'  => $u?->name ?? 'Unzugewiesen',
                'email' => $u?->email,
                'avatar'=> $u?->avatar,
                'count' => $tasks->count(),
                'overdue' => $tasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(),
            ];
        })
        ->sortByDesc('count')
        ->values();

    $maxPerPerson = $byPerson->max('count') ?: 1;

    // Tone-Mapping für Spalten
    $tonePalette = ['indigo', 'amber', 'teal', 'violet', 'sky', 'pink', 'rose', 'emerald'];
    $middleColumns = $groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false))->values();
    $columnTones = $middleColumns->mapWithKeys(fn ($col, $i) => [$col->id => $tonePalette[$i % count($tonePalette)]]);
@endphp

<x-ui-page x-data="{}">
    @include('planner::partials.planner-tokens')

    <x-slot name="navbar">
        <x-ui-page-navbar title="Delegierte Aufgaben" icon="heroicon-o-user-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Delegierte Aufgaben'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="createTask()">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Aufgabe</span>
            </x-ui-button>
            <x-ui-button variant="ghost" size="sm" wire:click="createTaskGroup">
                @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                <span>Spalte</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" icon="heroicon-o-user-group" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--ui-secondary)] leading-relaxed m-0">
                        Aufgaben, die du an andere delegiert hast — sortiert in deinen Bearbeitungsspalten.
                    </p>
                </section>

                {{-- PRO PERSON --}}
                @if($byPerson->isNotEmpty())
                    <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Pro Person</h3>
                        <ul class="space-y-2.5">
                            @foreach($byPerson->take(8) as $p)
                                @php
                                    $loadPct = round(($p['count'] / $maxPerPerson) * 100);
                                    $loadColor = $p['overdue'] > 0 ? 'var(--planner-status-overdue)' : ($loadPct >= 80 ? '#d97706' : 'var(--planner-status-active)');
                                    $initial = mb_strtoupper(mb_substr($p['name'], 0, 1));
                                @endphp
                                <li class="flex items-center gap-2 text-[11px]">
                                    @if($p['avatar'])
                                        <img src="{{ $p['avatar'] }}" alt="" class="w-5 h-5 rounded-full object-cover flex-shrink-0">
                                    @else
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-secondary)] text-white text-[9px] font-semibold flex-shrink-0">{{ $initial }}</span>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="truncate text-[var(--ui-secondary)] font-medium">{{ $p['name'] }}</span>
                                            <span class="tabular-nums text-[var(--ui-muted)] flex-shrink-0">
                                                {{ $p['count'] }}
                                                @if($p['overdue'] > 0)
                                                    <span class="text-[var(--planner-status-overdue)] font-semibold">/{{ $p['overdue'] }}</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="mt-1 w-full h-1 rounded-full bg-[var(--ui-muted-10)] overflow-hidden">
                                            <div class="h-full rounded-full transition-all" style="width: {{ $loadPct }}%; background-color: {{ $loadColor }};"></div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if($byPerson->count() > 8)
                            <p class="mt-2 text-[10px] text-[var(--ui-muted)] pl-1">+ {{ $byPerson->count() - 8 }} weitere</p>
                        @endif
                        @if($byPerson->sum('overdue') > 0)
                            <p class="mt-2 text-[10px] text-[var(--planner-status-overdue)] pl-1">
                                Balkenfarbe = überfällig vorhanden / nahe am Limit
                            </p>
                        @endif
                    </section>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" icon="heroicon-o-bolt" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-3">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                @forelse(($activities ?? []) as $activity)
                    <div class="p-2.5 rounded-lg border border-[var(--ui-border)]/40 bg-white shadow-sm">
                        <div class="text-[11px] font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">{{ $activity['time'] ?? '' }}</div>
                    </div>
                @empty
                    <p class="text-[11px] text-[var(--ui-muted)]">Noch keine Aktivität</p>
                @endforelse
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Header: Live-Counts --}}
        <div class="px-4 pt-3 pb-2 border-b border-[var(--ui-border)]/40 bg-white">
            <div class="flex items-start justify-between gap-6">
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-[var(--ui-secondary)] truncate m-0 leading-tight inline-flex items-center gap-2">
                        @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--planner-status-active)]')
                        Delegierte Aufgaben
                    </h1>
                    <p class="text-[11px] text-[var(--ui-muted)] mt-0.5 m-0">
                        {{ $byPerson->count() }} Person{{ $byPerson->count() === 1 ? '' : 'en' }} mit offenen Aufgaben
                    </p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-active)]"></span>
                        <span class="font-semibold tabular-nums">{{ $headerOpenCount }}</span>
                        <span class="text-[var(--ui-muted)]">offen</span>
                    </span>
                    @if($headerOverdueCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-overdue)]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-overdue)]"></span>
                            <span class="font-semibold tabular-nums">{{ $headerOverdueCount }}</span>
                            <span>überfällig</span>
                        </span>
                    @endif
                    @if($frogCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-frog)]">
                            <span>🐸</span>
                            <span class="font-semibold tabular-nums">{{ $frogCount }}</span>
                        </span>
                    @endif
                    @if($openPoints > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                            <span class="font-semibold tabular-nums">{{ $openPoints }}</span>
                            <span class="text-[var(--ui-muted)]">SP</span>
                        </span>
                    @endif
                    @if($withoutDueDate > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--ui-muted)]">
                            @svg('heroicon-o-calendar', 'w-3 h-3 opacity-60')
                            <span class="font-semibold tabular-nums">{{ $withoutDueDate }}</span>
                            <span>ohne Datum</span>
                        </span>
                    @endif
                    @if($totalCount > 0)
                        <span class="inline-flex items-center gap-2">
                            <span class="text-[var(--ui-muted)] tabular-nums">{{ $donePct }}%</span>
                            <span class="w-24 h-1 rounded-full bg-[var(--planner-track)] overflow-hidden">
                                <span class="block h-full rounded-full bg-[var(--planner-status-done)] transition-all duration-300" style="width: {{ $donePct }}%"></span>
                            </span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Board --}}
        <div
            class="planner-board-canvas flex-1 min-h-0 flex"
            x-data
            @done-column-expanded.window="
                $nextTick(() => {
                    const scroller = $el.querySelector('.overflow-x-auto');
                    if (scroller) scroller.scrollTo({ left: scroller.scrollWidth, behavior: 'smooth' });
                });
            "
        >
            <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-ui-kanban-column :title="($backlog->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true" class="col-tone-slate">
                        <x-slot name="headerActions">
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-backlog) 15%, transparent); color: var(--planner-col-backlog)">
                                {{ $backlog->tasks->count() }}
                            </span>
                        </x-slot>
                        @forelse(($backlog->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine delegierten Aufgaben</span>
                            </div>
                        @endforelse
                    </x-ui-kanban-column>
                @endif

                @foreach($middleColumns as $column)
                    @php $tone = $columnTones[$column->id] ?? 'indigo'; @endphp
                    <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" :class="'col-tone-' . $tone">
                        <x-slot name="headerActions">
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-default) 15%, transparent); color: var(--planner-col-default)">
                                {{ $column->tasks->count() }}
                            </span>
                            <button
                                wire:click="createTask('{{ $column->id ?? 0 }}')"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                            <button
                                @click="$dispatch('open-modal-delegated-task-group-settings', { taskGroupId: {{ $column->id ?? 0 }} })"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                                title="Gruppen-Einstellungen"
                            >
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        </x-slot>
                        @forelse(($column->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine Aufgaben</span>
                                <span class="text-[10px] mt-0.5 opacity-60">Hierher ziehen oder neu erstellen</span>
                            </div>
                        @endforelse
                        <x-slot name="footer">
                            <div x-data="{ open: false, title: '' }">
                                <button x-show="!open" @click="open = true; $nextTick(() => $refs.inlineInput.focus())" class="w-full text-left text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors flex items-center gap-1.5">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                    <span>Aufgabe</span>
                                </button>
                                <div x-show="open" x-cloak>
                                    <input
                                        x-ref="inlineInput"
                                        x-model="title"
                                        @keydown.enter.prevent="if(title.trim()) { $wire.createTask('{{ $column->id ?? 0 }}', title.trim()); title = ''; open = false; }"
                                        @keydown.escape="open = false; title = ''"
                                        @click.outside="open = false; title = ''"
                                        type="text"
                                        placeholder="Titel eingeben..."
                                        class="w-full text-xs border border-[var(--ui-border)] rounded px-2 py-1.5 bg-white focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)]/30 outline-none"
                                    />
                                </div>
                            </div>
                        </x-slot>
                    </x-ui-kanban-column>
                @endforeach

                {{-- Done column / strip --}}
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    @if($showDoneColumn)
                        <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true" class="col-tone-emerald">
                            <x-slot name="headerActions">
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-done) 15%, transparent); color: var(--planner-col-done)">
                                    {{ $done->tasks->count() }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="toggleShowDoneColumn"
                                    class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                    title="Einklappen"
                                >
                                    @svg('heroicon-o-chevron-double-right', 'w-4 h-4')
                                </button>
                            </x-slot>
                            @forelse(($done->tasks ?? []) as $task)
                                @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                            @empty
                                <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                    @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                                    <span class="text-xs">Noch nichts erledigt</span>
                                </div>
                            @endforelse
                        </x-ui-kanban-column>
                    @else
                        <button
                            x-data="{ isList: localStorage.getItem('kanbanView') === 'list' }"
                            x-init="this.isList = localStorage.getItem('kanbanView') === 'list'"
                            @storage-change.window="isList = localStorage.getItem('kanbanView') === 'list'"
                            type="button"
                            wire:click="toggleShowDoneColumn"
                            :class="isList
                                ? 'planner-done-bar group/done sticky bottom-0 z-20 w-full flex flex-row items-center justify-start gap-3 py-3 px-4 pr-14 bg-white border-t border-[var(--planner-status-done)]/30 shadow-lg hover:bg-[var(--planner-card-done)] transition-all cursor-pointer'
                                : 'planner-done-strip group/done sticky right-0 z-10 flex-shrink-0 h-full flex flex-col items-center justify-between py-4 px-2 bg-white hover:shadow-lg transition-all cursor-pointer'"
                            :style="!isList ? 'width: 2.75rem; min-width: 2.75rem;' : ''"
                            title="Erledigte anzeigen ({{ $done->tasks->count() }})"
                        >
                            <span x-show="!isList">
                                @svg('heroicon-o-chevron-double-left', 'w-4 h-4 text-[var(--planner-status-done)] mt-1')
                            </span>
                            <span x-show="isList">
                                @svg('heroicon-o-chevron-double-up', 'w-4 h-4 text-[var(--planner-status-done)]')
                            </span>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-[var(--planner-status-done)]"
                                :class="!isList ? 'flex-1 my-2' : ''"
                                :style="!isList ? 'writing-mode: vertical-rl; transform: rotate(180deg);' : ''"
                            >
                                {{ $done->label ?? 'Erledigt' }}
                            </span>
                            <span
                                class="inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1 text-[10px] font-semibold rounded-full tabular-nums"
                                style="background-color: color-mix(in srgb, var(--planner-col-done) 18%, transparent); color: var(--planner-col-done)"
                            >
                                {{ $done->tasks->count() }}
                            </span>
                            <span x-show="isList" class="text-[11px] text-[var(--ui-muted)] ml-auto mr-2">
                                Klick zum Anzeigen
                            </span>
                        </button>
                    @endif
                @endif
            </x-ui-kanban-container>
        </div>
    </div>

    <livewire:planner.delegated-task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
