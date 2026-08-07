@php
    $allTasks = $groups->flatMap(fn($g) => $g->tasks);
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
    $headerOpenCount = $openTasks->count();
    $headerDoneCount = $doneTasks->count();
    $headerOverdueCount = $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && $t->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::ACTIVE)->count();
    $hasActiveFilters = !empty($filterTagIds) || $filterColor;

    // MeisterTask-Section-Tones — Spalten-Akzentfarben (Slot-color zuerst, sonst rotierend nach Position)
    $tonePalette = ['indigo', 'amber', 'teal', 'violet', 'sky', 'pink', 'rose', 'emerald'];
    $validTones = ['indigo','amber','teal','violet','sky','pink','rose','emerald','slate'];
    $middleColumns = $groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false))->values();
    $columnTones = $middleColumns->mapWithKeys(function ($col, $i) use ($tonePalette, $validTones) {
        $slotColor = $col->color ?? null;
        $tone = in_array($slotColor, $validTones, true) ? $slotColor : $tonePalette[$i % count($tonePalette)];
        return [$col->id => $tone];
    });
@endphp

<x-ui-page
    x-data
    @keydown.n.window.prevent="$wire.createTask()"
>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->title" icon="heroicon-o-clipboard-document-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => $project->title],
        ]">
            {{-- Kennzahlen (offen/überfällig/erledigt) + Health-Index in EINEM
                 klickbaren Badge; Ton = Health-Status, Zahlen mit semantischem Punkt --}}
            @php
                $healthVariant = match($latestSnapshot?->health_color ?? 'gray') {
                    'green' => 'success', 'yellow' => 'warning', 'red' => 'danger', default => 'neutral',
                };
                $hScore = $latestSnapshot?->health_score;
                $hDelta = $latestSnapshot?->delta_health_score;
                $hTrend = ($hDelta === null || $hDelta === 0) ? null : ($hDelta > 0 ? '↑' : '↓');
            @endphp
            <x-nx-badge :variant="$healthVariant" :href="route('planner.projects.health', $project)"
                title="offen {{ $headerOpenCount }} · überfällig {{ $headerOverdueCount }} · erledigt {{ $headerDoneCount }}{{ $hScore !== null ? ' · Health ' . $hScore : '' }}">
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
                <span class="inline-flex items-center gap-1 text-[color:var(--nx-text)]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-success)]"></span>
                    <span class="tabular-nums">{{ $headerDoneCount }}</span>
                </span>
                <span class="opacity-30" aria-hidden="true">·</span>
                <span class="inline-flex items-center gap-1">
                    <span class="h-1.5 w-1.5 rounded-full" style="background: currentColor"></span>
                    <span class="tabular-nums">{{ $hScore ?? 'Health' }}</span>
                    @if($hTrend)<span class="tabular-nums opacity-80">{{ $hTrend }}{{ abs($hDelta) }}</span>@endif
                </span>
            </x-nx-badge>

            {{-- Overflow menu --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center justify-center w-8 h-7 rounded-md text-[var(--nx-muted)] hover:text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                    title="Mehr"
                >
                    @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    class="absolute top-full right-0 mt-1 w-52 bg-[color:var(--nx-surface)] border border-[var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)] z-30 py-1"
                >
                    @can('update', $project)
                        <button
                            type="button"
                            wire:click="createTask()"
                            @click="open = false"
                            class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                        >
                            @svg('heroicon-o-plus', 'w-4 h-4 text-[var(--nx-muted)]')
                            <span>Neue Aufgabe</span>
                        </button>
                        <button
                            type="button"
                            wire:click="createProjectSlot"
                            @click="open = false"
                            class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                        >
                            @svg('heroicon-o-square-2-stack', 'w-4 h-4 text-[var(--nx-muted)]')
                            <span>Neue Spalte</span>
                        </button>
                    @endcan
                    <button
                        type="button"
                        wire:click="openCanvas"
                        @click="open = false"
                        class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                    >
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span>Project Canvas</span>
                    </button>
                    @if($this->hasPlannerCaldavSubscription())
                        <button
                            type="button"
                            wire:click="toggleCaldavExposure"
                            @click="open = false"
                            class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                            title="Dieses Projekt als eigene Liste in meiner Aufgaben-App (Erinnerungen) zeigen"
                        >
                            @svg($this->caldavExposed() ? 'heroicon-s-bell-alert' : 'heroicon-o-bell', 'w-4 h-4 text-[var(--nx-muted)]')
                            <span>{{ $this->caldavExposed() ? 'In App ✓' : 'In App zeigen' }}</span>
                        </button>
                    @endif
                    <a
                        href="{{ route('planner.projects.health', $project) }}"
                        wire:navigate
                        @click="open = false"
                        class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                    >
                        @svg('heroicon-o-heart', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span>Health-Sicht</span>
                    </a>
                    @can('settings', $project)
                        <div class="border-t border-[color:var(--nx-line)] my-1"></div>
                        <button
                            type="button"
                            @click="open = false; $dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })"
                            class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--nx-muted)]')
                            <span>Einstellungen</span>
                        </button>
                    @endcan
                </div>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar
            title="Dashboard"
            icon="heroicon-o-chart-bar-square"
            width="w-96"
            :minWidth="280"
            :maxWidth="720"
            :defaultOpen="true"
        >
            @if($dashboardData)
                @include('planner::livewire.project._dashboard', [
                    'dashboardData' => $dashboardData,
                    'project' => $project,
                ])
            @endif
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">
    @include('planner::livewire.project._filter-bar', [
        'availableFilterTags' => $availableFilterTags,
        'availableFilterColors' => $availableFilterColors,
        'filterTagIds' => $filterTagIds,
        'filterColor' => $filterColor,
        'hasActiveFilters' => $hasActiveFilters,
    ])

    {{-- Board --}}
    <div
        class="flex-1 min-h-0 flex bg-[color:var(--nx-surface)]"
        x-data="{
            scrollKey: 'planner-project-{{ $project->id }}-scroll-x',
            scroller: null,
            saveTimer: null,
            saveScroll() {
                if (!this.scroller) return;
                try { sessionStorage.setItem(this.scrollKey, String(this.scroller.scrollLeft)); } catch (e) {}
            },
            initScrollMemory() {
                this.$nextTick(() => {
                    this.scroller = this.$el.querySelector('.overflow-x-auto');
                    if (!this.scroller) return;
                    let saved = null;
                    try { saved = sessionStorage.getItem(this.scrollKey); } catch (e) {}
                    if (saved !== null) {
                        const x = parseInt(saved, 10) || 0;
                        this.scroller.scrollLeft = x;
                        // Layout setzt sich manchmal erst spaeter — zweite Korrektur
                        requestAnimationFrame(() => { if (this.scroller) this.scroller.scrollLeft = x; });
                    }
                    this.scroller.addEventListener('scroll', () => {
                        clearTimeout(this.saveTimer);
                        this.saveTimer = setTimeout(() => this.saveScroll(), 200);
                    }, { passive: true });
                });
            }
        }"
        x-init="initScrollMemory()"
        x-on:livewire:navigating.window="saveScroll()"
        x-on:beforeunload.window="saveScroll()"
        @done-column-expanded.window="
            $nextTick(() => {
                const s = $el.querySelector('.overflow-x-auto');
                if (s) {
                    s.scrollTo({ left: s.scrollWidth, behavior: 'smooth' });
                }
            });
        "
    >
    <x-nx-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
        {{-- Backlog --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-nx-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true" tone="slate" :count="$backlog->tasks->count()">
                    @forelse($backlog->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[color:var(--nx-muted)]">
                            @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Backlog ist leer</span>
                            <span class="text-[10px] mt-0.5 opacity-60">Neue Aufgaben landen hier</span>
                        </div>
                    @endforelse
                    @can('update', $project)
                        <x-slot name="footer">
                            <div x-data="{ open: false, title: '' }">
                                <button x-show="!open" @click="open = true; $nextTick(() => $refs.inlineInput.focus())" class="w-full text-left text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition-colors flex items-center gap-1.5 px-2 py-1">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                    <span>Aufgabe</span>
                                </button>
                                <div x-show="open" x-cloak>
                                    <input
                                        x-ref="inlineInput"
                                        x-model="title"
                                        @keydown.enter.prevent="if(title.trim()) { $wire.createTask('{{ $backlog->id }}', title.trim()); title = ''; open = false; }"
                                        @keydown.escape="open = false; title = ''"
                                        @click.outside="open = false; title = ''"
                                        type="text"
                                        placeholder="Titel eingeben..."
                                        class="w-full text-xs border border-[color:var(--nx-line-strong)] rounded-[6px] px-2 py-1.5 bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] focus:border-[color:var(--nx-accent)] focus:ring-1 focus:ring-[color:var(--nx-accent)] outline-none"
                                    />
                                </div>
                            </div>
                        </x-slot>
                    @endcan
                </x-nx-kanban-column>
            @endif

            {{-- Middle columns --}}
            @foreach($middleColumns as $column)
                @php $tone = $columnTones[$column->id] ?? 'indigo'; @endphp
                <x-nx-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" :tone="$tone" :count="$column->tasks->count()">
                    {{-- headerActions immer setzen: Gate-Icon für alle sichtbar, Edit-Buttons nur für Editoren --}}
                    <x-slot name="headerActions">
                        @if($column->gated)
                            <span
                                title="{{ $column->gate_blocked ? 'Gesperrt — wartet auf vorherige Spalten' : 'Erst nach vorherigen Spalten (Sequenz)' }}"
                                class="{{ $column->gate_blocked ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-faint)]' }}"
                            >
                                @svg('heroicon-o-lock-closed', 'w-4 h-4')
                            </span>
                        @endif
                        @can('update', $project)
                            <button
                                wire:click="createTask('{{ $column->id }}')"
                                class="text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                            <button
                                @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $column->id }} })"
                                class="text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)] transition-colors"
                                title="Einstellungen"
                            >
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        @endcan
                    </x-slot>

                    {{-- Gate aktiv: Hinweis, warum der Agent hier (noch) nichts zieht --}}
                    @if($column->gate_blocked)
                        <div class="mb-2 flex items-center gap-1.5 rounded-lg bg-[color:var(--nx-warning)]/10 px-2.5 py-1.5 text-[11px] text-[color:var(--nx-warning)]">
                            @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5 flex-shrink-0')
                            <span>Wartet auf vorherige Spalten</span>
                        </div>
                    @endif

                    @forelse($column->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[color:var(--nx-muted)]">
                            @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Keine Aufgaben</span>
                            <span class="text-[10px] mt-0.5 opacity-60">Hierher ziehen oder neu erstellen</span>
                        </div>
                    @endforelse
                    @can('update', $project)
                        <x-slot name="footer">
                            <div x-data="{ open: false, title: '' }">
                                <button x-show="!open" @click="open = true; $nextTick(() => $refs.inlineInput.focus())" class="w-full text-left text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition-colors flex items-center gap-1.5 px-2 py-1">
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
                    @endcan
                </x-nx-kanban-column>
            @endforeach

            {{-- Done column (immer sichtbar — expanded oder collapsed) --}}
            @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($done)
                @if($showDoneColumn)
                    <x-nx-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true" tone="emerald" :count="$done->tasks->count()">
                        <x-slot name="headerActions">
                            <button
                                type="button"
                                wire:click="toggleShowDoneColumn"
                                class="text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)] transition-colors"
                                title="Einklappen"
                            >
                                @svg('heroicon-o-chevron-double-right', 'w-4 h-4')
                            </button>
                        </x-slot>
                        @forelse($done->tasks as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[color:var(--nx-muted)]">
                                @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Noch nichts erledigt</span>
                            </div>
                        @endforelse
                    </x-nx-kanban-column>
                @else
                    {{-- Collapsed-Done: im Board schmaler Streifen rechts, in der Liste eine voll-breite Leiste unten --}}
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
                            @svg('heroicon-o-chevron-double-left', 'w-4 h-4 text-[color:var(--nx-success)] mt-1')
                        </span>
                        <span x-show="isList">
                            @svg('heroicon-o-chevron-double-up', 'w-4 h-4 text-[color:var(--nx-success)]')
                        </span>

                        {{-- Label: im Board vertikal, in der Liste horizontal --}}
                        <span
                            class="text-[10px] font-bold uppercase tracking-wider text-[color:var(--nx-success)]"
                            :class="!isList ? 'flex-1 my-2' : ''"
                            :style="!isList ? 'writing-mode: vertical-rl; transform: rotate(180deg);' : ''"
                        >
                            {{ $done->label ?? 'Erledigt' }}
                        </span>

                        <span
                            class="inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1 text-[10px] font-semibold rounded-full tabular-nums text-[color:var(--nx-success)] bg-[rgba(47,158,68,0.16)]"
                        >
                            {{ $done->tasks->count() }}
                        </span>

                        {{-- Sub-Hinweis nur in Liste --}}
                        <span
                            x-show="isList"
                            class="text-[11px] text-[color:var(--nx-muted)] ml-auto mr-2"
                        >
                            Klick zum Anzeigen
                        </span>
                    </button>
                @endif
            @endif
        </x-nx-kanban-container>
        </div>
    </div>

    {{-- Modals --}}
    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
