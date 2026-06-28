@php
    use Carbon\Carbon;

    $axisLabels = [
        'strategy' => 'Strategie',
        'progress' => 'Fortschritt',
        'burn' => 'Druck',
    ];
    $axisExplain = [
        'strategy' => 'Canvas-Vollstaendigkeit + kritische Bloecke + Risiken + ueberfaellige Milestones',
        'progress' => 'Verhaeltnis erledigte zu Gesamt-Tasks',
        'burn' => 'Ueberfaellige + Frogs + Time-Over-Plan + Budget-Ueberschreitung',
    ];

    $colorDot = fn ($c) => match($c) {
        'green' => 'bg-emerald-500',
        'yellow' => 'bg-amber-500',
        'red' => 'bg-rose-500',
        default => 'bg-zinc-300',
    };
    $colorChip = fn ($c) => match($c) {
        'green' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'yellow' => 'bg-amber-50 text-amber-700 border-amber-200',
        'red' => 'bg-rose-50 text-rose-700 border-rose-200',
        default => 'bg-zinc-50 text-zinc-500 border-zinc-200',
    };
    $scoreToColor = fn ($v) => $v === null ? 'gray' : ($v >= 70 ? 'green' : ($v >= 40 ? 'yellow' : 'red'));

    $axisScores = $latest?->axis_scores ?? [];

    // Trend-Daten in Sparkline-Form
    $trendValues = $trend->pluck('health_score')->filter(fn ($v) => $v !== null)->values()->all();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Health · ' . $project->title" icon="heroicon-o-heart" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => $project->title, 'href' => route('planner.projects.show', $project)],
            ['label' => 'Health'],
        ]">
            <x-ui-button variant="secondary-ghost" size="sm" wire:click="refreshSnapshot" title="Snapshot jetzt neu rechnen">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Neu rechnen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <div class="p-6 max-w-7xl mx-auto space-y-6">

        @if(!$latest)
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-12 text-center">
                <div class="mx-auto w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                    @svg('heroicon-o-heart', 'w-6 h-6 text-zinc-400')
                </div>
                <h3 class="text-sm font-medium text-[var(--ui-secondary)] m-0">Noch kein Snapshot vorhanden</h3>
                <p class="text-xs text-[var(--ui-muted)] mt-1 mb-4">Snapshots werden naechtlich um 03:00 erstellt. Du kannst auch jetzt einen erzwingen.</p>
                <x-ui-button variant="primary" size="sm" wire:click="refreshSnapshot">
                    @svg('heroicon-o-arrow-path', 'w-4 h-4')
                    <span>Snapshot jetzt erstellen</span>
                </x-ui-button>
            </div>
        @else

        {{-- HEADER: Health-Hauptkarte --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Health Score Card --}}
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-5 lg:col-span-2">
                <div class="flex items-start gap-5">
                    {{-- Big circle --}}
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-full {{ $colorDot($latest->health_color) }}/15 border-4 {{ str_replace('bg-', 'border-', $colorDot($latest->health_color)) }} flex items-center justify-center">
                            <div class="text-center">
                                <div class="text-3xl font-bold tabular-nums leading-none">{{ $latest->health_score ?? '–' }}</div>
                                <div class="text-[9px] uppercase tracking-wider text-[var(--ui-muted)] mt-0.5">Score</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-medium {{ $colorChip($latest->health_color) }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $colorDot($latest->health_color) }}"></span>
                                {{ ucfirst($latest->health_color ?? 'unbekannt') }}
                            </span>
                            @if($latest->delta_health_score !== null && $latest->delta_health_score !== 0)
                                @php
                                    $deltaColor = $latest->delta_health_score > 0 ? 'text-emerald-600' : 'text-rose-600';
                                    $deltaArrow = $latest->delta_health_score > 0 ? '↑' : '↓';
                                @endphp
                                <span class="text-[11px] tabular-nums {{ $deltaColor }}">
                                    {{ $deltaArrow }} {{ abs($latest->delta_health_score) }} vs Vortag
                                </span>
                            @endif
                        </div>

                        @if($latest->worst_axis && isset($axisLabels[$latest->worst_axis]))
                            <div class="text-sm text-[var(--ui-secondary)] mb-1">
                                Schwaechste Achse: <strong>{{ $axisLabels[$latest->worst_axis] }}</strong>
                            </div>
                        @endif

                        <div class="flex items-center gap-3 text-xs text-[var(--ui-muted)]">
                            <span>Stand: {{ $latest->taken_on?->format('d.m.Y') }}</span>
                            <span>·</span>
                            <span>Trigger: {{ $latest->trigger }}</span>
                            @if($latest->last_movement_at)
                                <span>·</span>
                                <span title="Letzte Aenderung an Tasks/TimeEntries/Canvas">letzte Bewegung {{ $latest->last_movement_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Confidence Card --}}
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-5">
                <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] mb-1">Confidence</div>
                <div class="text-3xl font-bold tabular-nums">{{ $latest->confidence_score }}<span class="text-base font-normal text-[var(--ui-muted)]">%</span></div>
                <div class="mt-2 h-1.5 w-full bg-zinc-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $latest->confidence_score >= 75 ? 'bg-emerald-500' : ($latest->confidence_score >= 50 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ $latest->confidence_score }}%"></div>
                </div>
                @if($latest->confidence_reason)
                    @php
                        $missing = str_starts_with($latest->confidence_reason, 'missing:')
                            ? explode(',', substr($latest->confidence_reason, strlen('missing:')))
                            : [];
                    @endphp
                    @if(!empty($missing))
                        <div class="mt-3 text-[11px] text-[var(--ui-muted)]">Fehlt:</div>
                        <ul class="text-[11px] mt-0.5 space-y-0.5">
                            @foreach($missing as $m)
                                <li class="text-rose-600">· {{ trim($m) }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        </section>

        {{-- AXIS CARDS --}}
        <section>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Achsen-Breakdown</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach(['strategy', 'progress', 'burn'] as $axisKey)
                    @php
                        $axisVal = $axisScores[$axisKey] ?? null;
                        $axisColor = $scoreToColor($axisVal);
                        $isWorst = $latest->worst_axis === $axisKey;
                    @endphp
                    <div class="rounded-xl border {{ $isWorst ? 'border-rose-300 shadow-sm' : 'border-[var(--ui-border)]' }} bg-white p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $colorDot($axisColor) }}"></span>
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $axisLabels[$axisKey] }}</span>
                                @if($isWorst)
                                    <span class="text-[9px] uppercase tracking-wider text-rose-600 font-semibold">Schwach</span>
                                @endif
                            </div>
                            <span class="text-2xl font-bold tabular-nums text-[var(--ui-secondary)]">{{ $axisVal ?? '–' }}</span>
                        </div>
                        <p class="text-[11px] text-[var(--ui-muted)] m-0 leading-snug">{{ $axisExplain[$axisKey] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- TREND --}}
        <section class="rounded-xl border border-[var(--ui-border)] bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Trend ({{ $trendDays }} Tage)</h3>
                <div class="flex items-center gap-1">
                    @foreach([7, 30, 90, 180] as $opt)
                        <button type="button" wire:click="setTrendDays({{ $opt }})"
                                class="px-2 py-1 rounded text-[11px] {{ $trendDays === $opt ? 'bg-[var(--ui-secondary)] text-white' : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)]' }}">
                            {{ $opt }}d
                        </button>
                    @endforeach
                </div>
            </div>

            @if(count($trendValues) < 2)
                <div class="text-[11px] text-[var(--ui-muted)] py-6 text-center">
                    Noch zu wenig Snapshots fuer einen Trend ({{ count($trendValues) }} Stuetzpunkt(e)).
                </div>
            @else
                @php
                    $vMin = min($trendValues);
                    $vMax = max($trendValues);
                    $vRange = max(1, $vMax - $vMin);
                    $w = 800;
                    $h = 100;
                    $n = count($trendValues);
                    $points = '';
                    foreach ($trendValues as $i => $v) {
                        $x = ($i / max(1, $n - 1)) * $w;
                        $y = $h - (($v - $vMin) / $vRange) * ($h - 8) - 4;
                        $points .= ($i === 0 ? '' : ' ') . round($x, 1) . ',' . round($y, 1);
                    }
                @endphp
                <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full h-24">
                    <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2" class="text-[var(--ui-primary)]" />
                </svg>
                <div class="flex items-center justify-between text-[10px] text-[var(--ui-muted)] mt-1">
                    <span>{{ Carbon::parse($trend->first()?->taken_on)->format('d.m.') }} · {{ $trend->first()?->health_score ?? '–' }}</span>
                    <span>min {{ $vMin }} · max {{ $vMax }}</span>
                    <span>{{ Carbon::parse($trend->last()?->taken_on)->format('d.m.') }} · {{ $trend->last()?->health_score ?? '–' }}</span>
                </div>
            @endif
        </section>

        {{-- KEY NUMBERS GRID --}}
        <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-4">
                <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Tasks offen</div>
                <div class="text-2xl font-bold tabular-nums">{{ $latest->tasks_open }}<span class="text-sm text-[var(--ui-muted)] font-normal"> / {{ $latest->tasks_total }}</span></div>
                @if($latest->tasks_overdue > 0)
                    <div class="text-[11px] text-rose-600 mt-1">{{ $latest->tasks_overdue }} ueberfaellig</div>
                @endif
            </div>
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-4">
                <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Frogs</div>
                <div class="text-2xl font-bold tabular-nums">{{ $latest->tasks_frog }}</div>
                @if($latest->tasks_postponed > 0)
                    <div class="text-[11px] text-amber-600 mt-1">{{ $latest->tasks_postponed }} postponed</div>
                @endif
            </div>
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-4">
                <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Story Points</div>
                <div class="text-2xl font-bold tabular-nums">{{ $latest->story_points_done }}<span class="text-sm text-[var(--ui-muted)] font-normal"> / {{ $latest->story_points_total }}</span></div>
            </div>
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-4">
                <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Zeit (h)</div>
                <div class="text-2xl font-bold tabular-nums">{{ round($latest->minutes_logged / 60, 1) }}<span class="text-sm text-[var(--ui-muted)] font-normal"> / {{ round($latest->minutes_planned / 60, 1) }}</span></div>
                <div class="text-[11px] text-[var(--ui-muted)] mt-1">geloggt / geplant</div>
            </div>
        </section>

        {{-- SUB-DATA: FROGS + PEOPLE --}}
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Top-5 Frogs --}}
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3 m-0">Top 5 Froesche</h3>
                @if($latest->frogs->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)] m-0">Keine offenen Froesche.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($latest->frogs as $frog)
                            <li class="flex items-start gap-3 py-2 border-b border-[var(--ui-border)]/40 last:border-b-0">
                                <span class="text-[10px] tabular-nums text-[var(--ui-muted)] mt-0.5">#{{ $frog->rank }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm text-[var(--ui-secondary)] truncate">{{ $frog->task_title }}</div>
                                    <div class="flex items-center gap-2 text-[11px] text-[var(--ui-muted)] mt-0.5">
                                        @if($frog->due_date)
                                            <span class="{{ $frog->is_overdue ? 'text-rose-600 font-medium' : '' }}">
                                                {{ $frog->is_overdue ? 'ueberfaellig: ' : 'faellig: ' }}{{ $frog->due_date->format('d.m.Y') }}
                                            </span>
                                        @else
                                            <span>kein Datum</span>
                                        @endif
                                        @if($frog->postpone_count > 0)
                                            <span>·</span>
                                            <span title="Wie oft verschoben">{{ $frog->postpone_count }}× postponed</span>
                                        @endif
                                        @if($frog->story_points)
                                            <span>·</span>
                                            <span>{{ strtoupper($frog->story_points) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Person-Workload --}}
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3 m-0">Workload</h3>
                @if($latest->people->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)] m-0">Niemand hat aktuell offene Tasks.</p>
                @else
                    @php $maxSp = max(1, $latest->people->max('sp_open')); @endphp
                    <ul class="space-y-2">
                        @foreach($latest->people as $person)
                            <li class="py-1">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span class="text-[var(--ui-secondary)] truncate">{{ $person->user_name }}</span>
                                    <span class="tabular-nums text-[var(--ui-muted)] text-xs">
                                        {{ $person->open_tasks }} offen
                                        @if($person->overdue_tasks > 0)
                                            <span class="text-rose-600">· {{ $person->overdue_tasks }} ueberfaellig</span>
                                        @endif
                                    </span>
                                </div>
                                @if($person->sp_open > 0)
                                    <div class="h-1.5 w-full bg-zinc-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-[var(--ui-primary)]" style="width: {{ round(($person->sp_open / $maxSp) * 100) }}%"></div>
                                    </div>
                                    <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">{{ $person->sp_open }} SP open</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- SLOT BREAKDOWN --}}
        @if($latest->slots->isNotEmpty())
            <section class="rounded-xl border border-[var(--ui-border)] bg-white p-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3 m-0">Slot-Verteilung</h3>
                @php $maxTotal = max(1, $latest->slots->max('total_tasks')); @endphp
                <ul class="space-y-2">
                    @foreach($latest->slots as $slot)
                        <li>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-[var(--ui-secondary)] truncate">{{ $slot->slot_name }}</span>
                                <span class="tabular-nums text-xs text-[var(--ui-muted)]">{{ $slot->open_tasks }} offen / {{ $slot->done_tasks }} done</span>
                            </div>
                            <div class="h-1.5 w-full bg-zinc-100 rounded-full overflow-hidden flex">
                                <div class="h-full bg-emerald-500" style="width: {{ round(($slot->done_tasks / $maxTotal) * 100) }}%"></div>
                                <div class="h-full bg-amber-500" style="width: {{ round(($slot->open_tasks / $maxTotal) * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @endif {{-- latest --}}
    </div>
</x-ui-page>
