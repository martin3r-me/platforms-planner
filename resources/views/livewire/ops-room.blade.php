@php
    $colorTones = [
        'red' => ['fg' => 'text-[color:var(--nx-danger)]', 'glow' => 'ops-glow-red', 'bg' => 'bg-[var(--nx-danger)]/10', 'dot' => 'bg-[color:var(--nx-danger)]', 'fill' => 'bg-[color:var(--nx-danger)]', 'label' => 'BRENNT'],
        'yellow' => ['fg' => 'text-[color:var(--nx-warning)]', 'glow' => 'ops-glow-yellow', 'bg' => 'bg-[var(--nx-warning)]/10', 'dot' => 'bg-[color:var(--nx-warning)]', 'fill' => 'bg-[color:var(--nx-warning)]', 'label' => 'ACHTUNG'],
        'green' => ['fg' => 'text-[color:var(--nx-success)]', 'glow' => 'ops-glow-green', 'bg' => 'bg-[var(--nx-success)]/10', 'dot' => 'bg-[color:var(--nx-success)]', 'fill' => 'bg-[color:var(--nx-success)]', 'label' => 'STABIL'],
        'gray' => ['fg' => 'text-[color:var(--nx-muted)]', 'glow' => 'ops-glow-gray', 'bg' => 'bg-[color:var(--nx-bg)]', 'dot' => 'bg-[color:var(--nx-muted)]', 'fill' => 'bg-[color:var(--nx-muted)]', 'label' => 'KEINE DATEN'],
    ];
    $tone = fn ($c) => $colorTones[$c ?: 'gray'] ?? $colorTones['gray'];
    $axisLabel = [
        'strategy' => 'Strategie',
        'progress' => 'Fortschritt',
        'burn' => 'Druck',
    ];
    $axisIcon = [
        'strategy' => 'heroicon-o-squares-2x2',
        'progress' => 'heroicon-o-arrow-trending-up',
        'burn' => 'heroicon-o-fire',
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
    class="h-full w-full flex flex-col text-[color:var(--nx-text)]"
>

    {{-- ═══════════ TOPBAR ═══════════ --}}
    <header class="flex items-center justify-between px-8 py-3 border-b border-[color:var(--nx-line)] flex-shrink-0">
        <div class="inline-flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--nx-danger)]/10 border border-[var(--nx-danger)]/30">
                @svg('heroicon-o-heart', 'w-5 h-5 text-[color:var(--nx-danger)]')
            </span>
            <div>
                <h1 class="text-xl font-bold tracking-[0.2em] text-[color:var(--nx-text)] m-0 leading-none">PORTFOLIO-HEALTH</h1>
                <p class="text-[10px] uppercase tracking-[0.3em] text-[color:var(--nx-muted)] m-0 mt-1">Wand · {{ $team->name ?? 'Team' }}</p>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="text-right">
                <div class="text-3xl font-bold tabular-nums text-[color:var(--nx-text)] leading-none"
                     x-data="{ now: new Date() }"
                     x-init="setInterval(() => now = new Date(), 1000)"
                     x-text="now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' })">
                </div>
                <div class="text-[10px] uppercase tracking-[0.3em] text-[color:var(--nx-muted)] mt-1 m-0"
                     x-data x-init="$el.textContent = new Date().toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })"></div>
            </div>
            <div class="h-10 border-l border-[color:var(--nx-line)]"></div>
            <div class="text-right">
                <div class="text-[10px] uppercase tracking-[0.3em] text-[color:var(--nx-muted)]">Snapshot</div>
                <div class="text-sm text-[color:var(--nx-text)] tabular-nums mt-0.5">{{ $snapshotStand?->format('d.m. · H:i') ?? '—' }}</div>
            </div>
            {{-- Umschalter: zurück zur Analyse-Ansicht --}}
            <a href="{{ route('planner.health-index') }}" wire:navigate
               title="Zur Analyse-Ansicht"
               class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md border border-[color:var(--nx-line-strong)] text-[11px] font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition-colors">
                @svg('heroicon-o-table-cells', 'w-4 h-4')
                <span>Analyse</span>
            </a>
            <button type="button" @click="toggleFullscreen()"
                    title="Fullscreen (F)"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-[color:var(--nx-line-strong)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:border-[color:var(--nx-line-strong)] transition-colors">
                @svg('heroicon-o-arrows-pointing-out', 'w-4 h-4')
            </button>
        </div>
    </header>

    {{-- ═══════════ AMPEL-BAND (Zone 1) ═══════════ --}}
    <section class="px-8 py-4 border-b border-[color:var(--nx-line)] flex-shrink-0">
        <div class="grid grid-cols-4 gap-3">
            @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                @php $t = $tone($c); $count = $byColor[$c] ?? 0; @endphp
                <div class="rounded-xl p-4 {{ $t['bg'] }} {{ $count > 0 ? $t['glow'] : '' }} flex items-center gap-4">
                    <span class="w-3 h-3 rounded-full {{ $t['dot'] }} {{ $c === 'red' && $count > 0 ? 'ops-pulse-dot' : '' }} flex-shrink-0"></span>
                    <div class="flex-1">
                        <div class="text-[9px] uppercase tracking-[0.25em] {{ $count > 0 ? $t['fg'] : 'text-[color:var(--nx-faint)]' }} opacity-80">{{ $t['label'] }}</div>
                        <div class="text-5xl font-bold tabular-nums {{ $count > 0 ? $t['fg'] : 'text-[color:var(--nx-faint)]' }} leading-none mt-1">{{ $count }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Distribution-Bar --}}
        <div class="mt-3 h-2 w-full bg-[color:var(--nx-line)] rounded-full overflow-hidden flex">
            @php $distTotal = max(1, array_sum($byColor)); @endphp
            @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                @php $w = ($byColor[$c] / $distTotal) * 100; $t = $tone($c); @endphp
                @if($byColor[$c] > 0)
                    <div class="h-full {{ $t['fill'] }}" style="width: {{ $w }}%" title="{{ $t['label'] }}: {{ $byColor[$c] }}"></div>
                @endif
            @endforeach
        </div>
        <div class="mt-1 text-[10px] uppercase tracking-[0.25em] text-[color:var(--nx-faint)] text-center">
            {{ $totalProjects }} Projekte im Scope
        </div>
    </section>

    {{-- ═══════════ HAUPT-2-REIHEN × 3-SPALTEN ═══════════ --}}
    <section class="flex-1 min-h-0 grid grid-cols-3 grid-rows-2 gap-px bg-[color:var(--nx-line)]">

        {{-- ── (1,1) BRENNT JETZT ── --}}
        <div class="bg-[color:var(--nx-surface)] p-5 flex flex-col min-h-0">
            <div class="flex items-baseline justify-between mb-3 flex-shrink-0">
                <h2 class="text-xs uppercase tracking-[0.3em] text-[color:var(--nx-danger)] m-0 inline-flex items-center gap-2">
                    @svg('heroicon-o-fire', 'w-4 h-4')
                    <span>Brennt jetzt</span>
                </h2>
                <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-faint)] tabular-nums">{{ $brennt->count() }} rot</span>
            </div>
            @if($brennt->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center text-[color:var(--nx-success)]">
                    @svg('heroicon-o-check-circle', 'w-14 h-14 mb-2')
                    <p class="text-sm m-0">Keine roten Projekte.</p>
                </div>
            @else
                <ul class="flex-1 space-y-2 min-h-0 overflow-y-auto">
                    @foreach($brennt as $s)
                        @php $wl = $axisLabel[$s->worst_axis] ?? null; @endphp
                        <li class="rounded-lg bg-[var(--nx-danger)]/5 border border-[var(--nx-danger)]/30 p-2.5 ops-glow-red">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl font-bold tabular-nums text-[color:var(--nx-danger)] leading-none flex-shrink-0 w-11 text-right">{{ $s->health_score ?? '–' }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[13px] font-semibold text-[color:var(--nx-text)] truncate">{{ $s->project?->name }}</div>
                                    <div class="flex items-center gap-2 mt-0.5 text-[10px] tabular-nums">
                                        @if($wl)
                                            <span class="text-[color:var(--nx-danger)]">⚠ {{ $wl }}</span>
                                        @endif
                                        @if($s->tasks_overdue > 0)
                                            <span class="text-[color:var(--nx-muted)]">·</span>
                                            <span class="text-[color:var(--nx-muted)]">{{ $s->tasks_overdue }} überfällig</span>
                                        @endif
                                        @if($s->tasks_frog > 0)
                                            <span class="text-[color:var(--nx-muted)]">·</span>
                                            <span class="text-[color:var(--nx-muted)]">{{ $s->tasks_frog }} Frog{{ $s->tasks_frog === 1 ? '' : 's' }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if($s->delta_health_score !== null && $s->delta_health_score !== 0)
                                    @php $dc = $s->delta_health_score > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]'; $da = $s->delta_health_score > 0 ? '↑' : '↓'; @endphp
                                    <span class="text-[10px] tabular-nums {{ $dc }} flex-shrink-0">{{ $da }}{{ abs($s->delta_health_score) }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ── (1,2) HEUTE LIVE ── --}}
        <div class="bg-[color:var(--nx-surface)] p-5 flex flex-col min-h-0">
            <div class="flex items-baseline justify-between mb-3 flex-shrink-0">
                <h2 class="text-xs uppercase tracking-[0.3em] text-[color:var(--nx-accent)] m-0 inline-flex items-center gap-2">
                    @svg('heroicon-o-bolt', 'w-4 h-4')
                    <span>Heute · Live</span>
                </h2>
                <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-[color:var(--nx-muted)]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-accent)] ops-pulse-dot"></span>
                    <span>60s</span>
                </span>
            </div>

            {{-- 4 Live-Counts --}}
            <div class="grid grid-cols-2 gap-2 flex-shrink-0">
                <div class="rounded-lg bg-[var(--nx-success)]/5 border border-[var(--nx-success)]/20 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-[color:var(--nx-success)]">erledigt</div>
                    <div class="text-3xl font-bold tabular-nums text-[color:var(--nx-success)] leading-none mt-1">{{ $tasksDoneToday }}</div>
                </div>
                <div class="rounded-lg bg-[var(--nx-danger)]/5 border border-[var(--nx-danger)]/20 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-[color:var(--nx-danger)]">neu überfällig</div>
                    <div class="text-3xl font-bold tabular-nums text-[color:var(--nx-danger)] leading-none mt-1">{{ $tasksNewOverdueToday }}</div>
                    @if($tasksOverdueAll > 0)
                        <div class="text-[10px] text-[color:var(--nx-muted)] mt-0.5 tabular-nums">{{ $tasksOverdueAll }} gesamt</div>
                    @endif
                </div>
                <div class="rounded-lg bg-[var(--nx-warning)]/5 border border-[var(--nx-warning)]/20 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-[color:var(--nx-warning)]">neue Frösche</div>
                    <div class="text-3xl font-bold tabular-nums text-[color:var(--nx-warning)] leading-none mt-1">{{ $newFrogsToday }}</div>
                </div>
                <div class="rounded-lg bg-[color:var(--nx-bg)] border border-[color:var(--nx-line-strong)]/40 p-3">
                    <div class="text-[9px] uppercase tracking-[0.25em] text-[color:var(--nx-muted)]">geloggt</div>
                    <div class="text-3xl font-bold tabular-nums text-[color:var(--nx-text)] leading-none mt-1">{{ round($minutesLoggedToday / 60, 1) }}<span class="text-base text-[color:var(--nx-muted)] font-normal">h</span></div>
                </div>
            </div>

            {{-- Workload Top-5 --}}
            @if($workload->isNotEmpty())
                <div class="mt-4 flex-1 min-h-0 flex flex-col">
                    <div class="text-[10px] uppercase tracking-[0.25em] text-[color:var(--nx-muted)] mb-2 flex-shrink-0">Workload Top {{ $workload->count() }}</div>
                    <ul class="space-y-1.5 flex-1 min-h-0 overflow-y-auto">
                        @php $maxOpen = max(1, $workload->max('open')); @endphp
                        @foreach($workload as $w)
                            <li>
                                <div class="flex items-center justify-between text-[12px] mb-1">
                                    <span class="text-[color:var(--nx-text)] truncate">{{ $w->name }}</span>
                                    <span class="text-[11px] tabular-nums text-[color:var(--nx-muted)] flex-shrink-0">
                                        <span class="font-semibold text-[color:var(--nx-text)]">{{ $w->open }}</span> offen
                                        @if($w->overdue > 0)<span class="text-[color:var(--nx-danger)] ml-1">{{ $w->overdue }}↓</span>@endif
                                        @if($w->frogs > 0)<span class="text-[color:var(--nx-warning)] ml-1">{{ $w->frogs }}🐸</span>@endif
                                    </span>
                                </div>
                                <div class="h-1 w-full bg-[color:var(--nx-line)] rounded-full overflow-hidden">
                                    <div class="h-full bg-[color:var(--nx-accent)]" style="width: {{ round(($w->open / $maxOpen) * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- ── (1,3) KARTEILEICHEN ── --}}
        <div class="bg-[color:var(--nx-surface)] p-5 flex flex-col min-h-0">
            <div class="flex items-baseline justify-between mb-3 flex-shrink-0">
                <h2 class="text-xs uppercase tracking-[0.3em] text-[color:var(--nx-muted)] m-0 inline-flex items-center gap-2">
                    @svg('heroicon-o-archive-box-x-mark', 'w-4 h-4')
                    <span>Karteileichen</span>
                </h2>
                <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-faint)]">{{ $karteileichen->count() }} Conf ≤ 25%</span>
            </div>
            @if($karteileichen->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center text-[color:var(--nx-success)]">
                    @svg('heroicon-o-check-circle', 'w-14 h-14 mb-2')
                    <p class="text-sm m-0">Alle Projekte sind gepflegt.</p>
                </div>
            @else
                <ul class="flex-1 space-y-1.5 min-h-0 overflow-y-auto">
                    @foreach($karteileichen as $s)
                        @php
                            $missing = $s->confidence_reason && str_starts_with($s->confidence_reason, 'missing:')
                                ? array_map('trim', explode(',', substr($s->confidence_reason, strlen('missing:'))))
                                : [];
                        @endphp
                        <li class="rounded bg-[color:var(--nx-bg)] border border-[color:var(--nx-line-strong)]/30 p-2 flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded bg-[color:var(--nx-line)] text-[color:var(--nx-muted)] text-[11px] font-bold tabular-nums flex-shrink-0">
                                {{ $s->confidence_score }}<span class="text-[8px] opacity-60">%</span>
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] text-[color:var(--nx-text)] truncate">{{ $s->project?->name }}</div>
                                <div class="text-[10px] text-[color:var(--nx-muted)] truncate">
                                    @foreach($missing as $m)
                                        <span class="text-[color:var(--nx-danger)]">{{ str_replace('_', ' ', $m) }}@if(!$loop->last) · @endif</span>
                                    @endforeach
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ── (2,1) ÄLTESTE FRÖSCHE ── --}}
        <div class="bg-[color:var(--nx-surface)] p-5 flex flex-col min-h-0">
            <div class="flex items-baseline justify-between mb-3 flex-shrink-0">
                <h2 class="text-xs uppercase tracking-[0.3em] text-[color:var(--nx-warning)] m-0 inline-flex items-center gap-2">
                    @svg('heroicon-o-bug-ant', 'w-4 h-4')
                    <span>Älteste Frösche</span>
                </h2>
                <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-faint)]">teamweit</span>
            </div>
            @if($aelteste->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center text-[color:var(--nx-success)]">
                    @svg('heroicon-o-face-smile', 'w-14 h-14 mb-2')
                    <p class="text-sm m-0">Kein Frosch ist überfällig.</p>
                </div>
            @else
                <ul class="flex-1 space-y-1.5 min-h-0 overflow-y-auto">
                    @foreach($aelteste as $task)
                        @php $daysOver = (int) now()->startOfDay()->diffInDays($task->due_date->copy()->startOfDay(), false) * -1; @endphp
                        <li class="rounded bg-[var(--nx-warning)]/5 border border-[var(--nx-warning)]/20 p-2 flex items-center gap-2.5">
                            <span class="inline-flex flex-col items-center justify-center w-12 rounded bg-[var(--nx-warning)]/10 border border-[var(--nx-warning)]/30 px-1 py-1 flex-shrink-0">
                                <span class="text-base font-bold tabular-nums text-[color:var(--nx-warning)] leading-none">{{ $daysOver }}</span>
                                <span class="text-[8px] uppercase tracking-wider text-[color:var(--nx-warning)] mt-0.5">{{ $daysOver === 1 ? 'Tag' : 'Tage' }}</span>
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] text-[color:var(--nx-text)] truncate">{{ $task->title }}</div>
                                <div class="flex items-center gap-2 text-[10px] text-[color:var(--nx-muted)] mt-0.5">
                                    @if($task->project)
                                        <span class="truncate">{{ $task->project->name }}</span>
                                    @endif
                                    @if($task->userInCharge)
                                        <span>·</span>
                                        <span>{{ $task->userInCharge->name }}</span>
                                    @endif
                                    @if($task->postpone_count > 0)
                                        <span>·</span>
                                        <span class="text-[color:var(--nx-warning)]">{{ $task->postpone_count }}× verschoben</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ── (2,2) ACHSEN + BEWEGUNG ── --}}
        <div class="bg-[color:var(--nx-surface)] p-5 flex flex-col min-h-0 gap-4">

            {{-- Achsen-Breakdown --}}
            <div class="flex-shrink-0">
                <div class="text-xs uppercase tracking-[0.3em] text-[color:var(--nx-muted)] mb-2 inline-flex items-center gap-2">
                    @svg('heroicon-o-chart-pie', 'w-4 h-4')
                    <span>Wo brennt's?</span>
                </div>
                @php $axesTotal = max(1, array_sum($axesBreakdown)); @endphp
                <div class="space-y-2">
                    @foreach($axesBreakdown as $axis => $count)
                        @php $pct = round(($count / $axesTotal) * 100); @endphp
                        <div>
                            <div class="flex items-center justify-between text-[11px] mb-1">
                                <span class="inline-flex items-center gap-1.5 text-[color:var(--nx-text)]">
                                    @svg($axisIcon[$axis], 'w-3 h-3')
                                    <span>{{ $axisLabel[$axis] }}</span>
                                </span>
                                <span class="tabular-nums text-[color:var(--nx-muted)]">{{ $count }} <span class="text-[color:var(--nx-faint)]">({{ $pct }}%)</span></span>
                            </div>
                            <div class="h-1.5 w-full bg-[color:var(--nx-line)] rounded-full overflow-hidden">
                                <div class="h-full bg-[var(--nx-danger)]/70" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Bewegung --}}
            <div class="flex-1 min-h-0 grid grid-cols-2 gap-3 pt-2 border-t border-[color:var(--nx-line)]">
                <div class="min-h-0 flex flex-col">
                    <div class="text-[10px] uppercase tracking-[0.25em] text-[color:var(--nx-success)] mb-2 flex-shrink-0 inline-flex items-center gap-1">
                        ↑ Gewinner
                    </div>
                    @if($gewinner->isEmpty())
                        <div class="text-[11px] text-[color:var(--nx-faint)]">—</div>
                    @else
                        <ul class="space-y-1 min-h-0 overflow-y-auto">
                            @foreach($gewinner as $s)
                                <li class="flex items-center gap-2 text-[11px]">
                                    <span class="tabular-nums text-[color:var(--nx-success)] font-semibold flex-shrink-0">+{{ $s->delta_health_score }}</span>
                                    <span class="truncate text-[color:var(--nx-text)]">{{ $s->project?->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="min-h-0 flex flex-col">
                    <div class="text-[10px] uppercase tracking-[0.25em] text-[color:var(--nx-danger)] mb-2 flex-shrink-0 inline-flex items-center gap-1">
                        ↓ Verlierer
                    </div>
                    @if($verlierer->isEmpty())
                        <div class="text-[11px] text-[color:var(--nx-faint)]">—</div>
                    @else
                        <ul class="space-y-1 min-h-0 overflow-y-auto">
                            @foreach($verlierer as $s)
                                <li class="flex items-center gap-2 text-[11px]">
                                    <span class="tabular-nums text-[color:var(--nx-danger)] font-semibold flex-shrink-0">{{ $s->delta_health_score }}</span>
                                    <span class="truncate text-[color:var(--nx-text)]">{{ $s->project?->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── (2,3) 30-TAGE-TREND ── --}}
        <div class="bg-[color:var(--nx-surface)] p-5 flex flex-col min-h-0">
            <div class="flex items-baseline justify-between mb-3 flex-shrink-0">
                <h2 class="text-xs uppercase tracking-[0.3em] text-[color:var(--nx-muted)] m-0 inline-flex items-center gap-2">
                    @svg('heroicon-o-chart-bar', 'w-4 h-4')
                    <span>Trend 30 Tage</span>
                </h2>
                @php $trendCount = $trend->filter(fn ($p) => $p['avg'] !== null)->count(); @endphp
                <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-faint)] tabular-nums">{{ $trendCount }} Tage</span>
            </div>

            @php $values = $trend->pluck('avg')->filter(fn ($v) => $v !== null)->values(); @endphp
            @if($values->count() < 2)
                <div class="flex-1 flex flex-col items-center justify-center text-[color:var(--nx-faint)]">
                    @svg('heroicon-o-chart-bar', 'w-12 h-12 mb-2 opacity-40')
                    <p class="text-xs m-0">Zu wenig Snapshots für Trend.</p>
                    <p class="text-[10px] m-0 mt-1 text-[color:var(--nx-faint)]">(Cron läuft nächtlich)</p>
                </div>
            @else
                @php
                    $vMin = $values->min();
                    $vMax = $values->max();
                    $vRange = max(1, $vMax - $vMin);
                    $n = $values->count();
                    $w = 600;
                    $h = 120;
                    $padY = 12;
                    $pointsLine = '';
                    $pointsArea = "0,{$h}";
                    foreach ($values as $i => $v) {
                        $x = ($i / max(1, $n - 1)) * $w;
                        $y = $h - $padY - (($v - $vMin) / $vRange) * ($h - 2 * $padY);
                        $pointsLine .= ($i === 0 ? '' : ' ') . round($x, 1) . ',' . round($y, 1);
                        $pointsArea .= ' ' . round($x, 1) . ',' . round($y, 1);
                    }
                    $pointsArea .= ' ' . $w . ',' . $h;

                    $redValues = $trend->pluck('red');
                    $redMax = max(1, $redValues->max());
                @endphp

                <div class="flex-1 flex flex-col">
                    {{-- Avg Health Sparkline --}}
                    <div class="flex-1 min-h-0">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-[color:var(--nx-muted)] mb-1">Avg Health-Score</div>
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full h-20">
                            <defs>
                                <linearGradient id="opsTrendFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--nx-accent)" stop-opacity="0.4"/>
                                    <stop offset="100%" stop-color="var(--nx-accent)" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <line x1="0" y1="{{ $h/2 }}" x2="{{ $w }}" y2="{{ $h/2 }}" stroke="var(--nx-line)" stroke-width="0.5" stroke-dasharray="2,4"/>
                            <polygon points="{{ $pointsArea }}" fill="url(#opsTrendFill)"/>
                            <polyline points="{{ $pointsLine }}" fill="none" stroke="var(--nx-accent)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                        </svg>
                        <div class="flex items-center justify-between text-[10px] text-[color:var(--nx-faint)] tabular-nums">
                            <span>{{ $vMin }}</span>
                            <span class="text-[color:var(--nx-muted)]">Heute: <span class="text-[color:var(--nx-text)] font-semibold">{{ $values->last() }}</span></span>
                            <span>{{ $vMax }}</span>
                        </div>
                    </div>

                    {{-- Red Count Bar-Strip --}}
                    <div class="mt-3 flex-shrink-0">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-[color:var(--nx-danger)] mb-1">Rote Projekte</div>
                        <div class="flex items-end gap-px h-8">
                            @foreach($trend as $point)
                                @php $bh = round(($point['red'] / $redMax) * 100); @endphp
                                <div class="flex-1 bg-[var(--nx-danger)]/40 rounded-sm" style="height: {{ max(3, $bh) }}%;" title="{{ $point['date'] }}: {{ $point['red'] }}"></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ═══════════ LAUFBAND (Marquee, unten) ═══════════ --}}
    @if($recentDone->isNotEmpty())
        <footer class="border-t border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] flex items-center flex-shrink-0">
            {{-- Label fixed left --}}
            <span class="px-8 py-3 text-[10px] uppercase tracking-[0.3em] text-[color:var(--nx-success)] flex-shrink-0 inline-flex items-center gap-1.5 border-r border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] z-10">
                @svg('heroicon-o-check-circle', 'w-3 h-3')
                Erledigt zuletzt
            </span>

            {{-- Scrollender Track — Items doppelt rendern für nahtlosen Loop --}}
            <div class="flex-1 min-w-0 overflow-hidden py-3">
                <div class="ops-marquee-track text-xs text-[color:var(--nx-muted)]">
                    @for($pass = 0; $pass < 2; $pass++)
                        @foreach($recentDone as $task)
                            <span class="inline-flex items-center gap-2 flex-shrink-0" @if($pass === 1) aria-hidden="true" @endif>
                                <span class="w-1 h-1 rounded-full bg-[color:var(--nx-success)]"></span>
                                <span class="text-[color:var(--nx-text)]">{{ $task->title }}</span>
                                @if($task->project)
                                    <span class="text-[color:var(--nx-faint)]">· {{ $task->project->name }}</span>
                                @endif
                                @if($task->userInCharge)
                                    <span class="text-[color:var(--nx-faint)]">· {{ $task->userInCharge->name }}</span>
                                @endif
                                <span class="text-[color:var(--nx-faint)] tabular-nums">· {{ $task->lifecycle_state_changed_at?->format('H:i') }}</span>
                                <span class="text-[color:var(--nx-faint)] mx-2">•</span>
                            </span>
                        @endforeach
                    @endfor
                </div>
            </div>
        </footer>
    @endif
</div>
