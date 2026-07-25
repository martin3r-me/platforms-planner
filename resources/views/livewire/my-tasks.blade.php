@php
    $allTasks = $groups->flatMap(fn($g) => $g->tasks);
    $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $doneTasks = $groups->filter(fn($g) => ($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
    $hasActiveFilters = !empty($filterTagIds) || $filterColor;

    $headerOpenCount = $openTasks->count();
    $headerDoneCount = $doneTasks->count();
    $headerOverdueCount = $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && $t->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::ACTIVE)->count();
    $frogCount = $openTasks->filter(fn($t) => $t->is_frog)->count();
    $totalCount = $headerOpenCount + $headerDoneCount;
    $donePct = $totalCount > 0 ? round(($headerDoneCount / $totalCount) * 100) : 0;
    $openPoints = $openTasks->sum(fn($t) => $t->story_points?->points() ?? 0);

    // Tone-Mapping für mittlere Spalten
    $tonePalette = ['indigo', 'amber', 'teal', 'violet', 'sky', 'pink', 'rose', 'emerald'];
    $middleColumns = $groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false))->values();
    $columnTones = $middleColumns->mapWithKeys(fn ($col, $i) => [$col->id => $tonePalette[$i % count($tonePalette)]]);

    // Projekt-Breakdown für Sidebar
    $byProject = $openTasks
        ->groupBy(fn($t) => $t->project?->name ?? 'Ohne Projekt')
        ->map(fn($tasks, $name) => [
            'name' => $name,
            'count' => $tasks->count(),
            'color' => $tasks->first()->project?->color ?? null,
            'project_id' => $tasks->first()->project?->id ?? null,
        ])
        ->sortByDesc('count')
        ->values();
@endphp

<x-ui-page
    x-data="{}"
    @keydown.n.window.prevent="$wire.createTask()"
>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Aufgaben" icon="heroicon-o-clipboard-document-check" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Meine Aufgaben'],
        ]">
            {{-- Live-Metriken (konsolidiert aus der alten Content-Header-Leiste) --}}
            <x-slot name="left">
                <div class="hidden md:flex items-center gap-3 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 text-[color:var(--nx-text)]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-info)]"></span>
                        <span class="font-semibold tabular-nums">{{ $headerOpenCount }}</span>
                        <span class="hidden lg:inline text-[color:var(--nx-muted)]">offen</span>
                    </span>
                    @if($headerOverdueCount > 0)
                        <span class="inline-flex items-center gap-1.5 text-[color:var(--nx-danger)]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-danger)]"></span>
                            <span class="font-semibold tabular-nums">{{ $headerOverdueCount }}</span>
                            <span class="hidden lg:inline">überfällig</span>
                        </span>
                    @endif
                    @if($frogCount > 0)
                        <span class="inline-flex items-center gap-1 text-[color:var(--nx-success)]">
                            <span>🐸</span><span class="font-semibold tabular-nums">{{ $frogCount }}</span>
                        </span>
                    @endif
                    @if($openPoints > 0)
                        <span class="hidden lg:inline-flex items-center gap-1.5 text-[color:var(--nx-text)]">
                            <span class="font-semibold tabular-nums">{{ $openPoints }}</span>
                            <span class="text-[color:var(--nx-muted)]">SP</span>
                        </span>
                    @endif
                    @if($totalCount > 0)
                        <span class="hidden xl:inline-flex items-center gap-2">
                            <span class="text-[color:var(--nx-muted)] tabular-nums">{{ $donePct }}%</span>
                            <span class="w-16 h-1 rounded-full bg-[color:var(--nx-line)] overflow-hidden">
                                <span class="block h-full rounded-full bg-[color:var(--nx-success)]" style="width: {{ $donePct }}%"></span>
                            </span>
                        </span>
                    @endif
                </div>
            </x-slot>

            <x-nx-button variant="primary" size="sm" wire:click="createTask()" title="Neue Aufgabe (N)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Aufgabe</span>
            </x-nx-button>
            <x-nx-button variant="ghost" size="sm" wire:click="createTaskGroup">
                @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                <span>Spalte</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" icon="heroicon-o-rectangle-stack" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-5 bg-[var(--nx-bg)]">
                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--nx-text)] leading-relaxed m-0">
                        Alle Aufgaben, die dir gerade gehören — projektübergreifend, sortiert in deinen persönlichen Spalten.
                    </p>
                </section>

                {{-- PRO PROJEKT --}}
                @if($byProject->isNotEmpty())
                    <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Pro Projekt</h3>
                        <ul class="space-y-1">
                            @foreach($byProject->take(8) as $proj)
                                <li>
                                    @if($proj['project_id'])
                                        <a href="{{ route('planner.projects.show', ['plannerProject' => $proj['project_id']]) }}" wire:navigate
                                           class="flex items-center gap-2 px-1.5 py-1 text-[11px] rounded hover:bg-[var(--nx-bg)] transition-colors">
                                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $proj['color'] ?? 'var(--nx-muted)' }};"></span>
                                            <span class="truncate text-[var(--nx-text)] flex-1">{{ $proj['name'] }}</span>
                                            <span class="tabular-nums text-[var(--nx-muted)]">{{ $proj['count'] }}</span>
                                        </a>
                                    @else
                                        <div class="flex items-center gap-2 px-1.5 py-1 text-[11px]">
                                            <span class="w-2 h-2 rounded-full flex-shrink-0 bg-[var(--nx-muted)] opacity-40"></span>
                                            <span class="truncate text-[var(--nx-muted)] flex-1 italic">{{ $proj['name'] }}</span>
                                            <span class="tabular-nums text-[var(--nx-muted)]">{{ $proj['count'] }}</span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($byProject->count() > 8)
                            <p class="mt-1.5 text-[10px] text-[var(--nx-muted)] pl-1.5">+ {{ $byProject->count() - 8 }} weitere</p>
                        @endif
                    </section>
                @endif

                {{-- FROSCH-FOKUS --}}
                @if($frogCount > 0)
                    <section class="p-3 rounded-lg border bg-[var(--nx-success)]/5 border-[var(--nx-success)]/30 shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-success)] mb-1.5">🐸 Frosch-Fokus</h3>
                        <p class="text-[11px] text-[var(--nx-text)] leading-relaxed m-0">
                            <span class="font-semibold tabular-nums">{{ $frogCount }}</span> Frosch{{ $frogCount === 1 ? '' : 'e' }} wartet auf dich — die wichtigsten Brocken zuerst.
                        </p>
                        <a href="{{ route('planner.frog-tasks') }}" wire:navigate class="inline-flex items-center gap-1 mt-2 text-[10px] font-medium text-[var(--nx-success)] hover:underline">
                            Frösche öffnen
                            @svg('heroicon-o-arrow-right', 'w-3 h-3')
                        </a>
                    </section>
                @endif

                {{-- IN ERINNERUNGEN ABONNIEREN (CalDAV) --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-1.5">📅 In Erinnerungen abonnieren</h3>
                    <p class="text-[11px] text-[var(--nx-text)] leading-relaxed m-0 mb-2">
                        Deine Aufgaben als Liste in Apple Erinnerungen (CalDAV) — schreibgeschützt.
                    </p>

                    {{-- Neues Secret + URL: nur einmalig sichtbar --}}
                    @if($newCaldavSecret)
                        <div class="rounded border border-[rgba(232,89,12,0.30)] bg-[rgba(232,89,12,0.10)] p-2 mb-2 space-y-1.5">
                            <p class="text-[10px] font-medium text-[color:var(--nx-warning)] m-0">Jetzt am iPhone einrichten (Passwort wird nur einmal gezeigt):</p>
                            <div x-data="{ copied: false }" class="flex items-center gap-1">
                                <code x-ref="caldavnewurl" class="flex-1 px-2 py-1 text-[10px] rounded bg-[color:var(--nx-surface)] border border-[rgba(232,89,12,0.30)] font-mono break-all">{{ $newCaldavUrl }}</code>
                                <button type="button" @click="navigator.clipboard.writeText($refs.caldavnewurl.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)" class="shrink-0 px-2 py-1 text-[10px] rounded border border-[rgba(232,89,12,0.30)] text-[color:var(--nx-warning)]" title="URL kopieren">
                                    <span x-show="!copied">URL</span><span x-show="copied" x-cloak>✓</span>
                                </button>
                            </div>
                            <div x-data="{ copied: false }" class="flex items-center gap-1">
                                <code x-ref="caldavsecret" class="flex-1 px-2 py-1 text-[10px] rounded bg-[color:var(--nx-surface)] border border-[rgba(232,89,12,0.30)] font-mono break-all">{{ $newCaldavSecret }}</code>
                                <button type="button" @click="navigator.clipboard.writeText($refs.caldavsecret.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)" class="shrink-0 px-2 py-1 text-[10px] rounded bg-[color:var(--nx-warning)] text-white" title="Passwort kopieren">
                                    <span x-show="!copied">Passwort</span><span x-show="copied" x-cloak>✓</span>
                                </button>
                            </div>
                            <p class="text-[10px] text-[color:var(--nx-warning)] m-0">Server = obige URL, Benutzer beliebig, Passwort = das Secret.</p>
                        </div>
                    @endif

                    {{-- Neues Abo --}}
                    <div class="flex items-center gap-1">
                        <input type="text" wire:model="caldavName" placeholder="z. B. iPhone" class="flex-1 px-2 py-1 text-[10px] rounded border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-[var(--nx-text)] placeholder:text-[var(--nx-muted)]" />
                        <button type="button" wire:click="createCaldavSubscription" class="shrink-0 px-2.5 py-1 text-[10px] font-medium rounded bg-[var(--nx-accent)] text-white hover:opacity-90">Abo</button>
                    </div>

                    {{-- Aktive Abos (je eigene URL) --}}
                    @foreach($this->caldavSubscriptions() as $sub)
                        <div class="mt-1.5 text-[10px]">
                            <div class="flex items-center justify-between">
                                <span class="text-[var(--nx-text)] font-medium truncate">{{ $sub->name }}</span>
                                <button type="button" wire:click="revokeCaldavSubscription({{ $sub->id }})" wire:confirm="Abo widerrufen? Geräte verlieren den Zugriff." class="shrink-0 text-[color:var(--nx-danger)] hover:underline">widerrufen</button>
                            </div>
                            <div x-data="{ copied: false }" class="flex items-center gap-1 mt-0.5">
                                <code x-ref="u{{ $sub->id }}" class="flex-1 px-2 py-0.5 text-[10px] rounded bg-[var(--nx-bg)] border border-[color:var(--nx-line)] text-[var(--nx-muted)] font-mono break-all">{{ $this->caldavUrlFor($sub->handle) }}</code>
                                <button type="button" @click="navigator.clipboard.writeText($refs.u{{ $sub->id }}.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)" class="shrink-0 px-2 py-0.5 rounded border border-[color:var(--nx-line)] text-[var(--nx-muted)]">
                                    <span x-show="!copied">URL</span><span x-show="copied" x-cloak>✓</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </section>
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

        {{-- Filter-Bar --}}
        @include('planner::livewire.project._filter-bar', [
            'availableFilterTags' => $availableFilterTags,
            'availableFilterColors' => $availableFilterColors,
            'filterTagIds' => $filterTagIds,
            'filterColor' => $filterColor,
            'hasActiveFilters' => $hasActiveFilters,
        ])

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
                {{-- Backlog --}}
                @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
                @if($backlog)
                    <x-nx-kanban-column :title="($backlog->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true" tone="slate" :count="$backlog->tasks->count()">
                        @forelse(($backlog->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'my-tasks'])
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--nx-muted)]">
                                @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Inbox ist leer</span>
                                <span class="text-[10px] mt-0.5 opacity-60">Neue Aufgaben landen hier</span>
                            </div>
                        @endforelse
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
                    </x-nx-kanban-column>
                @endif

                {{-- Middle columns --}}
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
                                @click="$dispatch('open-modal-task-group-settings', { taskGroupId: {{ $column->id ?? 0 }} })"
                                class="text-[var(--nx-muted)] hover:text-[var(--nx-accent)] transition-colors"
                                title="Gruppen-Einstellungen"
                            >
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        </x-slot>
                        @forelse(($column->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'my-tasks'])
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

                {{-- Done column (always present, expanded or collapsed strip) --}}
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
                                @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'my-tasks'])
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

    <livewire:planner.task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
