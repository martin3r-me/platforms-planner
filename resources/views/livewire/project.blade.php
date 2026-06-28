@php
    $allTasks = $groups->flatMap(fn($g) => $g->tasks);
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
    $headerOpenCount = $openTasks->count();
    $headerDoneCount = $doneTasks->count();
    $headerOverdueCount = $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count();
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
    @include('planner::partials.planner-tokens')
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->title" icon="heroicon-o-clipboard-document-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => $project->title],
        ]">
            {{-- Health-Pille aus juengstem Snapshot — plakativer Einstieg in die Health-Sicht --}}
            @if($latestSnapshot)
                @php
                    $hc = $latestSnapshot->health_color ?? 'gray';
                    $hs = $latestSnapshot->health_score;
                    $healthTones = [
                        'green'  => ['ring' => 'ring-emerald-300', 'bg' => 'bg-emerald-50', 'hover' => 'hover:bg-emerald-100', 'fg' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'border' => 'border-emerald-300', 'label' => 'Stabil'],
                        'yellow' => ['ring' => 'ring-amber-300',   'bg' => 'bg-amber-50',   'hover' => 'hover:bg-amber-100',   'fg' => 'text-amber-700',   'dot' => 'bg-amber-500',   'border' => 'border-amber-300',   'label' => 'Achtung'],
                        'red'    => ['ring' => 'ring-rose-300',    'bg' => 'bg-rose-50',    'hover' => 'hover:bg-rose-100',    'fg' => 'text-rose-700',    'dot' => 'bg-rose-500',    'border' => 'border-rose-300',    'label' => 'Brennt'],
                        'gray'   => ['ring' => 'ring-zinc-200',    'bg' => 'bg-zinc-50',    'hover' => 'hover:bg-zinc-100',    'fg' => 'text-zinc-600',    'dot' => 'bg-zinc-400',    'border' => 'border-zinc-300',    'label' => 'Keine Daten'],
                    ];
                    $t = $healthTones[$hc] ?? $healthTones['gray'];
                    $delta = $latestSnapshot->delta_health_score;
                    $trendArrow = $delta === null || $delta === 0 ? null : ($delta > 0 ? '↑' : '↓');
                    $worstAxisLabel = match($latestSnapshot->worst_axis) {
                        'strategy' => 'Strategie',
                        'progress' => 'Fortschritt',
                        'burn' => 'Druck',
                        default => null,
                    };
                    $tooltipParts = [
                        'Snapshot ' . optional($latestSnapshot->taken_on)->format('d.m.Y'),
                        'Health ' . ($hs ?? '–') . ' (' . $hc . ')',
                        'Confidence ' . $latestSnapshot->confidence_score . '%',
                    ];
                    if($worstAxisLabel) $tooltipParts[] = 'Schwaechste Achse: ' . $worstAxisLabel;
                    if($delta !== null) $tooltipParts[] = 'Veraenderung zum Vortag: ' . ($delta > 0 ? '+' : '') . $delta;
                    if($latestSnapshot->confidence_reason) $tooltipParts[] = $latestSnapshot->confidence_reason;
                @endphp
                <a href="{{ route('planner.projects.health', $project) }}"
                   wire:navigate
                   title="{{ implode(' · ', $tooltipParts) }}"
                   class="group inline-flex items-stretch h-9 rounded-lg border {{ $t['border'] }} {{ $t['bg'] }} {{ $t['hover'] }} text-[12px] {{ $t['fg'] }} font-medium overflow-hidden shadow-sm transition-all hover:shadow-md">
                    {{-- Score block --}}
                    <span class="flex items-center gap-2 px-3 border-r {{ $t['border'] }}/70">
                        <span class="w-2 h-2 rounded-full {{ $t['dot'] }} animate-pulse"></span>
                        <span class="text-base font-bold tabular-nums leading-none">{{ $hs ?? '–' }}</span>
                    </span>
                    {{-- Context block --}}
                    <span class="flex items-center gap-1.5 px-3">
                        @if($worstAxisLabel)
                            <span class="text-[10px] uppercase tracking-wider opacity-70">{{ $worstAxisLabel }}</span>
                        @else
                            <span class="text-[10px] uppercase tracking-wider opacity-70">{{ $t['label'] }}</span>
                        @endif
                        @if($trendArrow)
                            <span class="text-[11px] tabular-nums opacity-80">{{ $trendArrow }}{{ abs($delta) }}</span>
                        @endif
                        @svg('heroicon-o-arrow-top-right-on-square', 'w-3 h-3 opacity-50 group-hover:opacity-100 transition-opacity')
                    </span>
                </a>
            @else
                <a href="{{ route('planner.projects.health', $project) }}"
                   wire:navigate
                   title="Noch kein Snapshot vorhanden — jetzt einen anlegen"
                   class="inline-flex items-center gap-1.5 px-3 h-9 rounded-lg border border-dashed border-[var(--ui-border)] bg-white hover:bg-[var(--ui-muted-5)] text-[12px] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors">
                    @svg('heroicon-o-heart', 'w-4 h-4')
                    <span class="font-medium">Health</span>
                    @svg('heroicon-o-arrow-right', 'w-3 h-3 opacity-50')
                </a>
            @endif

            {{-- Primary action --}}
            @can('update', $project)
                <x-ui-button variant="primary" size="sm" wire:click="createTask()" title="Neue Aufgabe (N)">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Aufgabe</span>
                </x-ui-button>
            @endcan

            {{-- Overflow menu --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center justify-center w-8 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
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
                    class="absolute top-full right-0 mt-1 w-52 bg-white border border-[var(--ui-border)] rounded-lg shadow-lg z-30 py-1"
                >
                    @can('update', $project)
                        <button
                            type="button"
                            wire:click="createProjectSlot"
                            @click="open = false"
                            class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                        >
                            @svg('heroicon-o-square-2-stack', 'w-4 h-4 text-[var(--ui-muted)]')
                            <span>Neue Spalte</span>
                        </button>
                    @endcan
                    <button
                        type="button"
                        wire:click="openCanvas"
                        @click="open = false"
                        class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                    >
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span>Project Canvas</span>
                    </button>
                    <a
                        href="{{ route('planner.projects.health', $project) }}"
                        wire:navigate
                        @click="open = false"
                        class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                    >
                        @svg('heroicon-o-heart', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span>Health-Sicht</span>
                    </a>
                    @can('settings', $project)
                        <div class="border-t border-[var(--ui-border)]/60 my-1"></div>
                        <button
                            type="button"
                            @click="open = false; $dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })"
                            class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--ui-muted)]')
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

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" icon="heroicon-o-bolt" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                            <div class="text-[var(--ui-muted)]">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">
    {{-- Project Header (immer sichtbar, einheitlich für Board + Dashboard) --}}
    @include('planner::livewire.project._header', [
        'project' => $project,
        'openCount' => $headerOpenCount,
        'doneCount' => $headerDoneCount,
        'overdueCount' => $headerOverdueCount,
        'linkedEntities' => $linkedEntities,
        'canvasInfo' => $canvasInfo ?? null,
    ])

    @include('planner::livewire.project._filter-bar', [
        'availableFilterTags' => $availableFilterTags,
        'availableFilterColors' => $availableFilterColors,
        'filterTagIds' => $filterTagIds,
        'filterColor' => $filterColor,
        'hasActiveFilters' => $hasActiveFilters,
    ])

    {{-- Board --}}
    <div
        class="planner-board-canvas flex-1 min-h-0 flex"
        @if($project->color) style="--planner-project-color: {{ $project->color }};" @endif
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
    <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
        {{-- Backlog --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true" class="col-tone-slate">
                    <x-slot name="headerActions">
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-backlog) 15%, transparent); color: var(--planner-col-backlog)">
                            {{ $backlog->tasks->count() }}
                        </span>
                    </x-slot>
                    @forelse($backlog->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                            @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Backlog ist leer</span>
                            <span class="text-[10px] mt-0.5 opacity-60">Neue Aufgaben landen hier</span>
                        </div>
                    @endforelse
                </x-ui-kanban-column>
            @endif

            {{-- Middle columns --}}
            @foreach($middleColumns as $column)
                @php $tone = $columnTones[$column->id] ?? 'indigo'; @endphp
                <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" :class="'col-tone-' . $tone">
                    <x-slot name="headerActions">
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-semibold rounded-full" style="background-color: color-mix(in srgb, var(--planner-col-default) 15%, transparent); color: var(--planner-col-default)">
                            {{ $column->tasks->count() }}
                        </span>
                        @can('update', $project)
                            <button
                                wire:click="createTask('{{ $column->id }}')"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                        @endcan
                        @can('update', $project)
                            <button
                                @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $column->id }} })"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Einstellungen"
                            >
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        @endcan
                    </x-slot>

                    @forelse($column->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                            @svg('heroicon-o-clipboard', 'w-8 h-8 mb-2 opacity-40')
                            <span class="text-xs">Keine Aufgaben</span>
                            <span class="text-[10px] mt-0.5 opacity-60">Hierher ziehen oder neu erstellen</span>
                        </div>
                    @endforelse
                    @can('update', $project)
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
                                        @keydown.enter.prevent="if(title.trim()) { $wire.createTask('{{ $column->id }}', title.trim()); title = ''; open = false; }"
                                        @keydown.escape="open = false; title = ''"
                                        @click.outside="open = false; title = ''"
                                        type="text"
                                        placeholder="Titel eingeben..."
                                        class="w-full text-xs border border-[var(--ui-border)] rounded px-2 py-1.5 bg-white focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)]/30 outline-none"
                                    />
                                </div>
                            </div>
                        </x-slot>
                    @endcan
                </x-ui-kanban-column>
            @endforeach

            {{-- Done column (immer sichtbar — expanded oder collapsed) --}}
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
                        @forelse($done->tasks as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'project'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Noch nichts erledigt</span>
                            </div>
                        @endforelse
                    </x-ui-kanban-column>
                @else
                    {{-- Collapsed-Done: im Board schmaler Streifen rechts, in der Liste eine voll-breite Leiste unten --}}
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

                        {{-- Label: im Board vertikal, in der Liste horizontal --}}
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

                        {{-- Sub-Hinweis nur in Liste --}}
                        <span
                            x-show="isList"
                            class="text-[11px] text-[var(--ui-muted)] ml-auto mr-2"
                        >
                            Klick zum Anzeigen
                        </span>
                    </button>
                @endif
            @endif
        </x-ui-kanban-container>
        </div>
    </div>

    {{-- Modals --}}
    <livewire:planner.project-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>
</x-ui-page>
