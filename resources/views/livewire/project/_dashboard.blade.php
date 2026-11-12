{{-- Projekt-Überblick: in zehn Sekunden — wie steht es, was braucht Aufmerksamkeit, was kommt als Nächstes.
     Verdichtet die drei Achsen (Canvas = Zustand, Logbuch = Verlauf, Aufgaben = Arbeit), statt sie zu kopieren. --}}
@php
    $d = $dashboardData;

    // === Fortschritt: Story Points zuerst, Anzahl als Rückfall ===
    $openPoints = (int) $d['open_points'];
    $donePoints = (int) $d['done_points'];
    $totalPoints = $openPoints + $donePoints;
    $openCount = $d['open_count'];
    $doneCount = $d['done_count'];
    $totalCount = $openCount + $doneCount;
    $unestimated = (int) ($d['unestimated_count'] ?? 0);
    $pointsPercent = $totalPoints > 0 ? (int) round($donePoints / $totalPoints * 100) : null;
    $countPercent = $totalCount > 0 ? (int) round($doneCount / $totalCount * 100) : null;
    $progress = $pointsPercent ?? $countPercent;

    // === Zeitraum ===
    $plannedStart = $d['planned_start'];
    $plannedEnd = $d['planned_end'];
    $timePercent = null;
    $remainingDays = null;
    if ($plannedStart && $plannedEnd) {
        $totalDays = max(1, (int) $plannedStart->diffInDays($plannedEnd));
        $elapsed = (int) $plannedStart->diffInDays(now(), false);
        $remainingDays = (int) now()->startOfDay()->diffInDays($plannedEnd->copy()->startOfDay(), false);
        $timePercent = (int) max(0, min(100, round($elapsed / $totalDays * 100)));
    }

    // === Stunden & Budget ===
    $plannedHours = (float) $d['planned_hours'];
    $loggedHours = (float) $d['logged_hours'];
    $hoursPercent = $plannedHours > 0 ? (int) round($loggedHours / $plannedHours * 100) : null;
    $budgetAmount = (float) ($d['budget_amount'] ?? 0);
    $budgetUsed = (float) ($d['budget_used'] ?? 0);
    $budgetPercent = $budgetAmount > 0 ? (int) round($budgetUsed / $budgetAmount * 100) : null;
    $currency = $d['currency'];
    $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ' . $currency;

    // Verbrauch läuft dem Fortschritt davon? (mehr als 15 Punkte Abstand)
    $ahead = fn (?int $pct) => $pct !== null && $progress !== null && $pct - $progress > 15;

    // === Health (letzter Snapshot) ===
    $health = $d['health'];
    $healthWord = match ($health['color']) {
        'green' => 'Im Plan',
        'yellow' => 'Unter Beobachtung',
        'red' => 'Kritisch',
        default => 'Noch keine Health-Messung',
    };
    $healthTone = match ($health['color']) {
        'green' => 'var(--nx-success)',
        'yellow' => 'var(--nx-warning)',
        'red' => 'var(--nx-danger)',
        default => 'var(--nx-muted)',
    };
    $axisLabel = ['strategy' => 'Strategie', 'progress' => 'Fortschritt', 'burn' => 'Druck'][$health['worst_axis'] ?? ''] ?? null;

    // === Lage in einem Satz ===
    $facts = [];
    $facts[] = $totalPoints > 0 || $unestimated > 0
        ? "{$openPoints} SP offen" . ($unestimated ? " (+{$unestimated} ungeschätzt)" : '')
        : "{$openCount} Aufgaben offen";
    if ($progress !== null) {
        $facts[] = "{$progress} % erledigt";
    }
    if ($remainingDays !== null) {
        $facts[] = $remainingDays < 0 ? abs($remainingDays) . ' Tage über Frist' : ($remainingDays === 0 ? 'Frist heute' : "{$remainingDays} Tage bis Ende");
    }
    if ($budgetPercent !== null) {
        $facts[] = "Budget zu {$budgetPercent} % verbraucht";
    } elseif ($hoursPercent !== null) {
        $facts[] = "{$hoursPercent} % der Stunden verbraucht";
    }

    // === Ziel aus dem Canvas ===
    $goal = ($d['canvas_briefing']['project_goal']['entries'] ?? [])[0] ?? null;
    $goalText = $goal ? trim(strip_tags($goal['title'] ?: $goal['content'] ?? '')) : null;
    $canvasRoute = $d['canvas']['route'] ?? null;
    $canvasAnalysis = $d['canvas']['analysis'] ?? [];

    // === Braucht Aufmerksamkeit — nur, was Handeln verlangt ===
    $attention = [];
    foreach ($d['overdue_tasks']->take(4) as $t) {
        $attention[] = ['tone' => 'danger', 'icon' => 'heroicon-o-exclamation-triangle',
            'text' => $t['title'], 'meta' => $t['days_overdue'] . ' Tage überfällig' . ($t['assignee'] ? ' · ' . $t['assignee'] : ''),
            'url' => route('planner.tasks.show', $t['id'])];
    }
    if ($d['overdue_tasks']->count() > 4) {
        $attention[] = ['tone' => 'danger', 'icon' => 'heroicon-o-ellipsis-horizontal',
            'text' => '… und ' . ($d['overdue_tasks']->count() - 4) . ' weitere überfällige', 'meta' => null, 'url' => route('planner.projects.show', $project)];
    }
    foreach ($d['logbook']['risks']->take(3) as $r) {
        $attention[] = ['tone' => 'warning', 'icon' => 'heroicon-o-shield-exclamation',
            'text' => $r->title, 'meta' => 'Risiko · ' . $r->occurred_on?->format('d.m.'), 'url' => $d['logbook']['url']];
    }
    if ($remainingDays !== null && $remainingDays < 0 && $openCount > 0) {
        $attention[] = ['tone' => 'danger', 'icon' => 'heroicon-o-calendar',
            'text' => 'Geplantes Ende überschritten', 'meta' => 'seit ' . abs($remainingDays) . ' Tagen, ' . $openCount . ' Aufgaben offen', 'url' => null];
    }
    if ($budgetPercent !== null && $budgetPercent > 100) {
        $attention[] = ['tone' => 'danger', 'icon' => 'heroicon-o-banknotes',
            'text' => 'Budget überschritten', 'meta' => $money($budgetUsed) . ' von ' . $money($budgetAmount), 'url' => null];
    } elseif ($ahead($budgetPercent)) {
        $attention[] = ['tone' => 'warning', 'icon' => 'heroicon-o-banknotes',
            'text' => 'Budget läuft dem Fortschritt davon', 'meta' => "{$budgetPercent} % verbraucht bei {$progress} % erledigt", 'url' => null];
    }
    if ($hoursPercent !== null && $hoursPercent > 100) {
        $attention[] = ['tone' => 'danger', 'icon' => 'heroicon-o-clock',
            'text' => 'Mehr Stunden als geplant', 'meta' => round($loggedHours, 1) . ' h von ' . round($plannedHours, 1) . ' h', 'url' => null];
    }
    if ($unestimated > 0) {
        $attention[] = ['tone' => 'warning', 'icon' => 'heroicon-o-puzzle-piece',
            'text' => "{$unestimated} offene Aufgabe(n) ohne Schätzung", 'meta' => 'Komplexität unvollständig', 'url' => route('planner.projects.show', $project)];
    }
    if (! $d['canvas']) {
        $attention[] = ['tone' => 'warning', 'icon' => 'heroicon-o-squares-2x2',
            'text' => 'Noch kein Canvas', 'meta' => 'Ziel, Umfang, Beteiligte, Risiken festhalten', 'url' => null, 'action' => 'openCanvas'];
    } else {
        foreach (array_slice($canvasAnalysis['warnings'] ?? [], 0, 2) as $w) {
            $attention[] = ['tone' => 'warning', 'icon' => 'heroicon-o-squares-2x2', 'text' => $w, 'meta' => 'Canvas', 'url' => $canvasRoute];
        }
    }
    if ($d['logbook']['stale'] && ($d['logbook']['done_since'] ?? 0) > 0) {
        $attention[] = ['tone' => 'info', 'icon' => 'heroicon-o-book-open',
            'text' => $d['logbook']['days_since'] === null ? 'Logbuch noch leer' : 'Seit ' . $d['logbook']['days_since'] . ' Tagen kein Logbuch-Eintrag',
            'meta' => $d['logbook']['done_since'] . ' Aufgaben seitdem erledigt — festhalten, was erreicht wurde', 'url' => $d['logbook']['url']];
    }
    $toneColor = ['danger' => 'var(--nx-danger)', 'warning' => 'var(--nx-warning)', 'info' => 'var(--nx-info)'];

    $people = $d['team_members']->filter(fn ($m) => $m['open_tasks'] > 0)->sortByDesc(fn ($m) => [$m['open_points'], $m['open_tasks']])->values();
    $maxPeoplePoints = max(1, (int) $people->max('open_points'));

    $card = 'rounded-xl bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]';
    $h2 = 'text-[11px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0';
@endphp

<div class="max-w-6xl mx-auto w-full space-y-4">

    {{-- ═══ LAGE ═══ --}}
    <section class="{{ $card }} p-5">
        <div class="flex flex-wrap items-start gap-4">
            <a href="{{ $health['url'] }}" wire:navigate title="Health im Detail"
               class="shrink-0 w-16 h-16 rounded-full flex flex-col items-center justify-center border-4 hover:opacity-80 transition"
               style="border-color: {{ $healthTone }};">
                <span class="text-xl font-bold tabular-nums leading-none" style="color: {{ $healthTone }};">{{ $health['score'] !== null ? (int) round($health['score']) : '–' }}</span>
                <span class="text-[9px] text-[var(--nx-muted)] mt-0.5">Health</span>
            </a>
            <div class="flex-1 min-w-[14rem]">
                <div class="text-lg font-semibold text-[var(--nx-text)]" style="color: {{ $health['color'] ? $healthTone : 'inherit' }};">{{ $healthWord }}</div>
                <p class="text-sm text-[var(--nx-text)] mt-1 mb-0">{{ implode(' · ', $facts) }}</p>
                @if($axisLabel && $health['color'] !== 'green')
                    <p class="text-xs text-[var(--nx-muted)] mt-1 mb-0">Schwächste Achse: {{ $axisLabel }}</p>
                @endif
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-[color:var(--nx-line)] flex items-start gap-2 text-sm">
            @svg('heroicon-o-flag', 'w-4 h-4 mt-0.5 shrink-0 text-[var(--nx-muted)]')
            @if($goalText)
                <span class="flex-1 min-w-0 text-[var(--nx-text)]">{{ \Illuminate\Support\Str::limit($goalText, 220) }}</span>
                @if($canvasRoute)
                    <a href="{{ $canvasRoute }}" wire:navigate class="shrink-0 text-xs text-[var(--nx-muted)] hover:text-[var(--nx-text)]">Canvas →</a>
                @endif
            @elseif($canvasRoute)
                <a href="{{ $canvasRoute }}" wire:navigate class="flex-1 text-[var(--nx-muted)] hover:text-[var(--nx-text)]">Projektziel im Canvas noch nicht festgehalten →</a>
            @else
                <button type="button" wire:click="openCanvas" class="flex-1 text-left text-[var(--nx-muted)] hover:text-[var(--nx-text)]">Kein Canvas — jetzt das Projektziel festhalten →</button>
            @endif
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-start">

        {{-- ═══ LINKS: Aufmerksamkeit + Als Nächstes ═══ --}}
        <div class="lg:col-span-3 space-y-4">

            <section class="{{ $card }}">
                <div class="px-5 py-3 border-b border-[color:var(--nx-line)] flex items-center gap-2">
                    <h2 class="{{ $h2 }}">Braucht Aufmerksamkeit</h2>
                    @if(count($attention))
                        <span class="ml-auto text-xs tabular-nums text-[var(--nx-muted)]">{{ count($attention) }}</span>
                    @endif
                </div>
                @if(count($attention))
                    <ul class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($attention as $a)
                            @php $color = $toneColor[$a['tone']] ?? 'var(--nx-muted)'; @endphp
                            <li>
                                @if(! empty($a['action']))
                                    <button type="button" wire:click="{{ $a['action'] }}" class="w-full text-left flex items-start gap-3 px-5 py-2.5 hover:bg-[var(--nx-hover)] transition">
                                @elseif($a['url'])
                                    <a href="{{ $a['url'] }}" wire:navigate class="flex items-start gap-3 px-5 py-2.5 hover:bg-[var(--nx-hover)] transition">
                                @else
                                    <div class="flex items-start gap-3 px-5 py-2.5">
                                @endif
                                    <span class="shrink-0 mt-0.5" style="color: {{ $color }};">@svg($a['icon'], 'w-4 h-4')</span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-sm text-[var(--nx-text)] truncate">{{ $a['text'] }}</span>
                                        @if($a['meta'])
                                            <span class="block text-xs text-[var(--nx-muted)] truncate">{{ $a['meta'] }}</span>
                                        @endif
                                    </span>
                                @if(! empty($a['action']))
                                    </button>
                                @elseif($a['url'])
                                    </a>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="px-5 py-6 flex items-center gap-2 text-sm text-[var(--nx-success)]">
                        @svg('heroicon-o-check-circle', 'w-5 h-5')
                        Nichts Dringendes — weiterarbeiten.
                    </div>
                @endif
            </section>

            <section class="{{ $card }}">
                <div class="px-5 py-3 border-b border-[color:var(--nx-line)] flex items-center gap-2">
                    <h2 class="{{ $h2 }}">Als Nächstes</h2>
                    <a href="{{ route('planner.projects.show', $project) }}" wire:navigate class="ml-auto text-xs text-[var(--nx-muted)] hover:text-[var(--nx-text)]">Board →</a>
                </div>
                @if($d['next_tasks']->isNotEmpty())
                    <ul class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($d['next_tasks'] as $t)
                            <li>
                                <a href="{{ route('planner.tasks.show', $t['id']) }}" wire:navigate class="flex items-center gap-3 px-5 py-2.5 hover:bg-[var(--nx-hover)] transition">
                                    <span class="flex-1 min-w-0 text-sm text-[var(--nx-text)] truncate">
                                        @if($t['is_frog'])<span title="Frosch">🐸</span>@endif
                                        @if($t['high'])<span class="text-[var(--nx-danger)]" title="Hohe Priorität">!</span>@endif
                                        {{ $t['title'] }}
                                    </span>
                                    @if($t['assignee'])
                                        <span class="hidden sm:inline shrink-0 text-xs text-[var(--nx-muted)] truncate max-w-[8rem]">{{ $t['assignee'] }}</span>
                                    @endif
                                    @if($t['points'])
                                        <span class="shrink-0 text-xs tabular-nums text-[var(--nx-muted)]">{{ $t['points'] }} SP</span>
                                    @endif
                                    <span class="shrink-0 w-14 text-right text-xs tabular-nums {{ $t['due_date'] && $t['due_date']->isToday() ? 'text-[var(--nx-warning)] font-medium' : 'text-[var(--nx-muted)]' }}">
                                        {{ $t['due_date'] ? ($t['due_date']->isToday() ? 'heute' : $t['due_date']->format('d.m.')) : '' }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="px-5 py-6 text-sm text-[var(--nx-muted)] m-0">Keine offenen Aufgaben.</p>
                @endif
            </section>
        </div>

        {{-- ═══ RECHTS: Fortschritt + Wer trägt was ═══ --}}
        <div class="lg:col-span-2 space-y-4">

            <section class="{{ $card }} p-5 space-y-4">
                <h2 class="{{ $h2 }}">Fortschritt</h2>

                @php
                    $rows = [];
                    $rows[] = ['label' => 'Komplexität', 'pct' => $pointsPercent,
                        'value' => $totalPoints > 0 ? "{$donePoints} / {$totalPoints} SP" : 'nicht geschätzt',
                        'hint' => $unestimated ? "{$unestimated} ungeschätzt" : null, 'tone' => 'var(--nx-success)'];
                    $rows[] = ['label' => 'Aufgaben', 'pct' => $countPercent, 'value' => "{$doneCount} / {$totalCount}", 'hint' => null, 'tone' => 'var(--nx-success)'];
                    if ($timePercent !== null) {
                        $rows[] = ['label' => 'Zeitraum', 'pct' => $timePercent,
                            'value' => $plannedStart->format('d.m.') . ' – ' . $plannedEnd->format('d.m.y'),
                            'hint' => $ahead($timePercent) ? 'Zeit läuft schneller als der Fortschritt' : null,
                            'tone' => $remainingDays < 0 ? 'var(--nx-danger)' : ($ahead($timePercent) ? 'var(--nx-warning)' : 'var(--nx-info)')];
                    }
                    if ($hoursPercent !== null) {
                        $rows[] = ['label' => 'Stunden', 'pct' => min(100, $hoursPercent),
                            'value' => round($loggedHours, 1) . ' / ' . round($plannedHours, 1) . ' h',
                            'hint' => $hoursPercent > 100 ? "{$hoursPercent} % — über Plan" : null,
                            'tone' => $hoursPercent > 100 ? 'var(--nx-danger)' : ($ahead($hoursPercent) ? 'var(--nx-warning)' : 'var(--nx-info)')];
                    } elseif ($loggedHours > 0) {
                        $rows[] = ['label' => 'Stunden', 'pct' => null, 'value' => round($loggedHours, 1) . ' h erfasst', 'hint' => 'kein Plan hinterlegt', 'tone' => 'var(--nx-info)'];
                    }
                    if ($budgetPercent !== null) {
                        $rows[] = ['label' => 'Budget', 'pct' => min(100, $budgetPercent),
                            'value' => $money($budgetUsed) . ' / ' . $money($budgetAmount),
                            'hint' => $budgetPercent > 100 ? "{$budgetPercent} % — überschritten" : null,
                            'tone' => $budgetPercent > 100 ? 'var(--nx-danger)' : ($ahead($budgetPercent) ? 'var(--nx-warning)' : 'var(--nx-info)')];
                    }
                @endphp

                @foreach($rows as $row)
                    <div>
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="text-[var(--nx-text)]">{{ $row['label'] }}</span>
                            <span class="tabular-nums text-[var(--nx-muted)] text-xs">
                                {{ $row['value'] }}@if($row['pct'] !== null) · <span class="font-semibold text-[var(--nx-text)]">{{ $row['pct'] }} %</span>@endif
                            </span>
                        </div>
                        <div class="mt-1.5 h-1.5 rounded-full bg-[var(--nx-line)] overflow-hidden relative">
                            @if($row['pct'] !== null)
                                <div class="h-full rounded-full transition-all" style="width: {{ $row['pct'] }}%; background-color: {{ $row['tone'] }};"></div>
                            @endif
                            @if($progress !== null && $row['label'] !== 'Komplexität' && $row['label'] !== 'Aufgaben' && $row['pct'] !== null)
                                {{-- Marke: wo der Fortschritt steht — Verbrauch rechts davon = schneller als die Arbeit --}}
                                <span class="absolute top-[-2px] bottom-[-2px] w-0.5 bg-[var(--nx-text)]/60" style="left: {{ $progress }}%;" title="Fortschritt {{ $progress }} %"></span>
                            @endif
                        </div>
                        @if($row['hint'])
                            <div class="mt-1 text-xs" style="color: {{ str_contains($row['tone'], 'success') || str_contains($row['tone'], 'info') ? 'var(--nx-muted)' : $row['tone'] }};">{{ $row['hint'] }}</div>
                        @endif
                    </div>
                @endforeach

                @if($progress !== null && ($timePercent !== null || $hoursPercent !== null || $budgetPercent !== null))
                    <p class="text-[11px] text-[var(--nx-muted)] m-0">Der Strich markiert den Fortschritt ({{ $progress }} %). Liegt der Balken rechts davon, wird schneller verbraucht als erledigt.</p>
                @endif
            </section>

            @if($people->isNotEmpty())
                <section class="{{ $card }} p-5">
                    <h2 class="{{ $h2 }} mb-3">Wer trägt was</h2>
                    <ul class="space-y-2.5">
                        @foreach($people->take(6) as $m)
                            <li class="text-sm">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="truncate text-[var(--nx-text)]">{{ $m['name'] }}</span>
                                    <span class="shrink-0 tabular-nums text-xs text-[var(--nx-muted)]">
                                        @if($m['open_points'] > 0)<span class="font-semibold text-[var(--nx-text)]">{{ $m['open_points'] }} SP</span> · @endif{{ $m['open_tasks'] }} offen
                                    </span>
                                </div>
                                <div class="mt-1 h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                                    <div class="h-full rounded-full bg-[var(--nx-accent)]" style="width: {{ round($m['open_points'] / $maxPeoplePoints * 100) }}%;"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</div>
