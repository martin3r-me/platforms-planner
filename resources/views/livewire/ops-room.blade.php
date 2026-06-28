@php
    $colorTones = [
        'red' => ['fg' => 'text-rose-400', 'glow' => 'ops-glow-red', 'bg' => 'bg-rose-500/10', 'dot' => 'bg-rose-500', 'label' => 'BRENNT'],
        'yellow' => ['fg' => 'text-amber-300', 'glow' => 'ops-glow-yellow', 'bg' => 'bg-amber-500/10', 'dot' => 'bg-amber-400', 'label' => 'ACHTUNG'],
        'green' => ['fg' => 'text-emerald-400', 'glow' => 'ops-glow-green', 'bg' => 'bg-emerald-500/10', 'dot' => 'bg-emerald-500', 'label' => 'STABIL'],
        'gray' => ['fg' => 'text-zinc-400', 'glow' => 'ops-glow-gray', 'bg' => 'bg-zinc-500/10', 'dot' => 'bg-zinc-500', 'label' => 'KEINE DATEN'],
    ];
    $tone = fn ($c) => $colorTones[$c ?: 'gray'] ?? $colorTones['gray'];
    $axisLabel = [
        'strategy' => 'Strategie',
        'progress' => 'Fortschritt',
        'burn' => 'Druck',
    ];
@endphp

<div
    wire:poll.60s
    x-data="{
        toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(()=>{});
            } else {
                document.exitFullscreen().catch(()=>{});
            }
        }
    }"
    @keydown.window.f.prevent="toggleFullscreen()"
    @keydown.window.escape="if(document.fullscreenElement) document.exitFullscreen()"
    class="h-full w-full flex flex-col text-zinc-100"
>

    {{-- ═══════════ TOPBAR ═══════════ --}}
    <header class="flex items-center justify-between px-8 py-4 border-b border-zinc-800/60 backdrop-blur">
        <div class="flex items-center gap-4">
            <div class="inline-flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-500/10 border border-rose-500/30">
                    @svg('heroicon-o-heart', 'w-5 h-5 text-rose-400')
                </span>
                <div>
                    <h1 class="text-xl font-bold tracking-[0.2em] text-zinc-100 m-0 leading-none">OPS-ROOM</h1>
                    <p class="text-[10px] uppercase tracking-[0.3em] text-zinc-500 m-0 mt-1">{{ $team->name ?? 'Team' }} · Planner</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="text-right">
                <div class="text-3xl font-bold tabular-nums text-zinc-100 leading-none"
                     x-data="{ now: new Date() }"
                     x-init="setInterval(() => now = new Date(), 1000)"
                     x-text="now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' })">
                </div>
                <div class="text-[10px] uppercase tracking-[0.3em] text-zinc-500 mt-1 m-0"
                     x-data x-init="$el.textContent = new Date().toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })"></div>
            </div>
            <div class="h-10 border-l border-zinc-800"></div>
            <div class="text-right">
                <div class="text-[10px] uppercase tracking-[0.3em] text-zinc-500">Snapshot</div>
                <div class="text-sm text-zinc-300 tabular-nums mt-0.5">{{ $snapshotStand?->format('d.m. · H:i') ?? '—' }}</div>
            </div>
            <button type="button" @click="toggleFullscreen()"
                    title="Fullscreen (F)"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-500 transition-colors">
                @svg('heroicon-o-arrows-pointing-out', 'w-4 h-4')
            </button>
        </div>
    </header>

    {{-- ═══════════ AMPEL-BAND (Zone 1) ═══════════ --}}
    <section class="px-8 py-5 border-b border-zinc-800/60">
        <div class="grid grid-cols-4 gap-3">
            @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                @php $t = $tone($c); $count = $byColor[$c] ?? 0; @endphp
                <div class="rounded-xl p-4 {{ $t['bg'] }} {{ $count > 0 ? $t['glow'] : '' }} flex items-center gap-4">
                    <span class="w-3 h-3 rounded-full {{ $t['dot'] }} {{ $c === 'red' && $count > 0 ? 'ops-pulse-dot' : '' }} flex-shrink-0"></span>
                    <div class="flex-1">
                        <div class="text-[9px] uppercase tracking-[0.25em] {{ $count > 0 ? $t['fg'] : 'text-zinc-600' }} opacity-80">{{ $t['label'] }}</div>
                        <div class="text-5xl font-bold tabular-nums {{ $count > 0 ? $t['fg'] : 'text-zinc-700' }} leading-none mt-1">{{ $count }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Distribution-Bar --}}
        <div class="mt-4 h-2 w-full bg-zinc-900 rounded-full overflow-hidden flex">
            @php $distTotal = max(1, array_sum($byColor)); @endphp
            @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                @php $w = ($byColor[$c] / $distTotal) * 100; $t = $tone($c); @endphp
                @if($byColor[$c] > 0)
                    <div class="h-full {{ str_replace('text-', 'bg-', $t['fg']) }}" style="width: {{ $w }}%" title="{{ $t['label'] }}: {{ $byColor[$c] }}"></div>
                @endif
            @endforeach
        </div>
        <div class="mt-2 text-[10px] uppercase tracking-[0.25em] text-zinc-600 text-center">
            {{ $totalProjects }} Projekte im Scope · {{ $team->name ?? '—' }}
        </div>
    </section>

    {{-- ═══════════ HAUPT-3-SPALTEN ═══════════ --}}
    <section class="flex-1 min-h-0 grid grid-cols-3 gap-px bg-zinc-800/60">

        {{-- ── BRENNT JETZT (links) ── --}}
        <div class="bg-black/40 overflow-y-auto p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-xs uppercase tracking-[0.3em] text-rose-400 m-0">Brennt jetzt</h2>
                <span class="text-[10px] uppercase tracking-wider text-zinc-600">aus Snapshot</span>
            </div>
            @if($brennt->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-emerald-500/70">
                    @svg('heroicon-o-check-circle', 'w-12 h-12 mb-2')
                    <p class="text-sm m-0">Keine roten Projekte.</p>
                </div>
            @else
                <ul class="space-y-3">
                    @foreach($brennt as $s)
                        @php $wl = $axisLabel[$s->worst_axis] ?? null; @endphp
                        <li class="rounded-lg bg-rose-500/5 border border-rose-500/30 p-3 ops-glow-red">
                            <div class="flex items-start gap-3">
                                <span class="text-3xl font-bold tabular-nums text-rose-400 leading-none flex-shrink-0 w-12 text-right">{{ $s->health_score ?? '–' }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-zinc-100 truncate">{{ $s->project?->name }}</div>
                                    <div class="flex items-center gap-3 mt-1 text-[11px]">
                                        @if($wl)
                                            <span class="text-rose-300">⚠ {{ $wl }}</span>
                                        @endif
                                        @if($s->tasks_overdue > 0)
                                            <span class="text-zinc-400">{{ $s->tasks_overdue }} überfällig</span>
                                        @endif
                                        @if($s->tasks_frog > 0)
                                            <span class="text-zinc-400">{{ $s->tasks_frog }} Frog{{ $s->tasks_frog === 1 ? '' : 's' }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ── HEUTE LIVE (mitte) ── --}}
        <div class="bg-black/40 overflow-y-auto p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-xs uppercase tracking-[0.3em] text-indigo-300 m-0">Heute · Live</h2>
                <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-zinc-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 ops-pulse-dot"></span>
                    <span>refresh 60s</span>
                </span>
            </div>

            {{-- Live-Counts --}}
            <div class="grid grid-cols-2 gap-3 mb-5">
                <div class="rounded-lg bg-emerald-500/5 border border-emerald-500/20 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-emerald-400 mb-1">erledigt</div>
                    <div class="text-3xl font-bold tabular-nums text-emerald-300 leading-none">{{ $tasksDoneToday }}</div>
                </div>
                <div class="rounded-lg bg-rose-500/5 border border-rose-500/20 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-rose-400 mb-1">neu überfällig</div>
                    <div class="text-3xl font-bold tabular-nums text-rose-300 leading-none">{{ $tasksNewOverdueToday }}</div>
                    @if($tasksOverdueAll > 0)
                        <div class="text-[10px] text-zinc-500 mt-1 tabular-nums">{{ $tasksOverdueAll }} gesamt</div>
                    @endif
                </div>
                <div class="rounded-lg bg-amber-500/5 border border-amber-500/20 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-amber-300 mb-1">neue Frögge</div>
                    <div class="text-3xl font-bold tabular-nums text-amber-200 leading-none">{{ $newFrogsToday }}</div>
                </div>
                <div class="rounded-lg bg-zinc-800/40 border border-zinc-700/40 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-zinc-400 mb-1">geloggt</div>
                    <div class="text-3xl font-bold tabular-nums text-zinc-200 leading-none">{{ round($minutesLoggedToday / 60, 1) }}<span class="text-base text-zinc-500 font-normal">h</span></div>
                </div>
            </div>

            {{-- Workload Top-3 --}}
            @if($workload->isNotEmpty())
                <div>
                    <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500 mb-2">Workload Top 3</div>
                    <ul class="space-y-2">
                        @php $maxOpen = max(1, $workload->max('open')); @endphp
                        @foreach($workload as $w)
                            <li class="py-1">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span class="text-zinc-200 truncate">{{ $w->name }}</span>
                                    <span class="text-[11px] tabular-nums text-zinc-400">
                                        <span class="font-semibold text-zinc-200">{{ $w->open }}</span> offen
                                        @if($w->overdue > 0)
                                            <span class="text-rose-400 ml-1">{{ $w->overdue }}↓</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="h-1 w-full bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500" style="width: {{ round(($w->open / $maxOpen) * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- ── KARTEILEICHEN (rechts) ── --}}
        <div class="bg-black/40 overflow-y-auto p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-xs uppercase tracking-[0.3em] text-zinc-400 m-0">Karteileichen</h2>
                <span class="text-[10px] uppercase tracking-wider text-zinc-600">Confidence ≤ 25%</span>
            </div>
            @if($karteileichen->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-emerald-500/70">
                    @svg('heroicon-o-check-circle', 'w-12 h-12 mb-2')
                    <p class="text-sm m-0">Alle Projekte sind gepflegt.</p>
                </div>
            @else
                <ul class="space-y-2">
                    @foreach($karteileichen as $s)
                        @php
                            $missing = $s->confidence_reason && str_starts_with($s->confidence_reason, 'missing:')
                                ? array_map('trim', explode(',', substr($s->confidence_reason, strlen('missing:'))))
                                : [];
                        @endphp
                        <li class="rounded-lg bg-zinc-800/30 border border-zinc-700/40 p-3">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded bg-zinc-900 text-zinc-500 text-xs font-bold tabular-nums flex-shrink-0">
                                    {{ $s->confidence_score }}<span class="text-[8px] opacity-60">%</span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm text-zinc-200 truncate">{{ $s->project?->name }}</div>
                                    <div class="text-[10px] text-zinc-500 mt-0.5 truncate">
                                        fehlt:
                                        @foreach($missing as $m)
                                            <span class="text-rose-400/80">{{ str_replace('_', ' ', $m) }}@if(!$loop->last), @endif</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- ═══════════ BEWEGUNGS-TICKER (unten) ═══════════ --}}
    @if($recentDone->isNotEmpty())
        <footer class="border-t border-zinc-800/60 bg-black/60 px-8 py-3 flex items-center gap-6 overflow-hidden">
            <span class="text-[10px] uppercase tracking-[0.3em] text-emerald-400 flex-shrink-0 inline-flex items-center gap-1.5">
                @svg('heroicon-o-check-circle', 'w-3 h-3')
                Erledigt zuletzt
            </span>
            <ul class="flex items-center gap-6 text-xs text-zinc-400 overflow-hidden flex-nowrap">
                @foreach($recentDone as $task)
                    <li class="inline-flex items-center gap-2 flex-shrink-0">
                        <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                        <span class="text-zinc-200 truncate max-w-xs">{{ $task->title }}</span>
                        @if($task->project)
                            <span class="text-zinc-600 truncate max-w-[12rem]">· {{ $task->project->name }}</span>
                        @endif
                        <span class="text-zinc-600 tabular-nums">· {{ $task->done_at?->format('H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </footer>
    @endif
</div>
