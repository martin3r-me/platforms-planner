@php
    $d = $dashboardData;

    // === KPIs ===
    $totalCount = $d['total_count'];
    $doneCount  = $d['done_count'];
    $openCount  = $d['open_count'];
    $overdueCount = $d['overdue_tasks']->count();
    $taskPercent = $totalCount > 0 ? round(($doneCount / $totalCount) * 100) : 0;

    $loggedHours  = $d['logged_hours'];
    $plannedHours = $d['planned_hours'];
    $hoursPercent = $plannedHours > 0 ? round(($loggedHours / $plannedHours) * 100) : 0;
    $hoursOver    = $plannedHours > 0 && $loggedHours > $plannedHours;

    $budgetAmount  = (float) ($d['budget_amount'] ?? 0);
    $budgetUsed    = (float) ($d['budget_used'] ?? 0);
    $budgetPercent = $budgetAmount > 0 ? round(($budgetUsed / $budgetAmount) * 100) : 0;
    $budgetOver    = $budgetAmount > 0 && $budgetUsed > $budgetAmount;
    $currency      = $d['currency'];

    $plannedStart = $d['planned_start'];
    $plannedEnd   = $d['planned_end'];
    $timelinePercent = 0;
    $totalDays = 0;
    $elapsedDays = 0;
    $remainingDays = 0;
    $isOverdue = false;
    if ($plannedStart && $plannedEnd) {
        $totalDays = max(1, (int) $plannedStart->diffInDays($plannedEnd));
        $elapsedDays = max(0, (int) $plannedStart->diffInDays(now(), false));
        $remainingDays = $totalDays - $elapsedDays;
        $isOverdue = $remainingDays < 0;
        $timelinePercent = min(100, round(($elapsedDays / $totalDays) * 100));
    }

    $health = function ($pct, $over = false) {
        if ($over) return ['color' => 'var(--nx-danger)', 'bg' => 'rgba(224,49,49,0.10)'];
        if ($pct === null) return ['color' => 'var(--nx-muted)', 'bg' => 'var(--nx-bg)'];
        if ($pct >= 90)   return ['color' => 'var(--nx-warning)', 'bg' => 'rgba(232,89,12,0.10)'];
        if ($pct >= 60)   return ['color' => 'var(--nx-info)', 'bg' => 'rgba(25,113,194,0.10)'];
        return ['color' => 'var(--nx-success)', 'bg' => 'rgba(47,158,68,0.10)'];
    };
    $taskHealth   = $health($taskPercent);
    $hoursHealth  = $hoursOver ? $health(100, true) : $health($plannedHours > 0 ? $hoursPercent : null);
    $budgetHealth = $budgetOver ? $health(100, true) : $health($budgetAmount > 0 ? $budgetPercent : null);
    $timeHealth   = $isOverdue ? $health(100, true) : $health($plannedEnd ? $timelinePercent : null);

    $maxTeamOpen = $d['team_members']->isNotEmpty()
        ? max(1, $d['team_members']->max(fn($m) => $m['open_tasks']))
        : 1;

    // Canvas briefing
    $briefing = $d['canvas_briefing'] ?? null;
    $canvasExists = (bool) $d['canvas'];
    $canvasRoute = $d['canvas']['route'] ?? null;
    $cAnalysis = $d['canvas']['analysis'] ?? [];
    $cCompleteness = $cAnalysis['completeness_percent'] ?? 0;
    $cStatus = $cAnalysis['status'] ?? ($canvasExists ? 'unknown' : 'missing');
    $cTokens = [
        'green'   => ['color' => 'var(--nx-success)',    'bg' => 'rgba(47,158,68,0.08)'],
        'yellow'  => ['color' => 'var(--nx-warning)',                       'bg' => 'rgba(232,89,12,0.08)'],
        'red'     => ['color' => 'var(--nx-danger)', 'bg' => 'rgba(224,49,49,0.08)'],
        'missing' => ['color' => 'var(--nx-muted)',               'bg' => 'var(--nx-bg)'],
        'unknown' => ['color' => 'var(--nx-muted)',               'bg' => 'var(--nx-bg)'],
    ];
    $ct = $cTokens[$cStatus] ?? $cTokens['unknown'];

    $goalEntries = $briefing['project_goal']['entries'] ?? [];
    $goalHeadline = $goalEntries[0] ?? null;

    $facetIcons = [
        'scope'        => 'heroicon-o-rectangle-group',
        'stakeholders' => 'heroicon-o-user-group',
        'milestones'   => 'heroicon-o-flag',
        'risks'        => 'heroicon-o-shield-exclamation',
    ];
    $facetOrder = ['scope', 'stakeholders', 'milestones', 'risks'];

    $snippet = function ($text, $len = 80) {
        if (!$text) return null;
        $text = trim(strip_tags($text));
        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
    };

    // Narrative
    $narrativeParts = [];
    if ($taskPercent > 0 || $totalCount > 0) $narrativeParts[] = "{$taskPercent}% erledigt";
    if ($plannedEnd) {
        if ($isOverdue) $narrativeParts[] = abs($remainingDays) . "d über Frist";
        elseif ($remainingDays === 0) $narrativeParts[] = "Frist heute";
        else $narrativeParts[] = "{$remainingDays}d übrig";
    }
    if ($overdueCount > 0) $narrativeParts[] = "{$overdueCount} überfällig";
    $narrative = !empty($narrativeParts) ? implode(' · ', $narrativeParts) : null;
@endphp

<div class="p-3 space-y-3 bg-[var(--nx-bg)]">

    {{-- ═══ 1. BRIEFING — Warum gibt es das Projekt? ═══ --}}
    <section class="p-4 rounded-xl bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] border border-[color:var(--nx-line)]" style="background: linear-gradient(to bottom, {{ $ct['bg'] }}, white);">
        <div class="flex items-center justify-between mb-2">
            <div class="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)]">
                @svg('heroicon-o-squares-2x2', 'w-3 h-3')
                <span>Briefing</span>
                @if($canvasExists)
                    <span class="text-[var(--nx-line-strong)]">·</span>
                    <span style="color: {{ $ct['color'] }};">{{ $cCompleteness }}%</span>
                @endif
            </div>
            @if($canvasRoute)
                <a href="{{ $canvasRoute }}" wire:navigate class="text-[10px] inline-flex items-center gap-0.5 hover:underline" style="color: {{ $ct['color'] }};">
                    Canvas öffnen
                    @svg('heroicon-o-arrow-right', 'w-3 h-3')
                </a>
            @endif
        </div>

        {{-- Goal headline --}}
        @if($goalHeadline)
            @if($goalHeadline['title'])
                <p class="text-sm font-semibold text-[var(--nx-text)] leading-snug m-0 mb-1">{{ $goalHeadline['title'] }}</p>
            @endif
            @if($goalHeadline['content'])
                <p class="text-[12px] text-[var(--nx-text)]/80 leading-relaxed m-0">{{ $snippet($goalHeadline['content'], 180) }}</p>
            @endif
            @if(($briefing['project_goal']['count'] ?? 0) > 1)
                <p class="text-[10px] text-[var(--nx-muted)] mt-1">+ {{ $briefing['project_goal']['count'] - 1 }} weitere Zielsetzung{{ ($briefing['project_goal']['count'] - 1) === 1 ? '' : 'en' }}</p>
            @endif
        @elseif($canvasExists)
            <div class="flex items-start gap-2 p-2.5 rounded-md text-[11px]" style="background-color: {{ $ct['bg'] }};">
                @svg('heroicon-o-information-circle', 'w-3.5 h-3.5 flex-shrink-0 mt-0.5', ['style' => 'color: ' . $ct['color']])
                <span class="text-[var(--nx-text)]">Noch kein Projekt-Ziel formuliert. Im Canvas-Block „Project Goal" festhalten.</span>
            </div>
        @else
            <p class="text-[12px] text-[var(--nx-muted)] mb-2">Im Canvas wird festgehalten, was das Projekt erreichen soll, wer beteiligt ist, und welche Risiken existieren.</p>
            <button type="button" wire:click="openCanvas" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-md bg-[var(--nx-accent)] text-white hover:bg-[var(--nx-accent)]/90 transition-colors">
                @svg('heroicon-o-plus', 'w-3 h-3')
                Canvas anlegen
            </button>
        @endif

        {{-- Strategic facets: 2x2 grid in der schmalen Spalte --}}
        @if($briefing && $canvasExists)
            <div class="grid grid-cols-2 gap-2 mt-3">
                @foreach($facetOrder as $facetKey)
                    @php
                        $facet = $briefing[$facetKey] ?? null;
                        $facetEntries = $facet['entries'] ?? [];
                        $facetCount = $facet['count'] ?? 0;
                    @endphp
                    <a
                        @if($canvasRoute) href="{{ $canvasRoute }}" wire:navigate @endif
                        class="block p-2.5 rounded-md border border-[color:var(--nx-line)] bg-[var(--nx-surface)] hover:border-[var(--nx-accent)]/40 hover:shadow-[var(--nx-shadow-card)] transition-all group/facet"
                    >
                        <div class="flex items-center gap-1.5 mb-1">
                            @svg($facetIcons[$facetKey], 'w-3 h-3 text-[var(--nx-muted)]')
                            <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0 truncate">{{ $facet['label'] ?? $facetKey }}</h3>
                            @if($facetCount > 0)
                                <span class="ml-auto tabular-nums text-[10px] font-semibold text-[var(--nx-text)]">{{ $facetCount }}</span>
                            @endif
                        </div>
                        @if(!empty($facetEntries))
                            <ul class="space-y-0.5">
                                @foreach(array_slice($facetEntries, 0, 2) as $entry)
                                    <li class="text-[10px] text-[var(--nx-text)] leading-snug truncate">
                                        @if($entry['title'])
                                            <span class="font-medium">{{ $snippet($entry['title'], 32) }}</span>
                                        @elseif($entry['content'])
                                            <span>{{ $snippet($entry['content'], 40) }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if($facetCount > 2)
                                <p class="mt-0.5 text-[9px] text-[var(--nx-muted)]">+{{ $facetCount - 2 }}</p>
                            @endif
                        @else
                            <p class="text-[10px] text-[var(--nx-muted)] italic">leer</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ 2. WIE WEIT? — Vital Signs kompakt ═══ --}}
    <section class="p-4 rounded-xl bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] border border-[color:var(--nx-line)]">
        <div class="flex items-baseline justify-between mb-3">
            <h2 class="text-xs font-semibold text-[var(--nx-text)] m-0">Wie weit sind wir?</h2>
            @if($narrative)
                <span class="text-[10px] text-[var(--nx-muted)]">{{ $narrative }}</span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-2">

            {{-- Aufgaben --}}
            <div class="rounded-md border border-[color:var(--nx-line)] p-2.5">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="inline-flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wider text-[var(--nx-muted)]">
                        @svg('heroicon-o-check-circle', 'w-3 h-3')
                        Aufgaben
                    </span>
                    <span class="text-[9px] font-semibold tabular-nums" style="color: {{ $taskHealth['color'] }};">{{ $taskPercent }}%</span>
                </div>
                <div class="flex items-baseline gap-1">
                    <span class="text-lg font-bold tabular-nums text-[var(--nx-text)]">{{ $doneCount }}</span>
                    <span class="text-[10px] text-[var(--nx-muted)]">/ {{ $totalCount }}</span>
                </div>
                <div class="mt-1.5 w-full h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width: {{ $taskPercent }}%; background-color: {{ $taskHealth['color'] }};"></div>
                </div>
                @if($overdueCount > 0)
                    <div class="mt-1.5 text-[10px] text-[var(--nx-danger)] font-medium inline-flex items-center gap-1">
                        @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                        {{ $overdueCount }} überfällig
                    </div>
                @endif
            </div>

            {{-- Stunden --}}
            <div class="rounded-md border border-[color:var(--nx-line)] p-2.5">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="inline-flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wider text-[var(--nx-muted)]">
                        @svg('heroicon-o-clock', 'w-3 h-3')
                        Stunden
                    </span>
                    <span class="text-[9px] font-semibold tabular-nums" style="color: {{ $hoursHealth['color'] }};">@if($plannedHours > 0){{ $hoursPercent }}%@else –@endif</span>
                </div>
                <div class="flex items-baseline gap-1">
                    <span class="text-lg font-bold tabular-nums text-[var(--nx-text)]">{{ number_format($loggedHours, 1, ',', '.') }}</span>
                    @if($plannedHours > 0)
                        <span class="text-[10px] text-[var(--nx-muted)]">/ {{ number_format($plannedHours, 1, ',', '.') }}h</span>
                    @else
                        <span class="text-[10px] text-[var(--nx-muted)]">h</span>
                    @endif
                </div>
                @if($plannedHours > 0)
                    <div class="mt-1.5 w-full h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: {{ min(100, $hoursPercent) }}%; background-color: {{ $hoursHealth['color'] }};"></div>
                    </div>
                @else
                    <div class="mt-1.5 h-1"></div>
                @endif
                <div class="mt-1.5 text-[10px] text-[var(--nx-muted)]">
                    {{ number_format($d['billed_hours'], 1, ',', '.') }}h abgerechnet
                </div>
            </div>

            {{-- Budget --}}
            <div class="rounded-md border border-[color:var(--nx-line)] p-2.5">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="inline-flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wider text-[var(--nx-muted)]">
                        @svg('heroicon-o-banknotes', 'w-3 h-3')
                        Budget
                    </span>
                    <span class="text-[9px] font-semibold tabular-nums" style="color: {{ $budgetHealth['color'] }};">@if($budgetAmount > 0){{ $budgetPercent }}%@else –@endif</span>
                </div>
                @if($budgetAmount > 0)
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-bold tabular-nums text-[var(--nx-text)]">{{ number_format($budgetUsed, 0, ',', '.') }}</span>
                        <span class="text-[10px] text-[var(--nx-muted)]">/ {{ number_format($budgetAmount, 0, ',', '.') }} {{ $currency }}</span>
                    </div>
                    <div class="mt-1.5 w-full h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: {{ min(100, $budgetPercent) }}%; background-color: {{ $budgetHealth['color'] }};"></div>
                    </div>
                    <div class="mt-1.5 text-[10px] text-[var(--nx-muted)]">
                        {{ number_format(max(0, $budgetAmount - $budgetUsed), 0, ',', '.') }} {{ $currency }} verfügbar
                    </div>
                @else
                    <div class="text-[11px] text-[var(--nx-muted)]">Nicht definiert</div>
                    <div class="mt-1.5 h-1"></div>
                @endif
            </div>

            {{-- Zeit --}}
            <div class="rounded-md border border-[color:var(--nx-line)] p-2.5">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="inline-flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wider text-[var(--nx-muted)]">
                        @svg('heroicon-o-calendar-days', 'w-3 h-3')
                        Zeit
                    </span>
                    <span class="text-[9px] font-semibold tabular-nums" style="color: {{ $timeHealth['color'] }};">@if($plannedEnd){{ $timelinePercent }}%@else –@endif</span>
                </div>
                @if($plannedEnd)
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-bold tabular-nums {{ $isOverdue ? 'text-[var(--nx-danger)]' : 'text-[var(--nx-text)]' }}">{{ $isOverdue ? abs($remainingDays) : $remainingDays }}</span>
                        <span class="text-[10px] text-[var(--nx-muted)]">{{ $isOverdue ? 'd über' : 'd übrig' }}</span>
                    </div>
                    <div class="mt-1.5 w-full h-1 rounded-full bg-[var(--nx-line)] overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: {{ $timelinePercent }}%; background-color: {{ $timeHealth['color'] }};"></div>
                    </div>
                    <div class="mt-1.5 text-[10px] text-[var(--nx-muted)] tabular-nums">
                        Tag {{ $elapsedDays }} / {{ $totalDays }}
                    </div>
                @else
                    <div class="text-[11px] text-[var(--nx-muted)]">Kein Zeitplan</div>
                    <div class="mt-1.5 h-1"></div>
                @endif
            </div>
        </div>
    </section>

    {{-- ═══ 3. RISIKEN ═══ --}}
    <section class="p-4 rounded-xl bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] border border-[color:var(--nx-line)]">
        <div class="flex items-center justify-between mb-3">
            <h2 class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--nx-text)] m-0">
                @if($overdueCount > 0)
                    @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5 text-[var(--nx-danger)]')
                @else
                    @svg('heroicon-o-check-circle', 'w-3.5 h-3.5 text-[var(--nx-success)]')
                @endif
                Risiken
            </h2>
            @if($overdueCount > 0)
                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded bg-[var(--nx-danger)]/10 text-[var(--nx-danger)]">{{ $overdueCount }}</span>
            @endif
        </div>

        @if($d['overdue_tasks']->isNotEmpty())
            <ul class="space-y-1.5">
                @foreach($d['overdue_tasks']->take(6) as $task)
                    @php
                        $days = $task['days_overdue'];
                        $severity = $days >= 14 ? 'critical' : ($days >= 7 ? 'high' : 'medium');
                        $sev = [
                            'critical' => ['color' => 'var(--nx-danger)', 'bg' => 'rgba(224,49,49,0.10)'],
                            'high'     => ['color' => 'var(--nx-danger)', 'bg' => 'rgba(224,49,49,0.08)'],
                            'medium'   => ['color' => 'var(--nx-warning)', 'bg' => 'rgba(232,89,12,0.08)'],
                        ][$severity];
                        $assigneeInitial = $task['assignee'] ? mb_strtoupper(mb_substr($task['assignee'], 0, 1)) : '?';
                    @endphp
                    <li>
                        <a href="{{ route('planner.tasks.show', $task['id']) }}?from=project" wire:navigate
                           class="flex items-center gap-2 p-1.5 -mx-1.5 rounded-md hover:bg-[var(--nx-bg)] transition-colors group/task">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-semibold flex-shrink-0" style="background-color: {{ $sev['bg'] }}; color: {{ $sev['color'] }};">
                                {{ $assigneeInitial }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[11px] font-medium text-[var(--nx-text)] truncate group-hover/task:text-[var(--nx-accent)]">{{ $task['title'] }}</div>
                                <div class="text-[9px] text-[var(--nx-muted)]">{{ $task['due_date']->format('d.m.') }} @if($task['assignee']) · {{ $task['assignee'] }} @endif</div>
                            </div>
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded flex-shrink-0 tabular-nums" style="background-color: {{ $sev['bg'] }}; color: {{ $sev['color'] }};">
                                {{ $days }}d
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if($overdueCount > 6)
                <p class="text-[10px] text-[var(--nx-muted)] mt-1.5">+ {{ $overdueCount - 6 }} weitere</p>
            @endif
        @else
            <div class="text-center py-4 text-[var(--nx-muted)] text-[11px]">
                @svg('heroicon-o-check-circle', 'w-6 h-6 mx-auto mb-1 opacity-30 text-[var(--nx-success)]')
                Alles unter Kontrolle
            </div>
        @endif
    </section>

    {{-- ═══ 4. TEAM-AUSLASTUNG ═══ --}}
    @if($d['team_members']->isNotEmpty())
        <section class="p-4 rounded-xl bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] border border-[color:var(--nx-line)]">
            <div class="flex items-center justify-between mb-3">
                <h2 class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--nx-text)] m-0">
                    @svg('heroicon-o-users', 'w-3.5 h-3.5 text-[var(--nx-muted)]')
                    Team-Auslastung
                </h2>
                <span class="text-[10px] text-[var(--nx-muted)]">{{ $d['team_members']->count() }}</span>
            </div>
            <ul class="space-y-2">
                @foreach($d['team_members']->sortByDesc('open_tasks')->take(6) as $member)
                    @php
                        $loadPct = $maxTeamOpen > 0 ? round(($member['open_tasks'] / $maxTeamOpen) * 100) : 0;
                        $loadColor = $loadPct >= 80 ? 'var(--nx-warning)' : ($loadPct >= 40 ? 'var(--nx-info)' : 'var(--nx-success)');
                        $memberInitial = mb_strtoupper(mb_substr($member['name'], 0, 1));
                    @endphp
                    <li class="flex items-center gap-2 text-[11px]">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--nx-text)] text-white text-[9px] font-semibold flex-shrink-0">{{ $memberInitial }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-[var(--nx-text)] font-medium">{{ $member['name'] }}</span>
                                <span class="tabular-nums text-[var(--nx-muted)] flex-shrink-0">{{ $member['open_tasks'] }}</span>
                            </div>
                            <div class="mt-0.5 w-full h-0.5 rounded-full bg-[var(--nx-line)] overflow-hidden">
                                <div class="h-full rounded-full transition-all" style="width: {{ $loadPct }}%; background-color: {{ $loadColor }};"></div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ═══ 5. AKTIVITÄT ═══ --}}
    @if($d['activities']->isNotEmpty())
        <section class="p-4 rounded-xl bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)] border border-[color:var(--nx-line)]">
            <h2 class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--nx-text)] m-0 mb-3">
                @svg('heroicon-o-bolt', 'w-3.5 h-3.5 text-[var(--nx-muted)]')
                Aktivität
            </h2>
            <ol class="relative space-y-2.5 pl-4">
                <span class="absolute left-[5px] top-1 bottom-1 w-px bg-[var(--nx-line-strong)]"></span>
                @foreach($d['activities']->take(5) as $activity)
                    @php
                        $userName = $activity->user?->name ?? 'System';
                        $eventLabel = $activity->event ?? $activity->description ?? 'Aktivität';
                    @endphp
                    <li class="relative">
                        <span class="absolute -left-[14px] top-0.5 inline-block w-2 h-2 rounded-full bg-[var(--nx-surface)] border-2 border-[var(--nx-info)]"></span>
                        <div class="text-[10px] text-[var(--nx-text)] leading-snug">{{ $eventLabel }}</div>
                        <div class="text-[9px] text-[var(--nx-muted)]">{{ $userName }} · {{ $activity->created_at->diffForHumans() }}</div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

</div>
