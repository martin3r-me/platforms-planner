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
        <x-planner::actionbar active="delegated" task-action="$wire.createTask()">
            <x-slot:meta>
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
            </x-slot:meta>

            <x-slot:create>
                <x-planner::menu-item icon="heroicon-o-square-2-stack" wire:click="createTaskGroup">Gruppe</x-planner::menu-item>
            </x-slot:create>
        </x-planner::actionbar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Pro Person — kompakte Leiste (Balken = Last; rot = überfällig vorhanden) --}}
        @if($byPerson->isNotEmpty())
            <div class="shrink-0 flex items-center gap-2 px-3 sm:px-4 py-1.5 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] overflow-x-auto"
                 title="Aufgaben, die du an andere delegiert hast — sortiert in deinen Bearbeitungsspalten.">
                <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mr-1">Pro Person</span>
                @foreach($byPerson as $p)
                    @php
                        $loadPct = round(($p['count'] / $maxPerPerson) * 100);
                        $loadColor = $p['overdue'] > 0 ? 'var(--nx-danger)' : ($loadPct >= 80 ? '#d97706' : 'var(--nx-accent)');
                        $initial = mb_strtoupper(mb_substr($p['name'], 0, 1));
                    @endphp
                    <div class="shrink-0 flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-md bg-[var(--nx-bg)] text-[11px]"
                         title="{{ $p['name'] }}: {{ $p['count'] }} offen{{ $p['overdue'] > 0 ? ', ' . $p['overdue'] . ' überfällig' : '' }}">
                        @if($p['avatar'])
                            <img src="{{ $p['avatar'] }}" alt="" class="w-5 h-5 rounded-full object-cover flex-shrink-0">
                        @else
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--nx-text)] text-white text-[9px] font-semibold flex-shrink-0">{{ $initial }}</span>
                        @endif
                        <div class="w-20 sm:w-24 min-w-0">
                            <div class="flex items-center justify-between gap-1.5">
                                <span class="truncate text-[var(--nx-text)] font-medium">{{ $p['name'] }}</span>
                                <span class="tabular-nums text-[var(--nx-muted)] flex-shrink-0">
                                    {{ $p['count'] }}@if($p['overdue'] > 0)<span class="text-[var(--nx-danger)] font-semibold">/{{ $p['overdue'] }}</span>@endif
                                </span>
                            </div>
                            <div class="mt-0.5 w-full h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                                <div class="h-full rounded-full transition-all" style="width: {{ $loadPct }}%; background-color: {{ $loadColor }};"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif


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
                    @php $isRealGroup = is_numeric($column->id); @endphp
                    <x-nx-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$isRealGroup ? $column->id : null" :scrollable="true" :tone="$tone" :count="$column->tasks->count()">
                        @if($isRealGroup)
                            <x-slot name="headerActions">
                                <button
                                    wire:click="createTask('{{ $column->id }}')"
                                    class="text-[var(--nx-muted)] hover:text-[var(--nx-accent)] transition-colors"
                                    title="Neue Aufgabe"
                                >
                                    @svg('heroicon-o-plus-circle', 'w-4 h-4')
                                </button>
                                <button
                                    @click="$dispatch('open-modal-delegated-task-group-settings', { taskGroupId: {{ $column->id }} })"
                                    class="text-[var(--nx-muted)] hover:text-[var(--nx-accent)] transition-colors"
                                    title="Gruppen-Einstellungen"
                                >
                                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                                </button>
                            </x-slot>
                        @endif
                        @forelse(($column->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'delegated'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--nx-muted)]">
                                @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine Aufgaben</span>
                                <span class="text-[10px] mt-0.5 opacity-60">Hierher ziehen oder neu erstellen</span>
                            </div>
                        @endforelse
                        @if($isRealGroup)
                            <x-slot name="footer">
                                <div x-data="{ open: false, title: '' }">
                                    <button x-show="!open" @click="open = true; $nextTick(() => $refs.inlineInput.focus())" class="w-full text-left text-xs text-[var(--nx-muted)] hover:text-[var(--nx-text)] transition-colors flex items-center gap-1.5 px-2 py-1">
                                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                        <span>Aufgabe</span>
                                    </button>
                                    <div x-show="open" x-cloak>
                                        <input
                                            x-ref="inlineInput"
                                            x-model="title"
                                            @keydown.enter.prevent="if(title.trim()) { $wire.createTask('{{ $column->id }}', title.trim()); title = ''; open = false; }"
                                            @keydown.escape="open = false; title = ''"
                                            @click.outside="open = false; title = ''"
                                            type="text"
                                            placeholder="Titel eingeben..."
                                            class="w-full text-xs border border-[color:var(--nx-line-strong)] rounded-[6px] px-2 py-1.5 bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] focus:border-[color:var(--nx-accent)] focus:ring-1 focus:ring-[color:var(--nx-accent)] outline-none"
                                        />
                                    </div>
                                </div>
                            </x-slot>
                        @endif
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
                        <x-nx-kanban-collapsed :title="($done->label ?? 'Erledigt')" :count="$done->tasks->count()" wire:click="toggleShowDoneColumn" />
                    @endif
                @endif
            </x-nx-kanban-container>
        </div>
    </div>

    <livewire:planner.delegated-task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
