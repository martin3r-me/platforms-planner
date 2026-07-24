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
    @include('planner::partials.planner-tokens')
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->title" icon="heroicon-o-clipboard-document-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => $project->title],
        ]">
            {{-- Live-Metriken (konsolidiert aus dem alten Content-Header) --}}
            <x-slot name="left">
                @php $metricTotal = $headerOpenCount + $headerDoneCount; $metricPct = $metricTotal > 0 ? round($headerDoneCount / $metricTotal * 100) : 0; @endphp
                <div class="hidden md:flex items-center gap-3 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 text-[color:var(--nx-text)]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-info)]"></span>
                        <span class="font-semibold tabular-nums">{{ $headerOpenCount }}</span>
                        <span class="text-[color:var(--nx-muted)]">offen</span>
                    </span>
                    @if($headerOverdueCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[color:var(--nx-danger)]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-danger)]"></span>
                            <span class="font-semibold tabular-nums">{{ $headerOverdueCount }}</span>
                            <span>überfällig</span>
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1.5 text-[color:var(--nx-text)]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-success)]"></span>
                        <span class="font-semibold tabular-nums">{{ $headerDoneCount }}</span>
                        <span class="text-[color:var(--nx-muted)]">erledigt</span>
                    </span>
                    @if($metricTotal > 0)
                        <span class="inline-flex items-center gap-2">
                            <span class="text-[color:var(--nx-muted)] tabular-nums">{{ $metricPct }}%</span>
                            <span class="w-16 h-1 rounded-full bg-[color:var(--nx-line)] overflow-hidden">
                                <span class="block h-full rounded-full bg-[color:var(--nx-success)]" style="width: {{ $metricPct }}%"></span>
                            </span>
                        </span>
                    @endif
                </div>
            </x-slot>

            {{-- Health-Pille aus juengstem Snapshot — plakativer Einstieg in die Health-Sicht --}}
            @if($latestSnapshot)
                @php
                    $hc = $latestSnapshot->health_color ?? 'gray';
                    $hs = $latestSnapshot->health_score;
                    $healthTones = [
                        'green'  => ['ring' => 'ring-[rgba(47,158,68,.30)]', 'bg' => 'bg-[rgba(47,158,68,.10)]', 'hover' => 'hover:bg-[rgba(47,158,68,.16)]', 'fg' => 'text-[color:var(--nx-success)]', 'dot' => 'bg-[color:var(--nx-success)]', 'border' => 'border-[rgba(47,158,68,.30)]', 'label' => 'Stabil'],
                        'yellow' => ['ring' => 'ring-[rgba(232,89,12,.30)]', 'bg' => 'bg-[rgba(232,89,12,.10)]', 'hover' => 'hover:bg-[rgba(232,89,12,.16)]', 'fg' => 'text-[color:var(--nx-warning)]', 'dot' => 'bg-[color:var(--nx-warning)]', 'border' => 'border-[rgba(232,89,12,.30)]', 'label' => 'Achtung'],
                        'red'    => ['ring' => 'ring-[rgba(224,49,49,.30)]', 'bg' => 'bg-[rgba(224,49,49,.10)]', 'hover' => 'hover:bg-[rgba(224,49,49,.16)]', 'fg' => 'text-[color:var(--nx-danger)]', 'dot' => 'bg-[color:var(--nx-danger)]', 'border' => 'border-[rgba(224,49,49,.30)]', 'label' => 'Brennt'],
                        'gray'   => ['ring' => 'ring-[color:var(--nx-line-strong)]', 'bg' => 'bg-[color:var(--nx-bg)]', 'hover' => 'hover:bg-[color:var(--nx-hover)]', 'fg' => 'text-[color:var(--nx-muted)]', 'dot' => 'bg-[color:var(--nx-faint)]', 'border' => 'border-[color:var(--nx-line-strong)]', 'label' => 'Keine Daten'],
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
                    <span class="flex items-center gap-2 px-3 border-r {{ $t['border'] }}">
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
                   class="inline-flex items-center gap-1.5 px-3 h-9 rounded-lg border border-dashed border-[var(--nx-line-strong)] bg-[color:var(--nx-surface)] hover:bg-[var(--nx-bg)] text-[12px] text-[var(--nx-muted)] hover:text-[var(--nx-text)] transition-colors">
                    @svg('heroicon-o-heart', 'w-4 h-4')
                    <span class="font-medium">Health</span>
                    @svg('heroicon-o-arrow-right', 'w-3 h-3 opacity-50')
                </a>
            @endif

            {{-- Primary action --}}
            @can('update', $project)
                <x-nx-button variant="primary" size="sm" wire:click="createTask()" title="Neue Aufgabe (N)">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Aufgabe</span>
                </x-nx-button>
            @endcan

            {{-- CalDAV: dieses Projekt als eigene Liste in Apple Erinnerungen zeigen (nur bei aktivem Abo) --}}
            @if($this->hasPlannerCaldavSubscription())
                <x-nx-button variant="ghost" size="sm" wire:click="toggleCaldavExposure"
                    title="Dieses Projekt als eigene Liste in meiner Aufgaben-App (Erinnerungen) zeigen">
                    @svg($this->caldavExposed() ? 'heroicon-s-bell-alert' : 'heroicon-o-bell', 'w-4 h-4')
                    <span>{{ $this->caldavExposed() ? 'In App ✓' : 'In App' }}</span>
                </x-nx-button>
            @endif

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
                </x-nx-kanban-column>
            @endif

            {{-- Middle columns --}}
            @foreach($middleColumns as $column)
                @php $tone = $columnTones[$column->id] ?? 'indigo'; @endphp
                <x-nx-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" :tone="$tone" :count="$column->tasks->count()">
                    @can('update', $project)
                        <x-slot name="headerActions">
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
                        </x-slot>
                    @endcan

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
    <livewire:planner.customer-project-settings-modal/>
</x-ui-page>
