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
        <x-planner::actionbar active="my-tasks" task-action="$wire.createTask()"
            :filters-active="count($filterTagIds) + ($filterColor ? 1 : 0)">
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

            @if($availableFilterTags->isNotEmpty() || $availableFilterColors->isNotEmpty() || $hasActiveFilters)
                <x-slot:filters>
                    @if($availableFilterTags->isNotEmpty())
                        <x-planner::filter-group label="Tags">
                            @foreach($availableFilterTags as $tag)
                                <x-planner::chip :active="in_array($tag['id'], $filterTagIds)" :dot="$tag['color']" wire:click="toggleTagFilter({{ $tag['id'] }})">{{ $tag['label'] }}</x-planner::chip>
                            @endforeach
                        </x-planner::filter-group>
                    @endif
                    @if($availableFilterColors->isNotEmpty())
                        <x-planner::filter-group label="Farbe">
                            @foreach($availableFilterColors as $color)
                                <button type="button" wire:click="toggleColorFilter('{{ $color }}')" title="{{ $color }}"
                                        class="w-6 h-6 sm:w-5 sm:h-5 rounded-full border-2 transition-all {{ $filterColor === $color ? 'border-[var(--nx-accent)] ring-2 ring-[var(--nx-accent)]/30' : 'border-[color:var(--nx-line)] hover:border-[var(--nx-accent)]/60' }}"
                                        style="background-color: {{ $color }}"></button>
                            @endforeach
                        </x-planner::filter-group>
                    @endif
                    @if($hasActiveFilters)
                        <button type="button" wire:click="clearFilters" class="inline-flex items-center gap-1 text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)]">
                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5') Filter zurücksetzen
                        </button>
                    @endif
                </x-slot:filters>
            @endif

            <x-slot:create>
                <x-planner::menu-item icon="heroicon-o-square-2-stack" wire:click="createTaskGroup">Gruppe</x-planner::menu-item>
            </x-slot:create>

            <x-slot:more>
                <x-planner::menu-item icon="heroicon-o-calendar-days" x-on:click="$dispatch('planner-caldav-toggle')"
                    title="Deine Aufgaben als Liste in Apple Erinnerungen (CalDAV)">In Erinnerungen abonnieren</x-planner::menu-item>
            </x-slot:more>
        </x-planner::actionbar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- In Erinnerungen abonnieren (CalDAV) — über ⋯ ein-/ausblendbar --}}
        <div x-data="{ show: @js((bool) $newCaldavSecret) }" @planner-caldav-toggle.window="show = !show"
             x-show="show" x-cloak
             class="shrink-0 max-h-[60vh] overflow-y-auto border-b border-[color:var(--nx-line)] bg-[var(--nx-bg)]">
            <section class="px-3 sm:px-4 py-3">
                <div class="flex items-start justify-between gap-3 mb-1.5">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">📅 In Erinnerungen abonnieren</h3>
                    <button type="button" @click="show = false" class="shrink-0 text-[var(--nx-muted)] hover:text-[var(--nx-text)]" title="Schließen">
                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                    </button>
                </div>
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

        {{-- Pro Projekt — kompakte Leiste --}}
        @if($byProject->isNotEmpty())
            <div class="shrink-0 flex items-center gap-1.5 px-3 sm:px-4 h-9 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] overflow-x-auto"
                 title="Alle Aufgaben, die dir gerade gehören — projektübergreifend, sortiert in deinen persönlichen Spalten.">
                <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mr-1">Pro Projekt</span>
                @foreach($byProject as $proj)
                    @if($proj['project_id'])
                        <a href="{{ route('planner.projects.show', ['plannerProject' => $proj['project_id']]) }}" wire:navigate
                           class="shrink-0 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] bg-[var(--nx-bg)] hover:bg-[var(--nx-hover)] transition-colors">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $proj['color'] ?? 'var(--nx-muted)' }};"></span>
                            <span class="max-w-[10rem] truncate text-[var(--nx-text)]">{{ $proj['name'] }}</span>
                            <span class="tabular-nums text-[var(--nx-muted)]">{{ $proj['count'] }}</span>
                        </a>
                    @else
                        <span class="shrink-0 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] bg-[var(--nx-bg)]">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 bg-[var(--nx-muted)] opacity-40"></span>
                            <span class="max-w-[10rem] truncate text-[var(--nx-muted)] italic">{{ $proj['name'] }}</span>
                            <span class="tabular-nums text-[var(--nx-muted)]">{{ $proj['count'] }}</span>
                        </span>
                    @endif
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
                                    @click="$dispatch('open-modal-task-group-settings', { taskGroupId: {{ $column->id }} })"
                                    class="text-[var(--nx-muted)] hover:text-[var(--nx-accent)] transition-colors"
                                    title="Gruppen-Einstellungen"
                                >
                                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                                </button>
                            </x-slot>
                        @endif
                        @forelse(($column->tasks ?? []) as $task)
                            @include('planner::livewire.task-preview-card', ['task' => $task, 'cardFrom' => 'my-tasks'])
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
                        <x-nx-kanban-collapsed :title="($done->label ?? 'Erledigt')" :count="$done->tasks->count()" wire:click="toggleShowDoneColumn" />
                    @endif
                @endif
            </x-nx-kanban-container>
        </div>
    </div>

    <livewire:planner.task-group-settings-modal/>
    <livewire:planner.project-slot-settings-modal/>
</x-ui-page>
