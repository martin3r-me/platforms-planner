@php
    $axisLabels = ['strategy' => 'Strategie', 'progress' => 'Fortschritt', 'burn' => 'Druck'];
    $colorTones = [
        'red' => ['bg' => 'bg-[var(--nx-danger)]/10', 'border' => 'border-[var(--nx-danger)]/30', 'fg' => 'text-[color:var(--nx-danger)]', 'dot' => 'bg-[color:var(--nx-danger)]', 'fill' => 'bg-[color:var(--nx-danger)]', 'label' => 'Brennt'],
        'yellow' => ['bg' => 'bg-[var(--nx-warning)]/10', 'border' => 'border-[var(--nx-warning)]/30', 'fg' => 'text-[color:var(--nx-warning)]', 'dot' => 'bg-[color:var(--nx-warning)]', 'fill' => 'bg-[color:var(--nx-warning)]', 'label' => 'Achtung'],
        'green' => ['bg' => 'bg-[var(--nx-success)]/10', 'border' => 'border-[var(--nx-success)]/30', 'fg' => 'text-[color:var(--nx-success)]', 'dot' => 'bg-[color:var(--nx-success)]', 'fill' => 'bg-[color:var(--nx-success)]', 'label' => 'Stabil'],
        'gray' => ['bg' => 'bg-[var(--nx-bg)]', 'border' => 'border-[color:var(--nx-line)]', 'fg' => 'text-[color:var(--nx-muted)]', 'dot' => 'bg-[color:var(--nx-muted)]', 'fill' => 'bg-[color:var(--nx-muted)]', 'label' => 'Keine Daten'],
    ];
    $tone = fn ($c) => $colorTones[$c ?: 'gray'] ?? $colorTones['gray'];

    $missingLabel = [
        'canvas' => 'Project Canvas',
        'planned_period' => 'Geplanter Zeitraum',
        'planned_minutes' => 'Geplante Minuten',
        'tasks' => 'Aufgaben',
    ];

    // Stacked-bar widths for distribution
    $distTotal = max(1, array_sum($byColor));
    $idxVariant = ($byColor['red'] ?? 0) > 0 ? 'danger' : (($byColor['yellow'] ?? 0) > 0 ? 'warning' : 'success');
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Health-Index" icon="heroicon-o-heart" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Health-Index'],
        ]">
            {{-- Modus-Umschalter: Analyse (aktiv) ⟷ Wand --}}
            <div class="inline-flex rounded-md border border-[color:var(--nx-line-strong)] overflow-hidden">
                <span class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]">
                    @svg('heroicon-o-table-cells', 'w-3.5 h-3.5')
                    Analyse
                </span>
                <a href="{{ route('planner.ops') }}" wire:navigate
                   title="Wand-Modus (Vollbild-Monitor)"
                   class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] border-l border-[color:var(--nx-line-strong)] transition-colors">
                    @svg('heroicon-o-presentation-chart-line', 'w-3.5 h-3.5')
                    Wand
                </a>
            </div>

            {{-- Ampel-Verteilung als EIN Badge rechts (Projekt-Standard), health-getönt --}}
            <x-nx-badge :variant="$idxVariant" title="Brennt {{ $byColor['red'] ?? 0 }} · Achtung {{ $byColor['yellow'] ?? 0 }} · Stabil {{ $byColor['green'] ?? 0 }} · Keine Daten {{ $byColor['gray'] ?? 0 }}">
                <span class="inline-flex items-center gap-1 text-[color:var(--nx-danger)]"><span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-danger)]"></span><span class="tabular-nums">{{ $byColor['red'] ?? 0 }}</span></span>
                <span class="inline-flex items-center gap-1 text-[color:var(--nx-warning)]"><span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-warning)]"></span><span class="tabular-nums">{{ $byColor['yellow'] ?? 0 }}</span></span>
                <span class="inline-flex items-center gap-1 text-[color:var(--nx-success)]"><span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-success)]"></span><span class="tabular-nums">{{ $byColor['green'] ?? 0 }}</span></span>
                @if(($byColor['gray'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1 text-[var(--nx-muted)]"><span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-muted)]"></span><span class="tabular-nums">{{ $byColor['gray'] }}</span></span>
                @endif
            </x-nx-badge>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- ════════ LEFT SIDEBAR: Filter ════════ --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" icon="heroicon-o-funnel" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--nx-bg)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--nx-text)] leading-relaxed m-0">
                        Teamweite Health-Sicht aller Projekte. Daten aus dem jüngsten nächtlichen Snapshot.
                    </p>
                    @if($lastTakenOn)
                        <p class="text-[10px] text-[var(--nx-muted)] mt-1 m-0">Stand: {{ $lastTakenOn->format('d.m.Y') }}</p>
                    @endif
                </section>

                {{-- AMPEL-FILTER --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Ampel</h3>
                    <div class="space-y-1">
                        <button wire:click="$set('colorFilter', 'all')"
                                class="w-full flex items-center justify-between px-2 py-1.5 rounded text-[12px] transition-colors {{ $colorFilter === 'all' ? 'bg-[var(--nx-text)] text-white' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                            <span>Alle</span>
                            <span class="tabular-nums opacity-70 text-[11px]">{{ array_sum($byColor) }}</span>
                        </button>
                        @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                            @php $t = $tone($c); @endphp
                            <button wire:click="$set('colorFilter', '{{ $c }}')"
                                    class="w-full flex items-center justify-between px-2 py-1.5 rounded text-[12px] transition-colors {{ $colorFilter === $c ? $t['bg'].' '.$t['fg'].' border '.$t['border'] : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                                <span class="inline-flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $t['dot'] }}"></span>
                                    <span>{{ $t['label'] }}</span>
                                </span>
                                <span class="tabular-nums opacity-70 text-[11px]">{{ $byColor[$c] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>

                {{-- ACHSEN-FILTER --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Schwächste Achse</h3>
                    <div class="space-y-1">
                        <button wire:click="$set('axisFilter', 'all')"
                                class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $axisFilter === 'all' ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                            Alle
                        </button>
                        @foreach($axisLabels as $axisKey => $axisName)
                            <button wire:click="$set('axisFilter', '{{ $axisKey }}')"
                                    class="w-full flex items-center justify-between px-2 py-1 rounded text-[11px] transition-colors {{ $axisFilter === $axisKey ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                                <span>{{ $axisName }}</span>
                                <span class="tabular-nums opacity-60">{{ $byAxis[$axisKey] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>

                {{-- LEBENSZYKLUS --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Lebenszyklus</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach([
                            'all' => 'Alle',
                            'aktiv' => 'Aktiv',
                            'ruhend' => 'Ruhend',
                            'abgeschlossen' => 'Abgeschlossen',
                            'verworfen' => 'Verworfen',
                        ] as $key => $label)
                            <button wire:click="$set('lifecycleFilter', '{{ $key }}')"
                                    class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $lifecycleFilter === $key ? 'bg-[var(--nx-text)] text-white' : 'bg-[var(--nx-bg)] text-[var(--nx-text)] hover:bg-[var(--nx-line)]' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </section>

                {{-- SORTIERUNG --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Sortierung</h3>
                    <div class="space-y-1">
                        @foreach([
                            'worst' => 'Schlimmste zuerst',
                            'best' => 'Beste zuerst',
                            'confidence' => 'Geringste Confidence zuerst',
                            'movement' => 'Letzte Bewegung zuerst',
                            'name' => 'Name (A→Z)',
                        ] as $key => $label)
                            <button wire:click="$set('sort', '{{ $key }}')"
                                    class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $sort === $key ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- ════════ RIGHT SIDEBAR: Bewegung ════════ --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Bewegung" icon="heroicon-o-bolt" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4 bg-[var(--nx-bg)]">

                {{-- SCOPE-INFO --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Scope</h3>
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div>
                            <div class="text-xl font-bold tabular-nums text-[var(--nx-text)]">{{ $totalAll }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">Projekte</div>
                        </div>
                        <div>
                            <div class="text-xl font-bold tabular-nums text-[var(--nx-text)]">{{ $movedProjectsCount }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">bewegt vs Vortag</div>
                        </div>
                    </div>
                    @if($lastTakenOn)
                        <div class="mt-3 pt-3 border-t border-[color:var(--nx-line)] text-[10px] text-[var(--nx-muted)] text-center">
                            Snapshot {{ $lastTakenOn->format('d.m.Y') }} · Cron nächtlich 03:00
                        </div>
                    @endif
                </section>

                {{-- TOP GEWINNER --}}
                @if($topGainers->isNotEmpty())
                    <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-success)] mb-2 inline-flex items-center gap-1.5">
                            @svg('heroicon-o-arrow-trending-up', 'w-3 h-3')
                            <span>Größte Gewinner</span>
                        </h3>
                        <ul class="space-y-1.5">
                            @foreach($topGainers as $s)
                                @php $t = $tone($s->health_color); @endphp
                                <li>
                                    <a href="{{ route('planner.projects.health', $s->project_id) }}"
                                       wire:navigate
                                       class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--nx-bg)] transition-colors group">
                                        <span class="w-2 h-2 rounded-full {{ $t['dot'] }} flex-shrink-0"></span>
                                        <span class="flex-1 min-w-0 text-[12px] text-[var(--nx-text)] truncate group-hover:text-[var(--nx-accent)]">{{ $s->project?->name }}</span>
                                        <span class="text-[11px] tabular-nums font-semibold text-[color:var(--nx-success)] flex-shrink-0">↑{{ $s->delta_health_score }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- TOP VERLIERER --}}
                @if($topLosers->isNotEmpty())
                    <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-danger)] mb-2 inline-flex items-center gap-1.5">
                            @svg('heroicon-o-arrow-trending-down', 'w-3 h-3')
                            <span>Größte Verlierer</span>
                        </h3>
                        <ul class="space-y-1.5">
                            @foreach($topLosers as $s)
                                @php $t = $tone($s->health_color); @endphp
                                <li>
                                    <a href="{{ route('planner.projects.health', $s->project_id) }}"
                                       wire:navigate
                                       class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--nx-bg)] transition-colors group">
                                        <span class="w-2 h-2 rounded-full {{ $t['dot'] }} flex-shrink-0"></span>
                                        <span class="flex-1 min-w-0 text-[12px] text-[var(--nx-text)] truncate group-hover:text-[var(--nx-accent)]">{{ $s->project?->name }}</span>
                                        <span class="text-[11px] tabular-nums font-semibold text-[color:var(--nx-danger)] flex-shrink-0">↓{{ abs($s->delta_health_score) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if($topGainers->isEmpty() && $topLosers->isEmpty())
                    <section class="p-4 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] text-center">
                        @svg('heroicon-o-pause-circle', 'w-6 h-6 mx-auto mb-1 text-[var(--nx-muted)] opacity-50')
                        <p class="text-[11px] text-[var(--nx-muted)] m-0">Keine Bewegung gegenüber dem Vortag.</p>
                    </section>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- ════════ MAIN BODY ════════ --}}
    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Projekt-Liste --}}
        <div class="flex-1 overflow-y-auto px-4 py-3 bg-[var(--nx-bg)]">
            {{-- Ampel-Verteilung (Portfolio-Puls) --}}
            <div class="mb-3 rounded-xl border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] p-4 space-y-2">
                <div class="h-3 w-full bg-[color:var(--nx-line)] rounded-full overflow-hidden flex">
                    @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                        @php $w = round(($byColor[$c] / $distTotal) * 100, 2); $t = $tone($c); @endphp
                        @if($byColor[$c] > 0)
                            <div class="{{ $t['fill'] }} h-full" style="width: {{ $w }}%" title="{{ $t['label'] }}: {{ $byColor[$c] }} ({{ round($w) }}%)"></div>
                        @endif
                    @endforeach
                </div>
                @php $missingTotal = array_sum($missingLayers); @endphp
                @if($missingTotal > 0)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-[var(--nx-muted)]">
                        <span class="font-semibold uppercase tracking-wider">Datenpflege fehlt:</span>
                        @foreach($missingLayers as $key => $count)
                            @if($count > 0)
                                <span><span class="font-medium text-[var(--nx-text)] tabular-nums">{{ $count }}</span> × {{ $missingLabel[$key] }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            @if($snapshots->isEmpty())
                <div class="rounded-xl border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-12 text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-[color:var(--nx-line)] flex items-center justify-center mb-3">
                        @svg('heroicon-o-funnel', 'w-6 h-6 text-[color:var(--nx-muted)]')
                    </div>
                    <h3 class="text-sm font-medium text-[var(--nx-text)] m-0">Keine Projekte passen zu deinen Filtern</h3>
                    <p class="text-xs text-[var(--nx-muted)] mt-1 mb-0">Lockere die Filter links, um mehr Projekte zu sehen.</p>
                </div>
            @else
                <ul class="space-y-2">
                    @foreach($snapshots as $s)
                        @php
                            $t = $tone($s->health_color);
                            $axisVal = $s->axis_scores[$s->worst_axis] ?? null;
                            $worstLabel = $axisLabels[$s->worst_axis] ?? null;
                            $delta = $s->delta_health_score;
                            $deltaArrow = $delta === null || $delta === 0 ? null : ($delta > 0 ? '↑' : '↓');
                            $deltaColor = $delta === null ? 'text-[color:var(--nx-muted)]' : ($delta > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]');
                        @endphp
                        <li>
                            <a href="{{ route('planner.projects.health', $s->project_id) }}"
                               wire:navigate
                               class="group flex items-stretch rounded-xl border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] hover:border-[var(--nx-accent)]/60 hover:shadow-md transition-all overflow-hidden">

                                {{-- Score Spalte --}}
                                <div class="flex flex-col items-center justify-center w-20 flex-shrink-0 {{ $t['bg'] }} border-r {{ $t['border'] }} py-3">
                                    <span class="text-2xl font-bold tabular-nums {{ $t['fg'] }} leading-none">{{ $s->health_score ?? '–' }}</span>
                                    <span class="text-[9px] uppercase tracking-wider {{ $t['fg'] }} opacity-70 mt-1">{{ $t['label'] }}</span>
                                </div>

                                {{-- Hauptzeile --}}
                                <div class="flex-1 min-w-0 px-4 py-3">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if($s->project?->color)
                                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $s->project->color }}"></span>
                                        @endif
                                        <span class="text-sm font-semibold text-[var(--nx-text)] truncate">{{ $s->project?->name }}</span>
                                        @if($s->kind)
                                            <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-[var(--nx-bg)] text-[var(--nx-muted)] font-medium flex-shrink-0">{{ $s->kind }}</span>
                                        @endif
                                        @if($s->status && $s->status !== 'aktiv')
                                            <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-[var(--nx-warning)]/10 text-[color:var(--nx-warning)] font-medium flex-shrink-0">{{ $s->status }}</span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                                        @if($worstLabel)
                                            <span class="inline-flex items-center gap-1 {{ $t['fg'] }}">
                                                @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                                <span>Schwach: {{ $worstLabel }}{{ $axisVal !== null ? ' ('.$axisVal.')' : '' }}</span>
                                            </span>
                                        @endif
                                        @if($s->tasks_open > 0)
                                            <span class="text-[var(--nx-muted)]">
                                                <span class="tabular-nums font-medium text-[var(--nx-text)]">{{ $s->tasks_open }}</span> offen
                                            </span>
                                        @endif
                                        @if($s->tasks_overdue > 0)
                                            <span class="inline-flex items-center gap-1 text-[color:var(--nx-danger)]">
                                                @svg('heroicon-o-clock', 'w-3 h-3')
                                                <span class="tabular-nums font-medium">{{ $s->tasks_overdue }}</span> überfällig
                                            </span>
                                        @endif
                                        @if($s->tasks_frog > 0)
                                            <span class="inline-flex items-center gap-1 text-[color:var(--nx-warning)]">
                                                @svg('heroicon-o-bug-ant', 'w-3 h-3')
                                                <span class="tabular-nums font-medium">{{ $s->tasks_frog }}</span> Frösche
                                            </span>
                                        @endif
                                        @if($s->confidence_score < 50)
                                            <span class="inline-flex items-center gap-1 text-[color:var(--nx-muted)]">
                                                @svg('heroicon-o-question-mark-circle', 'w-3 h-3')
                                                <span>Confidence {{ $s->confidence_score }}%</span>
                                            </span>
                                        @endif
                                        @if($s->last_movement_at)
                                            <span class="text-[var(--nx-muted)]" title="{{ $s->last_movement_at->format('d.m.Y H:i') }}">
                                                · letzte Bewegung {{ $s->last_movement_at->diffForHumans(short: true) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Delta + Pfeil --}}
                                <div class="flex items-center gap-3 px-4 flex-shrink-0">
                                    @if($deltaArrow)
                                        <span class="inline-flex items-center gap-0.5 text-[11px] tabular-nums {{ $deltaColor }} font-medium" title="Veränderung Health-Score zum Vortag">
                                            {{ $deltaArrow }}{{ abs($delta) }}
                                        </span>
                                    @endif
                                    @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--nx-muted)] group-hover:text-[var(--nx-accent)] transition-colors')
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-ui-page>
