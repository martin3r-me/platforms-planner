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

    $totalPoints = $d['open_points'] + $d['done_points'];

    // Timeline
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

    // Health-Status helper: gibt CSS color/bg/label zurück
    $health = function ($pct, $over = false) {
        if ($over) return ['color' => 'var(--planner-status-overdue)', 'bg' => 'rgba(239,68,68,0.10)', 'label' => 'Überzogen'];
        if ($pct === null) return ['color' => 'var(--ui-muted)', 'bg' => 'var(--ui-muted-5)', 'label' => '–'];
        if ($pct >= 90)   return ['color' => '#d97706', 'bg' => 'rgba(217,119,6,0.10)', 'label' => 'Knapp'];
        if ($pct >= 60)   return ['color' => 'var(--planner-status-active)', 'bg' => 'rgba(99,102,241,0.10)', 'label' => 'Auf Kurs'];
        return ['color' => 'var(--planner-status-done)', 'bg' => 'rgba(34,197,94,0.10)', 'label' => 'Komfort'];
    };

    $taskHealth   = $health($taskPercent);
    $hoursHealth  = $hoursOver ? $health(100, true) : $health($plannedHours > 0 ? $hoursPercent : null);
    $budgetHealth = $budgetOver ? $health(100, true) : $health($budgetAmount > 0 ? $budgetPercent : null);
    $timeHealth   = $isOverdue ? $health(100, true) : $health($plannedEnd ? $timelinePercent : null);

    // Slot-Heatmap
    $maxSlotTotal = $d['slots']->isNotEmpty()
        ? max(1, $d['slots']->max(fn($s) => $s->open_count + $s->done_count))
        : 1;

    // Team workload
    $maxTeamOpen = $d['team_members']->isNotEmpty()
        ? max(1, $d['team_members']->max(fn($m) => $m['open_tasks']))
        : 1;
@endphp

<div class="p-6 space-y-6 max-w-[1600px] mx-auto">

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- HERO: 4 Vital Signs — Aufgaben · Stunden · Budget · Zeit   --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

        {{-- Aufgaben --}}
        <div class="relative bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5 overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1" style="background-color: {{ $taskHealth['color'] }}"></div>
            <div class="flex items-center justify-between mb-3">
                <div class="inline-flex items-center gap-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                    @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                    <span>Aufgaben</span>
                </div>
                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded" style="background-color: {{ $taskHealth['bg'] }}; color: {{ $taskHealth['color'] }};">
                    {{ $taskPercent }}%
                </span>
            </div>
            <div class="flex items-baseline gap-1">
                <span class="text-3xl font-bold tabular-nums text-[var(--ui-secondary)]">{{ $doneCount }}</span>
                <span class="text-sm text-[var(--ui-muted)]">/ {{ $totalCount }}</span>
            </div>
            <div class="mt-3 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)] overflow-hidden">
                <div class="h-full rounded-full transition-all" style="width: {{ $taskPercent }}%; background-color: {{ $taskHealth['color'] }};"></div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px]">
                <span class="text-[var(--ui-muted)]"><span class="tabular-nums font-medium text-[var(--ui-secondary)]">{{ $openCount }}</span> offen</span>
                @if($overdueCount > 0)
                    <span class="inline-flex items-center gap-1 text-[var(--planner-status-overdue)] font-medium">
                        @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                        <span class="tabular-nums">{{ $overdueCount }}</span> überfällig
                    </span>
                @endif
            </div>
            @if($totalPoints > 0)
                <div class="mt-1 text-[10px] text-[var(--ui-muted)]">
                    {{ $d['done_points'] }} / {{ $totalPoints }} SP
                </div>
            @endif
        </div>

        {{-- Stunden --}}
        <div class="relative bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5 overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1" style="background-color: {{ $hoursHealth['color'] }}"></div>
            <div class="flex items-center justify-between mb-3">
                <div class="inline-flex items-center gap-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                    @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                    <span>Stunden</span>
                </div>
                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded" style="background-color: {{ $hoursHealth['bg'] }}; color: {{ $hoursHealth['color'] }};">
                    @if($plannedHours > 0) {{ $hoursPercent }}% @else – @endif
                </span>
            </div>
            <div class="flex items-baseline gap-1">
                <span class="text-3xl font-bold tabular-nums text-[var(--ui-secondary)]">{{ number_format($loggedHours, 1, ',', '.') }}</span>
                @if($plannedHours > 0)
                    <span class="text-sm text-[var(--ui-muted)]">/ {{ number_format($plannedHours, 1, ',', '.') }} h</span>
                @else
                    <span class="text-sm text-[var(--ui-muted)]">h</span>
                @endif
            </div>
            @if($plannedHours > 0)
                <div class="mt-3 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)] overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width: {{ min(100, $hoursPercent) }}%; background-color: {{ $hoursHealth['color'] }};"></div>
                </div>
            @else
                <div class="mt-3 h-1.5"></div>
            @endif
            <div class="mt-3 grid grid-cols-2 gap-2 text-[11px]">
                <div>
                    <div class="text-[var(--ui-muted)]">Abgerechnet</div>
                    <div class="tabular-nums font-medium text-[var(--ui-secondary)]">{{ number_format($d['billed_hours'], 1, ',', '.') }} h</div>
                </div>
                <div>
                    <div class="text-[var(--ui-muted)]">Offen</div>
                    <div class="tabular-nums font-medium text-[var(--ui-secondary)]">{{ number_format($d['unbilled_hours'], 1, ',', '.') }} h</div>
                </div>
            </div>
        </div>

        {{-- Budget --}}
        <div class="relative bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5 overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1" style="background-color: {{ $budgetHealth['color'] }}"></div>
            <div class="flex items-center justify-between mb-3">
                <div class="inline-flex items-center gap-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                    @svg('heroicon-o-banknotes', 'w-3.5 h-3.5')
                    <span>Budget</span>
                </div>
                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded" style="background-color: {{ $budgetHealth['bg'] }}; color: {{ $budgetHealth['color'] }};">
                    @if($budgetAmount > 0) {{ $budgetPercent }}% @else – @endif
                </span>
            </div>
            @if($budgetAmount > 0)
                <div class="flex items-baseline gap-1">
                    <span class="text-3xl font-bold tabular-nums text-[var(--ui-secondary)]">{{ number_format($budgetUsed, 0, ',', '.') }}</span>
                    <span class="text-sm text-[var(--ui-muted)]">/ {{ number_format($budgetAmount, 0, ',', '.') }} {{ $currency }}</span>
                </div>
                <div class="mt-3 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)] overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width: {{ min(100, $budgetPercent) }}%; background-color: {{ $budgetHealth['color'] }};"></div>
                </div>
                <div class="mt-3 text-[11px]">
                    <span class="text-[var(--ui-muted)]">Verfügbar</span>
                    <span class="tabular-nums font-medium {{ $budgetOver ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-secondary)]' }} ml-1">
                        {{ number_format(max(0, $budgetAmount - $budgetUsed), 0, ',', '.') }} {{ $currency }}
                    </span>
                    @if($d['hourly_rate'])
                        <span class="text-[var(--ui-muted)] ml-auto float-right">{{ number_format((float) $d['hourly_rate'], 0, ',', '.') }} {{ $currency }}/h</span>
                    @endif
                </div>
            @else
                <div class="text-sm text-[var(--ui-muted)]">Kein Budget definiert</div>
                <div class="mt-3 h-1.5"></div>
                <div class="mt-3 text-[11px] text-[var(--ui-muted)]">Im Settings-Modal hinzufügen</div>
            @endif
        </div>

        {{-- Zeit --}}
        <div class="relative bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5 overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1" style="background-color: {{ $timeHealth['color'] }}"></div>
            <div class="flex items-center justify-between mb-3">
                <div class="inline-flex items-center gap-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                    @svg('heroicon-o-calendar-days', 'w-3.5 h-3.5')
                    <span>Zeit</span>
                </div>
                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded" style="background-color: {{ $timeHealth['bg'] }}; color: {{ $timeHealth['color'] }};">
                    @if($plannedEnd) {{ $timelinePercent }}% @else – @endif
                </span>
            </div>
            @if($plannedEnd)
                <div class="flex items-baseline gap-1">
                    <span class="text-3xl font-bold tabular-nums {{ $isOverdue ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-secondary)]' }}">
                        {{ $isOverdue ? abs($remainingDays) : $remainingDays }}
                    </span>
                    <span class="text-sm text-[var(--ui-muted)]">{{ $isOverdue ? 'd überzogen' : 'd übrig' }}</span>
                </div>
                <div class="mt-3 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)] overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width: {{ $timelinePercent }}%; background-color: {{ $timeHealth['color'] }};"></div>
                </div>
                <div class="mt-3 flex items-center justify-between text-[11px] text-[var(--ui-muted)]">
                    <span>{{ $plannedStart->format('d.m.Y') }}</span>
                    <span class="tabular-nums">Tag {{ $elapsedDays }} / {{ $totalDays }}</span>
                    <span>{{ $plannedEnd->format('d.m.Y') }}</span>
                </div>
            @else
                <div class="text-sm text-[var(--ui-muted)]">Kein Zeitplan</div>
                <div class="mt-3 h-1.5"></div>
                <div class="mt-3 text-[11px] text-[var(--ui-muted)]">Start- und Enddatum setzen</div>
            @endif
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TIMELINE-BAND (nur wenn Start+Ende gesetzt)                --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if($plannedStart && $plannedEnd)
        <section class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Projekt-Timeline</h2>
                <span class="text-[11px] text-[var(--ui-muted)] tabular-nums">
                    {{ $plannedStart->format('d.m.Y') }} → {{ $plannedEnd->format('d.m.Y') }}
                </span>
            </div>
            <div class="relative h-8">
                {{-- Track --}}
                <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-2 rounded-full bg-[var(--ui-muted-10)]"></div>
                {{-- Elapsed --}}
                <div
                    class="absolute left-0 top-1/2 -translate-y-1/2 h-2 rounded-full transition-all"
                    style="width: {{ $timelinePercent }}%; background-color: {{ $timeHealth['color'] }};"
                ></div>
                {{-- Today marker --}}
                @if(!$isOverdue && $timelinePercent < 100)
                    <div
                        class="absolute top-0 bottom-0 flex flex-col items-center"
                        style="left: {{ $timelinePercent }}%; transform: translateX(-50%);"
                    >
                        <div class="w-3 h-3 rounded-full border-2 border-white shadow" style="background-color: {{ $timeHealth['color'] }}; margin-top: 10px;"></div>
                        <span class="mt-1 text-[10px] font-medium whitespace-nowrap" style="color: {{ $timeHealth['color'] }}">heute</span>
                    </div>
                @endif
                {{-- Start / End markers --}}
                <div class="absolute left-0 top-0 bottom-0 flex items-center">
                    <span class="w-1 h-3 rounded-full bg-[var(--ui-muted)]"></span>
                </div>
                <div class="absolute right-0 top-0 bottom-0 flex items-center">
                    <span class="w-1 h-3 rounded-full bg-[var(--ui-muted)]"></span>
                </div>
            </div>
            <div class="mt-2 flex items-center justify-between text-[11px] text-[var(--ui-muted)]">
                <span class="tabular-nums">Tag {{ $elapsedDays }} von {{ $totalDays }}</span>
                <span class="font-medium {{ $isOverdue ? 'text-[var(--planner-status-overdue)]' : 'text-[var(--ui-secondary)]' }}">
                    @if($isOverdue) {{ abs($remainingDays) }} Tage überzogen
                    @elseif($remainingDays === 0) Endet heute
                    @else {{ $remainingDays }} Tage übrig
                    @endif
                </span>
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- BOARD-HEATMAP (ersetzt slot-Tabelle + alten Board-Teaser)  --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <section class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Board-Übersicht</h2>
                <p class="text-[11px] text-[var(--ui-muted)] mt-0.5">Volumen pro Spalte — Klick öffnet das Board</p>
            </div>
            <button
                type="button"
                wire:click="$set('activeTab', 'board')"
                class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium rounded-md border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:border-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition-colors"
            >
                @svg('heroicon-o-view-columns', 'w-3.5 h-3.5')
                <span>Board öffnen</span>
                @svg('heroicon-o-arrow-right', 'w-3 h-3 opacity-60')
            </button>
        </div>

        @if($d['slots']->isNotEmpty())
            <div class="space-y-2.5">
                @foreach($d['slots'] as $slot)
                    @php
                        $slotTotal = $slot->open_count + $slot->done_count;
                        $slotPercent = $slotTotal > 0 ? round(($slot->done_count / $slotTotal) * 100) : 0;
                        $widthPct = max(2, round(($slotTotal / $maxSlotTotal) * 100));
                        $openPctOfBar = $slotTotal > 0 ? round(($slot->open_count / $slotTotal) * 100) : 0;
                    @endphp
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'board')"
                        class="block w-full text-left group/slot hover:bg-[var(--ui-muted-5)] rounded-md px-2 -mx-2 py-1.5 transition-colors"
                    >
                        <div class="flex items-center gap-3 text-[11px]">
                            <span class="w-28 truncate font-medium text-[var(--ui-secondary)] flex-shrink-0">{{ $slot->label ?? $slot->name ?? 'Spalte' }}</span>
                            <div class="flex-1 h-3 rounded-md bg-[var(--ui-muted-5)] overflow-hidden relative" style="max-width: {{ $widthPct }}%;">
                                <div class="absolute inset-y-0 left-0 bg-[var(--planner-status-active)]/70" style="width: {{ $openPctOfBar }}%;"></div>
                                <div class="absolute inset-y-0 right-0 bg-[var(--planner-status-done)]/60" style="width: {{ 100 - $openPctOfBar }}%;"></div>
                            </div>
                            <span class="tabular-nums text-[var(--ui-muted)] w-16 text-right flex-shrink-0">
                                <span class="font-semibold text-[var(--ui-secondary)]">{{ $slot->open_count }}</span> /
                                <span>{{ $slot->done_count }}</span>
                            </span>
                            <span class="tabular-nums text-[var(--ui-muted)] w-10 text-right flex-shrink-0">{{ $slotPercent }}%</span>
                        </div>
                    </button>
                @endforeach
            </div>
            <div class="mt-4 pt-3 border-t border-[var(--ui-border)]/40 flex items-center gap-4 text-[10px] text-[var(--ui-muted)]">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-sm bg-[var(--planner-status-active)]/70"></span>
                    offen
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-sm bg-[var(--planner-status-done)]/60"></span>
                    erledigt
                </span>
                <span class="ml-auto">Balkenlänge ∝ Gesamtvolumen je Spalte</span>
            </div>
        @else
            <div class="text-center py-6 text-[var(--ui-muted)] text-xs">
                @svg('heroicon-o-view-columns', 'w-8 h-8 mx-auto mb-2 opacity-30')
                Noch keine Spalten — lege welche im Board an.
            </div>
        @endif
    </section>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- RISIKEN (2/3) + TEAM-AUSLASTUNG (1/3)                      --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Risiken --}}
        <div class="lg:col-span-2 bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    @if($overdueCount > 0)
                        @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--planner-status-overdue)]')
                    @else
                        @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--planner-status-done)]')
                    @endif
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Risiken & Überfällig</h2>
                </div>
                @if($overdueCount > 0)
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded bg-[var(--planner-status-overdue)]/10 text-[var(--planner-status-overdue)]">
                        {{ $overdueCount }}
                    </span>
                @endif
            </div>

            @if($d['overdue_tasks']->isNotEmpty())
                <ul class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($d['overdue_tasks'] as $task)
                        @php
                            $days = $task['days_overdue'];
                            $severity = $days >= 14 ? 'critical' : ($days >= 7 ? 'high' : 'medium');
                            $sev = [
                                'critical' => ['color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.10)'],
                                'high'     => ['color' => 'var(--planner-status-overdue)', 'bg' => 'rgba(239,68,68,0.08)'],
                                'medium'   => ['color' => '#d97706', 'bg' => 'rgba(217,119,6,0.08)'],
                            ][$severity];
                            $assigneeInitial = $task['assignee'] ? mb_strtoupper(mb_substr($task['assignee'], 0, 1)) : '?';
                        @endphp
                        <li>
                            <a
                                href="{{ route('planner.tasks.show', $task['id']) }}?from=project"
                                wire:navigate
                                class="flex items-center gap-3 py-2.5 hover:bg-[var(--ui-muted-5)] rounded-md px-2 -mx-2 transition-colors group/task"
                            >
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-[11px] font-semibold flex-shrink-0" style="background-color: {{ $sev['bg'] }}; color: {{ $sev['color'] }};" title="{{ $task['assignee'] ?? 'Niemand zugewiesen' }}">
                                    {{ $assigneeInitial }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-medium text-[var(--ui-secondary)] truncate group-hover/task:text-[var(--ui-primary)] transition-colors">{{ $task['title'] }}</div>
                                    <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">
                                        Fällig {{ $task['due_date']->format('d.m.Y') }}
                                        @if($task['assignee']) · {{ $task['assignee'] }} @endif
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded flex-shrink-0 tabular-nums" style="background-color: {{ $sev['bg'] }}; color: {{ $sev['color'] }};">
                                    {{ $days }}d
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-center py-6 text-[var(--ui-muted)] text-xs">
                    @svg('heroicon-o-check-circle', 'w-8 h-8 mx-auto mb-2 opacity-30 text-[var(--planner-status-done)]')
                    Keine überfälligen Aufgaben — alles unter Kontrolle.
                </div>
            @endif
        </div>

        {{-- Team-Auslastung --}}
        <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-users', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Team-Auslastung</h2>
                </div>
                <span class="text-[10px] text-[var(--ui-muted)]">{{ $d['team_members']->count() }}</span>
            </div>

            @if($d['team_members']->isNotEmpty())
                <ul class="space-y-2.5">
                    @foreach($d['team_members']->sortByDesc('open_tasks') as $member)
                        @php
                            $loadPct = $maxTeamOpen > 0 ? round(($member['open_tasks'] / $maxTeamOpen) * 100) : 0;
                            $loadColor = $loadPct >= 80 ? '#d97706' : ($loadPct >= 40 ? 'var(--planner-status-active)' : 'var(--planner-status-done)');
                            $memberInitial = mb_strtoupper(mb_substr($member['name'], 0, 1));
                        @endphp
                        <li>
                            <div class="flex items-center gap-2 text-[11px]">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[var(--ui-secondary)] text-white text-[10px] font-semibold flex-shrink-0">{{ $memberInitial }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate text-[var(--ui-secondary)] font-medium">{{ $member['name'] }}</span>
                                        <span class="tabular-nums text-[var(--ui-muted)] flex-shrink-0">{{ $member['open_tasks'] }}</span>
                                    </div>
                                    <div class="mt-1 w-full h-1 rounded-full bg-[var(--ui-muted-10)] overflow-hidden">
                                        <div class="h-full rounded-full transition-all" style="width: {{ $loadPct }}%; background-color: {{ $loadColor }};"></div>
                                    </div>
                                    @if($member['role'])
                                        <div class="mt-0.5 text-[10px] text-[var(--ui-muted)] truncate">{{ $member['role'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-[var(--ui-muted)]">Keine Mitglieder</p>
            @endif
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- CANVAS (1/2) + AKTIVITÄT (1/2)                             --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Canvas (refined hero card) --}}
        @php
            $analysis = $d['canvas']['analysis'] ?? [];
            $completeness = $analysis['completeness_percent'] ?? null;
            $cStatus = $analysis['status'] ?? ($d['canvas'] ? 'unknown' : 'missing');
            $cTokens = [
                'green'   => ['color' => 'var(--planner-status-done)',    'bg' => 'rgba(34,197,94,0.08)',  'label' => 'OK',         'border' => 'rgba(34,197,94,0.30)'],
                'yellow'  => ['color' => '#d97706',                       'bg' => 'rgba(217,119,6,0.08)',  'label' => 'Lücken',     'border' => 'rgba(217,119,6,0.30)'],
                'red'     => ['color' => 'var(--planner-status-overdue)', 'bg' => 'rgba(239,68,68,0.08)',  'label' => 'Kritisch',   'border' => 'rgba(239,68,68,0.30)'],
                'missing' => ['color' => 'var(--ui-muted)',               'bg' => 'var(--ui-muted-5)',     'label' => 'Fehlt',      'border' => 'var(--ui-border)'],
                'unknown' => ['color' => 'var(--ui-muted)',               'bg' => 'var(--ui-muted-5)',     'label' => 'Unbekannt',  'border' => 'var(--ui-border)'],
            ];
            $ct = $cTokens[$cStatus] ?? $cTokens['unknown'];
            $canvasWarnings = $analysis['warnings'] ?? [];
        @endphp

        @if($d['canvas'])
            <a
                href="{{ $d['canvas']['route'] }}"
                wire:navigate
                class="block bg-[var(--ui-surface)] rounded-xl border-2 p-5 hover:shadow-md hover:-translate-y-px transition-all"
                style="border-color: {{ $ct['border'] }};"
            >
                <div class="flex items-center justify-between mb-4">
                    <div class="inline-flex items-center gap-2">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4', ['style' => 'color: ' . $ct['color']])
                        <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Project Canvas</h2>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium" style="color: {{ $ct['color'] }};">
                        Öffnen
                        @svg('heroicon-o-arrow-right', 'w-3 h-3')
                    </span>
                </div>

                @if($completeness !== null)
                    <div class="flex items-baseline gap-2 mb-2">
                        <span class="text-3xl font-bold tabular-nums" style="color: {{ $ct['color'] }};">{{ $completeness }}%</span>
                        <span class="text-xs text-[var(--ui-muted)]">vollständig</span>
                        <span class="ml-auto inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded" style="background-color: {{ $ct['bg'] }}; color: {{ $ct['color'] }};">
                            {{ $ct['label'] }}
                        </span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[var(--ui-muted-10)] overflow-hidden mb-4">
                        <div class="h-full rounded-full transition-all" style="width: {{ $completeness }}%; background-color: {{ $ct['color'] }};"></div>
                    </div>
                @endif

                @if(!empty($canvasWarnings))
                    <ul class="space-y-1.5 pt-3 border-t border-[var(--ui-border)]/40">
                        @foreach(array_slice($canvasWarnings, 0, 4) as $warning)
                            <li class="text-[11px] flex items-start gap-2 text-[var(--ui-secondary)]">
                                @svg('heroicon-o-exclamation-triangle', 'w-3 h-3 flex-shrink-0 mt-0.5 opacity-70', ['style' => 'color: ' . $ct['color']])
                                <span class="leading-snug">{{ $warning }}</span>
                            </li>
                        @endforeach
                        @if(count($canvasWarnings) > 4)
                            <li class="text-[10px] text-[var(--ui-muted)] pl-5">+ {{ count($canvasWarnings) - 4 }} weitere</li>
                        @endif
                    </ul>
                @endif
            </a>
        @else
            <button
                type="button"
                wire:click="openCanvas"
                class="block w-full text-left bg-[var(--ui-surface)] rounded-xl border-2 border-dashed border-[var(--ui-border)] p-5 hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all group/canvas"
            >
                <div class="flex items-center gap-2 mb-2">
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-muted)] group-hover/canvas:text-[var(--ui-primary)] transition-colors')
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Project Canvas</h2>
                </div>
                <p class="text-xs text-[var(--ui-muted)] mb-3">Strukturiere Ziele, Stakeholder und Annahmen an einem Ort.</p>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-[var(--ui-primary)]">
                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                    Canvas anlegen
                </span>
            </button>
        @endif

        {{-- Aktivität (Timeline-Style) --}}
        <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)] p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-bolt', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Aktivität</h2>
                </div>
                <span class="text-[10px] text-[var(--ui-muted)]">letzte {{ $d['activities']->count() }}</span>
            </div>

            @if($d['activities']->isNotEmpty())
                <ol class="relative space-y-3 pl-5">
                    {{-- vertical timeline rail --}}
                    <span class="absolute left-1.5 top-1 bottom-1 w-px bg-[var(--ui-border)]"></span>

                    @foreach($d['activities'] as $activity)
                        @php
                            $userName = $activity->user?->name ?? 'System';
                            $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
                            $eventLabel = $activity->event ?? $activity->description ?? 'Aktivität';
                        @endphp
                        <li class="relative">
                            <span class="absolute -left-[18px] top-1 inline-flex items-center justify-center w-3 h-3 rounded-full bg-[var(--ui-surface)] border-2 border-[var(--planner-status-active)]"></span>
                            <div class="text-[11px] text-[var(--ui-secondary)] leading-snug">{{ $eventLabel }}</div>
                            <div class="mt-0.5 flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                <span class="inline-flex items-center gap-1">
                                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-[var(--ui-secondary)] text-white text-[8px] font-semibold">{{ $userInitial }}</span>
                                    <span>{{ $userName }}</span>
                                </span>
                                <span>·</span>
                                <span title="{{ $activity->created_at->format('d.m.Y H:i') }}">{{ $activity->created_at->diffForHumans() }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="text-center py-6 text-[var(--ui-muted)] text-xs">
                    @svg('heroicon-o-bolt', 'w-8 h-8 mx-auto mb-2 opacity-30')
                    Noch keine Aktivität
                </div>
            @endif
        </div>
    </section>
</div>
