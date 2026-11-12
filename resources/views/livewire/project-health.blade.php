@php
    use Carbon\Carbon;
    use Platform\Planner\Services\ProjectSnapshotService;

    // Health = Lagebild mit Begründung: Wert → warum (konkrete Gründe je Achse) → wie belastbar → Verlauf.
    // Kennzahlen, Auslastung und Spalten stehen im Überblick bzw. am Board, nicht hier.
    $axisLabels = ['strategy' => 'Strategie', 'progress' => 'Fortschritt', 'burn' => 'Druck'];
    $axisQuestion = [
        'strategy' => 'Ist klar, wohin das Projekt will?',
        'progress' => 'Kommt die Arbeit so voran wie geplant?',
        'burn' => 'Wie viel brennt gerade?',
    ];
    $axisIcons = ['strategy' => 'heroicon-o-squares-2x2', 'progress' => 'heroicon-o-arrow-trending-up', 'burn' => 'heroicon-o-fire'];
    $axisWeights = ['strategy' => 30, 'progress' => 40, 'burn' => 30];

    $colorVar = ['green' => 'var(--nx-success)', 'yellow' => 'var(--nx-warning)', 'red' => 'var(--nx-danger)', 'gray' => 'var(--nx-muted)'];
    $scoreToColor = fn ($v) => $v === null ? 'gray' : ($v >= 70 ? 'green' : ($v >= 40 ? 'yellow' : 'red'));
    $c = fn ($color) => $colorVar[$color ?? 'gray'] ?? $colorVar['gray'];

    $nav = \Platform\Planner\Support\ProjectNavSummary::for($project);

    if ($latest) {
        $axisScores = $latest->axis_scores ?? [];
        $why = ProjectSnapshotService::explain($latest);
        $healthWord = match ($latest->health_color) {
            'green' => 'Im Plan',
            'yellow' => 'Unter Beobachtung',
            'red' => 'Kritisch',
            'gray' => 'Zu wenig Daten für eine Aussage',
            default => 'Unbekannt',
        };
        $ageDays = $latest->taken_on ? (int) $latest->taken_on->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;

        $missingLayers = [];
        if ($latest->confidence_reason && str_starts_with($latest->confidence_reason, 'missing:')) {
            $missingLayers = array_filter(array_map('trim', explode(',', substr($latest->confidence_reason, strlen('missing:')))));
        }
        $missingInfo = [
            'canvas' => ['label' => 'Canvas fehlt', 'hint' => 'Ziel, Umfang, Meilensteine und Risiken festhalten', 'url' => $nav['canvas']['url'], 'settings' => false],
            'planned_period' => ['label' => 'Kein geplanter Zeitraum', 'hint' => 'Ohne Start und Ende lässt sich der Fortschritt nicht am Soll messen', 'url' => null, 'settings' => true],
            'planned_minutes' => ['label' => 'Keine geplanten Stunden', 'hint' => 'Ohne Plan zählt Mehraufwand nicht in den Druck', 'url' => null, 'settings' => true],
            'tasks' => ['label' => 'Keine Aufgaben', 'hint' => 'Fortschritt ist erst mit Aufgaben messbar', 'url' => route('planner.projects.show', $project), 'settings' => false],
            'lifetime_mature' => ['label' => 'Projekt jünger als 24 Stunden', 'hint' => 'Erledigt sich von selbst', 'url' => null, 'settings' => false],
        ];

        // Verlauf: feste Skala 0–100 mit Ampel-Bändern (70 / 40), damit kleine Schwankungen nicht dramatisch wirken
        $trendPoints = $trend->filter(fn ($p) => $p->health_score !== null)->values();
    }

    $card = 'rounded-xl bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]';
    $h2 = 'text-[11px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0';
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->title" icon="heroicon-o-heart" />
    </x-slot>

    <x-slot name="actionbar">
        <x-planner::actionbar :project="$project" active="health" :filters-active="$trendDays !== 30 ? 1 : 0">
            <x-slot:filters>
                <x-planner::filter-group label="Verlauf">
                    @foreach([7, 30, 90, 180] as $opt)
                        <x-planner::chip :active="$trendDays === $opt" wire:click="setTrendDays({{ $opt }})">{{ $opt }} Tage</x-planner::chip>
                    @endforeach
                </x-planner::filter-group>
            </x-slot:filters>

            <x-slot:more>
                <x-planner::menu-item icon="heroicon-o-arrow-path" wire:click="refreshSnapshot" title="Health jetzt neu berechnen">Neu berechnen</x-planner::menu-item>
            </x-slot:more>
        </x-planner::actionbar>
    </x-slot>

    <x-planner::body>
    <div class="w-full space-y-4">

    @if(! $latest)
        <section class="{{ $card }} p-10 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-[color:var(--nx-line)] flex items-center justify-center mb-3">
                @svg('heroicon-o-heart', 'w-7 h-7 text-[color:var(--nx-muted)]')
            </div>
            <h3 class="text-base font-semibold text-[var(--nx-text)] m-0">Noch keine Health-Messung</h3>
            <p class="text-sm text-[var(--nx-muted)] mt-2 mb-5 max-w-md mx-auto">Health wird jede Nacht um 03:00 berechnet. Du kannst jetzt sofort einen Stand erzeugen.</p>
            <x-nx-button variant="primary" size="md" wire:click="refreshSnapshot">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Jetzt berechnen</span>
            </x-nx-button>
        </section>
    @else

        {{-- ═══ ERGEBNIS ═══ --}}
        <section class="{{ $card }} p-5">
            <div class="flex flex-wrap items-center gap-5">
                @php
                    $circ = 2 * pi() * 44;
                    $offset = $latest->health_score !== null ? $circ * (1 - $latest->health_score / 100) : $circ;
                @endphp
                <div class="relative w-24 h-24 shrink-0">
                    <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                        <circle cx="50" cy="50" r="44" fill="none" stroke="var(--nx-line)" stroke-width="7"/>
                        <circle cx="50" cy="50" r="44" fill="none" stroke="{{ $c($latest->health_color) }}" stroke-width="7"
                                stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}" stroke-linecap="round" class="transition-all duration-500"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-bold tabular-nums leading-none" style="color: {{ $c($latest->health_color) }};">{{ $latest->health_score ?? '–' }}</span>
                        <span class="text-[10px] text-[var(--nx-muted)] mt-1">von 100</span>
                    </div>
                </div>

                <div class="flex-1 min-w-[14rem]">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <h2 class="text-xl font-semibold m-0" style="color: {{ $c($latest->health_color) }};">{{ $healthWord }}</h2>
                        @if($latest->delta_health_score)
                            <span class="text-sm tabular-nums font-medium" style="color: {{ $latest->delta_health_score > 0 ? 'var(--nx-success)' : 'var(--nx-danger)' }};">
                                {{ $latest->delta_health_score > 0 ? '↑' : '↓' }} {{ abs($latest->delta_health_score) }} seit der letzten Messung
                            </span>
                        @endif
                    </div>
                    @if($latest->worst_axis && isset($axisLabels[$latest->worst_axis]) && $latest->health_color !== 'green')
                        <p class="text-sm text-[var(--nx-text)] mt-1 mb-0">
                            Hauptursache: <strong>{{ $axisLabels[$latest->worst_axis] }}</strong> — {{ $why[$latest->worst_axis]['reasons'][0]['text'] ?? '' }}
                        </p>
                    @elseif($latest->health_color === 'gray')
                        <p class="text-sm text-[var(--nx-text)] mt-1 mb-0">Es fehlen Angaben, ohne die der Wert geraten wäre — siehe „Belastbarkeit“.</p>
                    @else
                        <p class="text-sm text-[var(--nx-text)] mt-1 mb-0">Keine Achse zeigt Handlungsbedarf.</p>
                    @endif
                    <p class="text-xs mt-2 mb-0 {{ $ageDays !== null && $ageDays >= 2 ? 'text-[var(--nx-warning)]' : 'text-[var(--nx-muted)]' }}">
                        Stand {{ $latest->taken_at?->format('d.m.Y, H:i') ?? $latest->taken_on?->format('d.m.Y') }}
                        @if($ageDays !== null && $ageDays >= 1) ({{ $ageDays === 1 ? 'gestern' : 'vor ' . $ageDays . ' Tagen' }}) @endif
                        · {{ $latest->trigger === 'manual' ? 'manuell' : 'automatisch' }}
                        · <button type="button" wire:click="refreshSnapshot" wire:loading.attr="disabled" class="underline hover:text-[var(--nx-text)]">neu berechnen</button>
                    </p>
                </div>
            </div>
        </section>

        {{-- ═══ WARUM: drei Achsen mit konkreten Gründen ═══ --}}
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach(['progress', 'burn', 'strategy'] as $axisKey)
                @php
                    $val = $axisScores[$axisKey] ?? null;
                    // Fortschritt ohne geplanten Zeitraum: angezeigt, aber nicht bewertet
                    $rated = ! ($axisKey === 'progress' && ! ($latest->planned_start && $latest->planned_end));
                    $col = $c($rated ? $scoreToColor($val) : 'gray');
                    $isWorst = $latest->worst_axis === $axisKey && $latest->health_color !== 'green';
                    $link = match ($axisKey) {
                        'strategy' => $nav['canvas']['url'],
                        default => route('planner.projects.show', $project),
                    };
                @endphp
                <div class="{{ $card }} p-4 flex flex-col {{ $isWorst ? 'ring-2' : '' }}" @if($isWorst) style="--tw-ring-color: {{ $col }};" @endif>
                    <div class="flex items-center gap-2">
                        <span style="color: {{ $col }};">@svg($axisIcons[$axisKey], 'w-4 h-4')</span>
                        <span class="text-sm font-semibold text-[var(--nx-text)]">{{ $axisLabels[$axisKey] }}</span>
                        <span class="text-[10px] text-[var(--nx-muted)]">{{ $rated ? 'Gewicht ' . $axisWeights[$axisKey] . ' %' : 'nicht bewertet' }}</span>
                        <span class="ml-auto text-2xl font-bold tabular-nums" style="color: {{ $col }};">{{ $val ?? '–' }}</span>
                    </div>
                    <p class="text-xs text-[var(--nx-muted)] mt-1 mb-3">{{ $axisQuestion[$axisKey] }}</p>
                    <div class="h-1.5 rounded-full bg-[var(--nx-line)] overflow-hidden mb-3">
                        <div class="h-full rounded-full" style="width: {{ $val ?? 0 }}%; background-color: {{ $col }};"></div>
                    </div>
                    <ul class="space-y-1.5 flex-1">
                        @foreach($why[$axisKey]['reasons'] as $r)
                            <li class="flex items-start gap-2 text-sm">
                                <span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $r['delta'] ? 'var(--nx-danger)' : 'var(--nx-line-strong)' }};"></span>
                                <span class="flex-1 text-[var(--nx-text)]">{{ $r['text'] }}</span>
                                @if($r['delta'])
                                    <span class="shrink-0 tabular-nums text-xs font-medium text-[var(--nx-danger)]">{{ $r['delta'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($axisKey === 'burn' && $latest->frogs->isNotEmpty())
                        <div class="mt-3 pt-3 border-t border-[color:var(--nx-line)] space-y-1">
                            @foreach($latest->frogs->take(3) as $frog)
                                <div class="text-xs flex items-center gap-1.5 min-w-0">
                                    <span>🐸</span>
                                    <span class="truncate text-[var(--nx-text)]">{{ $frog->task_title }}</span>
                                    @if($frog->is_overdue)
                                        <span class="shrink-0 text-[var(--nx-danger)]">überfällig</span>
                                    @elseif($frog->postpone_count > 0)
                                        <span class="shrink-0 text-[var(--nx-warning)]">{{ $frog->postpone_count }}× verschoben</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ $link }}" wire:navigate class="mt-3 text-xs text-[var(--nx-muted)] hover:text-[var(--nx-text)]">
                        {{ $axisKey === 'strategy' ? 'Zum Canvas →' : 'Zum Board →' }}
                    </a>
                </div>
            @endforeach
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

            {{-- ═══ VERLAUF ═══ --}}
            <section class="{{ $card }} p-5 lg:col-span-2">
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <h2 class="{{ $h2 }}">Verlauf · {{ $trendDays }} Tage</h2>
                    @if($trendPoints->count() >= 2)
                        @php $totalDelta = (int) $trendPoints->last()->health_score - (int) $trendPoints->first()->health_score; @endphp
                        <span class="text-sm tabular-nums font-semibold" style="color: {{ $totalDelta > 0 ? 'var(--nx-success)' : ($totalDelta < 0 ? 'var(--nx-danger)' : 'var(--nx-muted)') }};">
                            {{ $totalDelta > 0 ? '+' : '' }}{{ $totalDelta }} im Zeitraum
                        </span>
                    @endif
                </div>

                @if($trendPoints->count() < 2)
                    <div class="py-10 text-center text-sm text-[var(--nx-muted)]">
                        Noch zu wenige Messungen für einen Verlauf ({{ $trendPoints->count() }}). Health wird jede Nacht gemessen.
                    </div>
                @else
                    @php
                        $w = 800; $h = 160;
                        $from = Carbon::parse($trendFrom)->startOfDay();
                        $span = max(1, Carbon::parse($trendTo)->startOfDay()->diffInDays($from, true));
                        $xy = fn ($p) => [
                            round($from->diffInDays(Carbon::parse($p->taken_on)->startOfDay(), true) / $span * $w, 1),
                            round($h - ($p->health_score / 100) * $h, 1),
                        ];
                        $line = $trendPoints->map(fn ($p) => implode(',', $xy($p)))->implode(' ');
                    @endphp
                    <div class="relative">
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full h-40">
                            {{-- Ampel-Bänder: ≥ 70 grün, 40–69 gelb, < 40 rot --}}
                            <rect x="0" y="0" width="{{ $w }}" height="{{ $h * 0.30 }}" fill="var(--nx-success)" opacity="0.06"/>
                            <rect x="0" y="{{ $h * 0.30 }}" width="{{ $w }}" height="{{ $h * 0.30 }}" fill="var(--nx-warning)" opacity="0.06"/>
                            <rect x="0" y="{{ $h * 0.60 }}" width="{{ $w }}" height="{{ $h * 0.40 }}" fill="var(--nx-danger)" opacity="0.05"/>
                            <polyline points="{{ $line }}" fill="none" stroke="var(--nx-text)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" opacity="0.7"/>
                        </svg>
                        {{-- Punkte als HTML, damit sie bei gestreckter Grafik rund bleiben --}}
                        @foreach($trendPoints as $p)
                            @php [$px, $py] = $xy($p); @endphp
                            <span class="absolute w-2 h-2 -ml-1 -mt-1 rounded-full border border-[color:var(--nx-surface)]"
                                  style="left: {{ $px / $w * 100 }}%; top: {{ $py / $h * 100 }}%; background-color: {{ $c($p->health_color) }};"
                                  title="{{ $p->taken_on?->format('d.m.') }}: {{ $p->health_score }}"></span>
                        @endforeach
                        <span class="absolute top-0 right-1 text-[9px] text-[var(--nx-muted)]">100</span>
                        <span class="absolute right-1 text-[9px] text-[var(--nx-muted)]" style="top: 30%;">70</span>
                        <span class="absolute right-1 text-[9px] text-[var(--nx-muted)]" style="top: 60%;">40</span>
                    </div>
                    <div class="flex justify-between text-[10px] text-[var(--nx-muted)] mt-1 tabular-nums">
                        <span>{{ Carbon::parse($trendFrom)->format('d.m.') }}</span>
                        <span>{{ Carbon::parse($trendTo)->format('d.m.Y') }}</span>
                    </div>
                @endif
            </section>

            {{-- ═══ BELASTBARKEIT ═══ --}}
            <section class="{{ $card }} p-5">
                @php $conf = (int) $latest->confidence_score; $confCol = $conf >= 75 ? 'green' : ($conf >= 50 ? 'yellow' : 'red'); @endphp
                <div class="flex items-baseline justify-between mb-2">
                    <h2 class="{{ $h2 }}">Belastbarkeit</h2>
                    <span class="text-lg font-bold tabular-nums" style="color: {{ $c($confCol) }};">{{ $conf }} %</span>
                </div>
                <div class="h-1.5 rounded-full bg-[var(--nx-line)] overflow-hidden mb-3">
                    <div class="h-full rounded-full" style="width: {{ $conf }}%; background-color: {{ $c($confCol) }};"></div>
                </div>
                <p class="text-xs text-[var(--nx-muted)] mt-0 mb-3">Wie viel der Wert weiß. Unter 50 % wird keine Health angezeigt.</p>

                @if(empty($missingLayers))
                    <div class="flex items-center gap-2 text-sm text-[var(--nx-success)]">
                        @svg('heroicon-o-check-circle', 'w-4 h-4') Alle Angaben vorhanden
                    </div>
                @else
                    <ul class="space-y-2.5">
                        @foreach($missingLayers as $m)
                            @php $info = $missingInfo[$m] ?? ['label' => $m, 'hint' => null, 'url' => null, 'settings' => false]; @endphp
                            <li class="flex items-start gap-2 text-sm">
                                @svg('heroicon-o-exclamation-circle', 'w-4 h-4 mt-0.5 shrink-0 text-[var(--nx-warning)]')
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[var(--nx-text)]">{{ $info['label'] }}</span>
                                    @if($info['hint'])<span class="block text-xs text-[var(--nx-muted)]">{{ $info['hint'] }}</span>@endif
                                    @if($info['url'])
                                        <a href="{{ $info['url'] }}" wire:navigate class="text-xs text-[var(--nx-accent)] hover:underline">Nachtragen →</a>
                                    @elseif($info['settings'])
                                        <button type="button" x-on:click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })" class="text-xs text-[var(--nx-accent)] hover:underline">In den Projekteinstellungen nachtragen →</button>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($latest->last_movement_at)
                    <p class="text-xs text-[var(--nx-muted)] mt-4 mb-0 pt-3 border-t border-[color:var(--nx-line)]" title="{{ $latest->last_movement_at->format('d.m.Y H:i') }}">
                        Letzte Bewegung im Projekt {{ $latest->last_movement_at->diffForHumans() }}
                    </p>
                @endif
            </section>
        </div>

        <p class="text-[11px] text-[var(--nx-muted)] m-0">
            Health = gewichteter Schnitt aus Fortschritt (40 %), Druck (30 %) und Strategie (30 %); die Farbe richtet sich nach der schwächsten Achse.
            Fortschritt misst erledigte Story Points gegen die verstrichene Zeit: je Prozentpunkt Rückstand 2 Punkte Abzug.
        </p>
    @endif

    </div>
    </x-planner::body>
</x-ui-page>
