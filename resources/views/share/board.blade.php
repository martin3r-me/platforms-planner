{{--
    Kunden-Tor · Projekt-Board (nur lesen). Zwei Ansichten: Board wie intern (nx-Kanban: getönte
    Slots in voller Höhe, weiße Karten mit Farbband, Erledigt als Streifen rechts) und Liste
    (nach Spalten gruppiert, mobil Standard).
    Klick auf eine Aufgabe öffnet das Detail-Blatt rechts. Daten: $columns (PlannerShareView::boardColumns).
--}}
<div x-data="{
        task: null,
        view: (() => { try { return localStorage.getItem('gate-board-view') } catch (e) { return null } })() || (window.innerWidth < 768 ? 'list' : 'board'),
        showDone: (() => { try { return localStorage.getItem('gate-board-done') === '1' } catch (e) { return false } })(),
        setView(v) { this.view = v; try { localStorage.setItem('gate-board-view', v) } catch (e) {} this.$nextTick(() => this.fit()) },
        setDone(v) {
            this.showDone = v; try { localStorage.setItem('gate-board-done', v ? '1' : '0') } catch (e) {}
            if (v) this.$nextTick(() => this.$refs.board.scrollTo({ left: this.$refs.board.scrollWidth, behavior: 'smooth' }));
        },
        ro: null,
        destroy() { this.ro && this.ro.disconnect() },
        fit() {
            const b = this.$refs.board; if (! b || ! b.offsetParent) return;
            b.style.setProperty('--gp-board-h', Math.max(420, window.innerHeight - (b.getBoundingClientRect().top + window.scrollY)) + 'px');
        },
     }"
     x-init="ro = new ResizeObserver(() => fit()); ro.observe($refs.board); ro.observe(document.body); $watch('task', v => document.documentElement.classList.toggle('gp-lock', !! v))"
     @resize.window.debounce.100ms="fit()"
     @keydown.escape.window="task = null">

    {{-- Ansicht umschalten --}}
    <div class="gp-wrap flex items-center justify-between gap-3 pt-5">
        <span class="text-xs text-[color:var(--nx-faint)]">Klicken Sie auf eine Aufgabe für Details.</span>
        <div class="inline-flex rounded-[8px] bg-[color:var(--nx-bg)] p-0.5 text-xs">
            @foreach (['board' => ['Board', 'heroicon-o-view-columns'], 'list' => ['Liste', 'heroicon-o-list-bullet']] as $v => [$vLabel, $vIcon])
                <button type="button" @click="setView('{{ $v }}')"
                        class="inline-flex items-center gap-1.5 rounded-[6px] px-2.5 py-1 transition-colors"
                        :class="view === '{{ $v }}' ? 'bg-[color:var(--nx-surface)] font-medium text-[color:var(--nx-text)] shadow-[var(--nx-shadow-card)]' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]'">
                    @svg($vIcon, 'w-3.5 h-3.5') {{ $vLabel }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Board — Aufbau wie im Planner (x-nx-kanban-*): Spalten in voller Höhe, jede scrollt für sich --}}
    @php
        $doneCol = collect($columns)->firstWhere('key', 'done');
        $boardCols = collect($columns)->reject(fn ($c) => $c['key'] === 'done');
    @endphp
    <div x-show="view === 'board'" x-ref="board" class="gp-board gp-bleed">
        @foreach ($boardCols as $col)
            @include('planner::share._board-column', ['col' => $col])
        @endforeach

        @if ($doneCol)
            <div class="contents" x-show="showDone" style="display: none">@include('planner::share._board-column', ['col' => $doneCol, 'collapsible' => true])</div>
            <button type="button" x-show="! showDone" class="gp-col-strip" @click="setDone(true)" title="Erledigte anzeigen ({{ $doneCol['total'] ?? count($doneCol['tasks']) }})">
                @svg('heroicon-o-chevron-double-left', 'w-4 h-4 mt-1')
                <span class="gp-vert">Erledigt</span>
                <span class="inline-flex h-5 min-w-[1.5rem] items-center justify-center rounded-full bg-[rgba(47,158,68,0.16)] px-1 text-[10px] font-semibold tabular-nums">{{ $doneCol['total'] ?? count($doneCol['tasks']) }}</span>
            </button>
        @endif
    </div>

    {{-- Liste --}}
    <div x-show="view === 'list'" style="display: none" class="gp-wrap space-y-6 pb-8 pt-4">
        @foreach ($columns as $col)
            @if (count($col['tasks']))
                <section wire:key="lcol-{{ $col['key'] }}">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full" style="background-color: var(--nx-tone-{{ $col['tone'] }})"></span>
                        <span class="text-sm font-medium">{{ $col['title'] }}</span>
                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $col['total'] ?? count($col['tasks']) }}</span>
                    </div>
                    <div class="overflow-hidden rounded-[12px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                        @foreach ($col['tasks'] as $t)
                            <button type="button" @click="task = @js($t)" wire:key="row-{{ $t['id'] }}"
                                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-[color:var(--nx-hover)]">
                                @if ($t['done'])
                                    @svg('heroicon-s-check-circle', 'w-4 h-4 shrink-0 text-[color:var(--nx-success)]')
                                @else
                                    <span class="h-4 w-4 shrink-0 rounded-full border-[1.5px] border-[color:var(--nx-line-strong)]"></span>
                                @endif
                                <span @class(['min-w-0 flex-1 truncate text-sm', 'text-[color:var(--nx-muted)] line-through' => $t['done']])>{{ $t['title'] }}</span>
                                @if ($t['person'])
                                    <span class="hidden shrink-0 text-xs text-[color:var(--nx-faint)] md:inline">{{ $t['person'] }}</span>
                                @endif
                                @if ($t['dod'])
                                    <span class="hidden shrink-0 text-[11px] tabular-nums text-[color:var(--nx-faint)] sm:inline">{{ $t['dod']['checked'] }}/{{ $t['dod']['total'] }}</span>
                                @endif
                                @if ($t['due_phrase'] && ! $t['done'])
                                    <span @class(['shrink-0 text-xs', 'font-medium text-[color:var(--nx-danger)]' => $t['overdue'], 'text-[color:var(--nx-faint)]' => ! $t['overdue']])>{{ $t['due_phrase'] }}</span>
                                @elseif ($t['done_at'])
                                    <span class="shrink-0 text-xs text-[color:var(--nx-faint)]">{{ $t['done_at'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    {{-- Detail-Blatt einer Aufgabe --}}
    <div x-show="task" x-transition.opacity class="fixed inset-0 z-50 bg-black/20" @click="task = null" style="display: none"></div>
    <aside x-show="task" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-100" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-[var(--nx-shadow-pop)]" style="display: none">
        <template x-if="task">
            <div class="flex min-h-0 flex-1 flex-col">
                <div class="flex h-12 shrink-0 items-center justify-between border-b border-[color:var(--nx-line)] px-4">
                    <span class="inline-flex min-w-0 items-center gap-1.5 truncate text-xs text-[color:var(--nx-muted)]">
                        @svg('heroicon-o-view-columns', 'w-3.5 h-3.5 shrink-0') <span class="truncate" x-text="task.column"></span>
                    </span>
                    <button type="button" class="-mr-2 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" @click="task = null" title="Schließen (Esc)">
                        @svg('heroicon-o-x-mark', 'w-5 h-5')
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-5">
                    <h3 class="text-lg font-semibold leading-snug" :class="task.done && 'line-through text-[color:var(--nx-muted)]'" x-text="task.title"></h3>

                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex items-center gap-3">
                            <dt class="w-24 shrink-0 text-[color:var(--nx-faint)]">Status</dt>
                            <dd>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="task.done ? 'text-[color:var(--nx-success)] bg-[rgba(47,158,68,.12)]' : 'text-[color:var(--nx-info)] bg-[rgba(25,113,194,.12)]'">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    <span x-text="task.done ? 'Erledigt' : 'Offen'"></span>
                                </span>
                            </dd>
                        </div>
                        <div class="flex gap-3" x-show="task.person">
                            <dt class="w-24 shrink-0 text-[color:var(--nx-faint)]">Zuständig</dt>
                            <dd x-text="task.person"></dd>
                        </div>
                        <div class="flex gap-3" x-show="task.priority">
                            <dt class="w-24 shrink-0 text-[color:var(--nx-faint)]">Priorität</dt>
                            <dd x-text="task.priority"></dd>
                        </div>
                        <div class="flex gap-3" x-show="task.tags && task.tags.length">
                            <dt class="w-24 shrink-0 text-[color:var(--nx-faint)]">Schlagworte</dt>
                            <dd x-text="(task.tags || []).join(', ')"></dd>
                        </div>
                        <div class="flex gap-3" x-show="task.done_at">
                            <dt class="w-24 shrink-0 text-[color:var(--nx-faint)]">Erledigt am</dt>
                            <dd x-text="task.done_at"></dd>
                        </div>
                        <div class="flex gap-3" x-show="task.due && ! task.done">
                            <dt class="w-24 shrink-0 text-[color:var(--nx-faint)]">Fällig</dt>
                            <dd :class="task.overdue && 'font-medium text-[color:var(--nx-danger)]'"><span x-text="task.due"></span> <span class="text-[color:var(--nx-faint)]" x-text="'· ' + task.due_phrase"></span></dd>
                        </div>
                    </dl>

                    <template x-if="task.description">
                        <div class="mt-6 border-t border-[color:var(--nx-line)] pt-5">
                            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Beschreibung</div>
                            <div class="whitespace-pre-line text-sm leading-relaxed" x-text="task.description"></div>
                        </div>
                    </template>

                    <template x-if="task.dod_items && task.dod_items.length">
                        <div class="mt-6 border-t border-[color:var(--nx-line)] pt-5">
                            <div class="mb-2 flex items-center justify-between text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">
                                <span>Erledigt, wenn …</span>
                                <span class="normal-case tabular-nums" x-text="task.dod.checked + ' / ' + task.dod.total"></span>
                            </div>
                            <div class="mb-3 h-1 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                                <div class="h-full rounded-full bg-[color:var(--nx-success)]" :style="'width:' + task.dod.pct + '%'"></div>
                            </div>
                            <ul class="space-y-2">
                                <template x-for="(item, i) in task.dod_items" :key="i">
                                    <li class="flex items-start gap-2.5 text-sm">
                                        <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] border"
                                              :class="item.checked ? 'border-[color:var(--nx-success)] bg-[color:var(--nx-success)] text-white' : 'border-[color:var(--nx-line-strong)]'">
                                            <svg x-show="item.checked" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                        </span>
                                        <span :class="item.checked && 'text-[color:var(--nx-muted)] line-through'" x-text="item.text"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <template x-if="! task.description && ! (task.dod_items && task.dod_items.length)">
                        <p class="mt-6 border-t border-[color:var(--nx-line)] pt-5 text-sm text-[color:var(--nx-faint)]">Keine weiteren Details hinterlegt.</p>
                    </template>
                </div>
            </div>
        </template>
    </aside>
</div>
