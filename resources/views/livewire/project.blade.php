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
        <x-planner::actionbar :project="$project" active="board" task-action="$wire.createTask()"
            :filters-active="count($filterTagIds) + ($filterColor ? 1 : 0)">
            <x-slot:meta>
                {{-- Kennzahlen (offen/überfällig/erledigt); Health steht im Reiter --}}
                <x-nx-badge variant="neutral"
                    title="offen {{ $headerOpenCount }} · überfällig {{ $headerOverdueCount }} · erledigt {{ $headerDoneCount }}">
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

            @can('update', $project)
                <x-slot:create>
                    <x-planner::menu-item icon="heroicon-o-square-2-stack" wire:click="createProjectSlot">Spalte</x-planner::menu-item>
                </x-slot:create>
            @endcan

            @if($this->hasPlannerCaldavSubscription())
                <x-slot:more>
                    <x-planner::menu-item :icon="$this->caldavExposed() ? 'heroicon-s-bell-alert' : 'heroicon-o-bell'" wire:click="toggleCaldavExposure"
                        title="Dieses Projekt als eigene Liste in meiner Aufgaben-App (Erinnerungen) zeigen">
                        {{ $this->caldavExposed() ? 'In Erinnerungen-App ✓' : 'In Erinnerungen-App zeigen' }}
                    </x-planner::menu-item>
                </x-slot:more>
            @endif
        </x-planner::actionbar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">
    {{-- Kickoff offen: ein Projekt beginnt im Canvas (Ziel, Beteiligte, Nutzen, Risiken) --}}
    @if(! ($canvasInfo['kickoff_done'] ?? true))
        <div class="shrink-0 flex items-center gap-3 px-4 py-2 border-b border-[color:var(--nx-line)] bg-[var(--nx-warning)]/10 text-xs">
            @svg('heroicon-o-rocket-launch', 'w-4 h-4 flex-shrink-0 text-[var(--nx-warning)]')
            <span class="flex-1 min-w-0 text-[var(--nx-text)]">
                <span class="font-medium">Kickoff offen.</span>
                Im Canvas festhalten, was das Projekt erreichen soll, wer beteiligt ist und welche Risiken es gibt — dann den Kickoff abschließen.
            </span>
            <x-nx-button variant="primary" size="sm" wire:click="openCanvas">Zum Kickoff</x-nx-button>
        </div>
    @endif
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
                    <x-nx-kanban-collapsed :title="($done->label ?? 'Erledigt')" :count="$done->tasks->count()" wire:click="toggleShowDoneColumn" />
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
