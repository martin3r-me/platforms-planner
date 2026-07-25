@php
    $allTasks = $groups->flatMap(fn($g) => $g->tasks);
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasks = $groups->filter(fn($g) => ($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);

    $headerOpenCount = $openTasks->count();
    $headerDoneCount = $doneTasks->count();
    $headerOverdueCount = $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && $t->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::ACTIVE)->count();
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
                'overdue' => $tasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && $t->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::ACTIVE)->count(),
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
    <x-slot name="navbar">
        <x-ui-page-navbar title="Delegierte Aufgaben" icon="heroicon-o-user-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Delegierte Aufgaben'],
        ]">
            {{-- Kennzahlen als EIN Badge rechts (Projekt-Standard) --}}
            <x-nx-badge title="offen {{ $headerOpenCount }} · überfällig {{ $headerOverdueCount }} · Frösche {{ $frogCount }}{{ $openPoints > 0 ? ' · ' . $openPoints . ' SP' : '' }}">
                <span class="inline-flex items-center gap-1 text-[color:var(--nx-text)]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-info)]"></span>
                    <span class="tabular-nums">{{ $headerOpenCount }}</span>
                </span>
                @if($headerOverdueCount > 0)
                    <span class="inline-flex items-center gap-1 text-[color:var(--nx-danger)]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-danger)]"></span>
                        <span class="tabular-nums">{{ $headerOverdueCount }}</span>
                    </span>
                @endif
                @if($frogCount > 0)
                    <span class="inline-flex items-center gap-1"><span>🐸</span><span class="tabular-nums">{{ $frogCount }}</span></span>
                @endif
                @if($openPoints > 0)
                    <span class="opacity-30" aria-hidden="true">·</span>
                    <span class="tabular-nums">{{ $openPoints }} SP</span>
                @endif
            </x-nx-badge>

            {{-- Aktionen im Overflow-Menü (keine sichtbaren Buttons — Projekt-Standard) --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open"
                    class="inline-flex items-center justify-center w-8 h-7 rounded-md text-[var(--nx-muted)] hover:text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors" title="Mehr">
                    @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
                </button>
                <div x-show="open" x-cloak x-transition.opacity.duration.100ms @click.outside="open = false" @keydown.escape.window="open = false"
                    class="absolute top-full right-0 mt-1 w-52 bg-[color:var(--nx-surface)] border border-[var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)] z-30 py-1">
                    <button type="button" wire:click="createTask()" @click="open = false"
                        class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors">
                        @svg('heroicon-o-plus', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span>Neue Aufgabe</span>
                    </button>
                    <button type="button" wire:click="createTaskGroup" @click="open = false"
                        class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors">
                        @svg('heroicon-o-square-2-stack', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span>Neue Spalte</span>
                    </button>
                </div>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" icon="heroicon-o-user-group" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--nx-bg)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--nx-text)] leading-relaxed m-0">
                        Aufgaben, die du an andere delegiert hast — sortiert in deinen Bearbeitungsspalten.
                    </p>
                </section>

                {{-- PRO PERSON --}}
                @if($byPerson->isNotEmpty())
                    <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Pro Person</h3>
                        <ul class="space-y-2.5">
                            @foreach($byPerson->take(8) as $p)
                                @php
                                    $loadPct = round(($p['count'] / $maxPerPerson) * 100);
                                    $loadColor = $p['overdue'] > 0 ? 'var(--nx-danger)' : ($loadPct >= 80 ? '#d97706' : 'var(--nx-accent)');
                                    $initial = mb_strtoupper(mb_substr($p['name'], 0, 1));
                                @endphp
                                <li class="flex items-center gap-2 text-[11px]">
                                    @if($p['avatar'])
                                        <img src="{{ $p['avatar'] }}" alt="" class="w-5 h-5 rounded-full object-cover flex-shrink-0">
                                    @else
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--nx-text)] text-white text-[9px] font-semibold flex-shrink-0">{{ $initial }}</span>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="truncate text-[var(--nx-text)] font-medium">{{ $p['name'] }}</span>
                                            <span class="tabular-nums text-[var(--nx-muted)] flex-shrink-0">
                                                {{ $p['count'] }}
                                                @if($p['overdue'] > 0)
                                                    <span class="text-[var(--nx-danger)] font-semibold">/{{ $p['overdue'] }}</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="mt-1 w-full h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                                            <div class="h-full rounded-full transition-all" style="width: {{ $loadPct }}%; background-color: {{ $loadColor }};"></div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if($byPerson->count() > 8)
                            <p class="mt-2 text-[10px] text-[var(--nx-muted)] pl-1">+ {{ $byPerson->count() - 8 }} weitere</p>
                        @endif
                        @if($byPerson->sum('overdue') > 0)
                            <p class="mt-2 text-[10px] text-[var(--nx-danger)] pl-1">
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
                <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)]">Letzte Aktivitäten</div>
                @forelse(($activities ?? []) as $activity)
                    <div class="p-2.5 rounded-lg border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)]">
                        <div class="text-[11px] font-medium text-[var(--nx-text)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                        <div class="text-[10px] text-[var(--nx-muted)] mt-0.5">{{ $activity['time'] ?? '' }}</div>
                    </div>
                @empty
                    <p class="text-[11px] text-[var(--nx-muted)]">Noch keine Aktivität</p>
                @endforelse
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Board --}}
        <div
            class="bg-[color:var(--nx-surface)] flex-1 min-h-0 flex"
            x-data
            @done-column-expanded.window="
                $nextTick(() => {
                    const scroller = $el.querySelector('.overflow-x-auto');
                    if (scroller) scroller.scrollTo({ left: scroller.scrollWidth, behavior: 'smooth' });
                });
            "
        >
            <x-nx-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-nx-kanban-column :title="($backlog->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true" tone="slate" :count="$backlog->tasks->count()">
                        @forelse(($backlog->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--nx-muted)]">
                                @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine delegierten Aufgaben</span>
                            </div>
                        @endforelse
                        <x-slot name="footer">
                            <div x-data="{ open: false, title: '' }">
                                <button x-show="!open" @click="open = true; $nextTick(() => $refs.inlineInput.focus())" class="w-full text-left text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition-colors flex items-center gap-1.5 px-2 py-1">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                    <span>Aufgabe</span>
                                </button>
                                <div x-show="open" x-cloak>
                                    <input x-ref="inlineInput" x-model="title"
                                        @keydown.enter.prevent="if(title.trim()) { $wire.createTask('{{ $backlog->id }}', title.trim()); title = ''; open = false; }"
                                        @keydown.escape="open = false; title = ''"
                                        @click.outside="open = false; title = ''"
                                        type="text" placeholder="Titel eingeben..."
                                        class="w-full text-xs border border-[color:var(--nx-line-strong)] rounded-[6px] px-2 py-1.5 bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] focus:border-[color:var(--nx-accent)] focus:ring-1 focus:ring-[color:var(--nx-accent)] outline-none" />
                                </div>
                            </div>
                        </x-slot>
                    </x-nx-kanban-column>
                @endif

                @foreach($middleColumns as $column)
                    @php $tone = $columnTones[$column->id] ?? 'indigo'; @endphp
                    <x-nx-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" :tone="$tone" :count="$column->tasks->count()">
                        <x-slot name="headerActions">
                            <button
                                wire:click="createTask('{{ $column->id ?? 0 }}')"
                                class="text-[var(--nx-muted)] hover:text-[var(--nx-accent)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                            <button
                                @click="$dispatch('open-modal-delegated-task-group-settings', { taskGroupId: {{ $column->id ?? 0 }} })"
                                class="text-[var(--nx-muted)] hover:text-[var(--nx-accent)] transition-colors"
                                title="Gruppen-Einstellungen"
                            >
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        </x-slot>
                        @forelse(($column->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--nx-muted)]">
                                @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine Aufgaben</span>
                                <span class="text-[10px] mt-0.5 opacity-60">Hierher ziehen oder neu erstellen</span>
                            </div>
                        @endforelse
                        <x-slot name="footer">
                            <div x-data="{ open: false, title: '' }">
                                <button x-show="!open" @click="open = true; $nextTick(() => $refs.inlineInput.focus())" class="w-full text-left text-xs text-[var(--nx-muted)] hover:text-[var(--nx-accent)] transition-colors flex items-center gap-1.5">
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
                                        class="w-full text-xs border border-[var(--nx-line-strong)] rounded px-2 py-1.5 bg-[color:var(--nx-surface)] focus:border-[var(--nx-accent)] focus:ring-1 focus:ring-[var(--nx-accent)]/30 outline-none"
                                    />
                                </div>
                            </div>
                        </x-slot>
                    </x-nx-kanban-column>
                @endforeach

                {{-- Done column / strip --}}
                @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
                @if($done)
                    @if($showDoneColumn)
                        <x-nx-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true" tone="emerald" :count="$done->tasks->count()">
                            <x-slot name="headerActions">
                                <button
                                    type="button"
                                    wire:click="toggleShowDoneColumn"
                                    class="text-[var(--nx-muted)] hover:text-[var(--nx-text)] transition-colors"
                                    title="Einklappen"
                                >
                                    @svg('heroicon-o-chevron-double-right', 'w-4 h-4')
                                </button>
                            </x-slot>
                            @forelse(($done->tasks ?? []) as $task)
                                @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                            @empty
                                <div class="flex flex-col items-center justify-center py-8 text-[var(--nx-muted)]">
                                    @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                                    <span class="text-xs">Noch nichts erledigt</span>
                                </div>
                            @endforelse
                        </x-nx-kanban-column>
                    @else
                        <button
                            x-data="{ isList: localStorage.getItem('kanbanView') === 'list' }"
                            x-init="this.isList = localStorage.getItem('kanbanView') === 'list'"
                            @storage-change.window="isList = localStorage.getItem('kanbanView') === 'list'"
                            type="button"
                            wire:click="toggleShowDoneColumn"
                            :class="isList
                                ? 'group/done sticky bottom-0 z-20 w-full flex flex-row items-center justify-start gap-3 py-3 px-4 pr-14 bg-[color:var(--nx-surface)] border-t border-[rgba(47,158,68,0.25)] hover:bg-[rgba(47,158,68,0.08)] transition-colors cursor-pointer'
                                : 'group/done sticky right-0 z-10 flex-shrink-0 h-full flex flex-col items-center justify-between py-4 px-2 bg-[color:var(--nx-surface)] border-l border-[color:var(--nx-line)] hover:bg-[color:var(--nx-hover)] transition-colors cursor-pointer'"
                            :style="!isList ? 'width: 2.75rem; min-width: 2.75rem;' : ''"
                            title="Erledigte anzeigen ({{ $done->tasks->count() }})"
                        >
                            <span x-show="!isList">
                                @svg('heroicon-o-chevron-double-left', 'w-4 h-4 text-[var(--nx-success)] mt-1')
                            </span>
                            <span x-show="isList">
                                @svg('heroicon-o-chevron-double-up', 'w-4 h-4 text-[var(--nx-success)]')
                            </span>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-[var(--nx-success)]"
                                :class="!isList ? 'flex-1 my-2' : ''"
                                :style="!isList ? 'writing-mode: vertical-rl; transform: rotate(180deg);' : ''"
                            >
                                {{ $done->label ?? 'Erledigt' }}
                            </span>
                            <span
                                class="inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1 text-[10px] font-semibold rounded-full tabular-nums"
                                style="background-color: color-mix(in srgb, var(--nx-success) 18%, transparent); color: var(--nx-success)"
                            >
                                {{ $done->tasks->count() }}
                            </span>
                            <span x-show="isList" class="text-[11px] text-[var(--nx-muted)] ml-auto mr-2">
                                Klick zum Anzeigen
                            </span>
                        </button>
                    @endif
                @endif
            </x-nx-kanban-container>
        </div>
    </div>

    <livewire:planner.delegated-task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
