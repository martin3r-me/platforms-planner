{{--
    Kunden-Tor · Projekt-Board (nur lesen). Optik wie das interne Board (nx-Kanban: getönte
    Slots, weiße Karten mit Farbband); Klick auf eine Karte öffnet das Detail-Blatt rechts.
    Daten: $columns aus PlannerShareView::boardColumns().
--}}
<div x-data="{ task: null }" @keydown.escape.window="task = null">
    <div class="flex gap-4 overflow-x-auto px-4 py-5 sm:px-8" style="scroll-padding: 2rem">
        @foreach ($columns as $col)
            @php
                $slotBg = "color-mix(in srgb, var(--nx-tone-{$col['tone']}) 6%, #ffffff)";
                $pillBg = "color-mix(in srgb, var(--nx-tone-{$col['tone']}) 16%, #ffffff)";
            @endphp
            <div class="flex shrink-0 flex-col rounded-[10px] p-1.5" style="width: 17.5rem; min-width: 17.5rem; background-color: {{ $slotBg }}" wire:key="col-{{ $col['key'] }}">
                <div @class(['flex items-center gap-1.5 px-1.5 py-1.5', 'opacity-80' => $col['muted']])>
                    <span class="inline-flex min-w-0 items-center gap-1.5 rounded-[6px] px-2 py-0.5" style="background-color: {{ $pillBg }}">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: var(--nx-tone-{{ $col['tone'] }})"></span>
                        <span class="truncate text-xs font-medium">{{ $col['title'] }}</span>
                    </span>
                    <span class="text-xs text-[color:var(--nx-faint)]">{{ $col['total'] ?? count($col['tasks']) }}</span>
                </div>

                <div class="space-y-2 px-0.5 pb-1 pt-1">
                    @forelse ($col['tasks'] as $t)
                        @php
                            $edge = match (true) {
                                $t['overdue'] => 'var(--nx-danger)',
                                $t['done'] => 'var(--nx-success)',
                                default => 'var(--nx-line-strong)',
                            };
                        @endphp
                        <button type="button" @click="task = @js($t)" wire:key="card-{{ $t['id'] }}"
                                @class(['relative block w-full rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] py-2.5 pl-5 pr-3 text-left shadow-[var(--nx-shadow-card)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,15,15,.08),0_1px_2px_rgba(15,15,15,.05)]', 'opacity-60' => $t['done']])>
                            <span class="absolute bottom-2.5 left-1.5 top-2.5 w-[3px] rounded-full" style="background-color: {{ $edge }}"></span>
                            <span @class(['block text-[13px] font-semibold leading-snug', 'line-through text-[color:var(--nx-muted)]' => $t['done']])>{{ $t['title'] }}</span>
                            @if ($t['due_phrase'] || $t['dod'] || $t['description'])
                                <span class="mt-2 flex items-center gap-2 text-[10px] leading-none text-[color:var(--nx-muted)]">
                                    @if ($t['due_phrase'] && ! $t['done'])
                                        <span @class(['inline-flex items-center gap-0.5', 'font-medium text-[color:var(--nx-danger)]' => $t['overdue']])>
                                            @svg('heroicon-o-clock', 'w-3 h-3 opacity-60') {{ $t['due_phrase'] }}
                                        </span>
                                    @endif
                                    @if ($t['dod'])
                                        <span class="inline-flex items-center gap-1">
                                            <span class="inline-block h-1 w-6 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                                                <span class="block h-full rounded-full {{ $t['dod']['pct'] >= 100 ? 'bg-[color:var(--nx-success)]' : 'bg-[color:var(--nx-accent)]' }}" style="width: {{ $t['dod']['pct'] }}%"></span>
                                            </span>
                                            <span class="tabular-nums">{{ $t['dod']['checked'] }}/{{ $t['dod']['total'] }}</span>
                                        </span>
                                    @endif
                                    <span class="flex-1"></span>
                                    @if ($t['description'])
                                        @svg('heroicon-o-bars-3-bottom-left', 'w-3 h-3 opacity-60')
                                    @endif
                                </span>
                            @endif
                        </button>
                    @empty
                        <div class="px-2 py-4 text-center text-[11px] italic text-[color:var(--nx-faint)]">Leer</div>
                    @endforelse
                    @if (($col['total'] ?? 0) > count($col['tasks']))
                        <div class="px-2 py-1 text-center text-[11px] text-[color:var(--nx-faint)]">+ {{ $col['total'] - count($col['tasks']) }} ältere im Verlauf</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Detail-Blatt einer Aufgabe --}}
    <div x-show="task" x-transition.opacity class="fixed inset-0 z-50 bg-black/20" @click="task = null" style="display: none"></div>
    <aside x-show="task" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-100" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
               class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-[var(--nx-shadow-pop)]" style="display: none">
            <template x-if="task">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex h-11 shrink-0 items-center justify-between border-b border-[color:var(--nx-line)] px-4">
                        <span class="truncate text-xs text-[color:var(--nx-muted)]" x-text="task.column"></span>
                        <button type="button" class="-mr-2 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" @click="task = null" title="Schließen">
                            @svg('heroicon-o-x-mark', 'w-5 h-5')
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-5 py-5">
                        <h3 class="text-lg font-semibold leading-snug" :class="task.done && 'line-through text-[color:var(--nx-muted)]'" x-text="task.title"></h3>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex gap-3">
                                <dt class="w-24 shrink-0 text-[color:var(--nx-faint)]">Status</dt>
                                <dd>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                          :class="task.done ? 'text-[color:var(--nx-success)] bg-[rgba(47,158,68,.12)]' : 'text-[color:var(--nx-info)] bg-[rgba(25,113,194,.12)]'">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                        <span x-text="task.done ? 'Erledigt' : 'Offen'"></span>
                                    </span>
                                </dd>
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
                            <div class="mt-6">
                                <div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Beschreibung</div>
                                <div class="whitespace-pre-line text-sm leading-relaxed" x-text="task.description"></div>
                            </div>
                        </template>

                        <template x-if="task.dod_items && task.dod_items.length">
                            <div class="mt-6">
                                <div class="mb-1.5 flex items-center justify-between text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">
                                    <span>Erledigt, wenn …</span>
                                    <span class="normal-case tabular-nums" x-text="task.dod.checked + ' / ' + task.dod.total"></span>
                                </div>
                                <ul class="space-y-1.5">
                                    <template x-for="(item, i) in task.dod_items" :key="i">
                                        <li class="flex items-start gap-2 text-sm">
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
                    </div>
                </div>
            </template>
    </aside>
</div>
