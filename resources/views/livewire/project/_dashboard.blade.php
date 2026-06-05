@php
    $d = $dashboardData;
    $totalCount = $d['total_count'];
    $doneCount = $d['done_count'];
    $openCount = $d['open_count'];
    $taskPercent = $totalCount > 0 ? round(($doneCount / $totalCount) * 100) : 0;

    $loggedHours = $d['logged_hours'];
    $plannedHours = $d['planned_hours'];
    $hoursPercent = $plannedHours > 0 ? min(100, round(($loggedHours / $plannedHours) * 100)) : 0;
    $hoursOver = $plannedHours > 0 && $loggedHours > $plannedHours;

    $budgetAmount = (float) ($d['budget_amount'] ?? 0);
    $budgetUsed = (float) ($d['budget_used'] ?? 0);
    $budgetPercent = $budgetAmount > 0 ? min(100, round(($budgetUsed / $budgetAmount) * 100)) : 0;
    $budgetOver = $budgetAmount > 0 && $budgetUsed > $budgetAmount;
    $currency = $d['currency'];

    $totalPoints = $d['open_points'] + $d['done_points'];

    // Timeline
    $plannedStart = $d['planned_start'];
    $plannedEnd = $d['planned_end'];
    $timelinePercent = 0;
    $totalDays = 0;
    $elapsedDays = 0;
    if ($plannedStart && $plannedEnd) {
        $totalDays = $plannedStart->diffInDays($plannedEnd);
        $elapsedDays = max(0, $plannedStart->diffInDays(now(), false));
        $timelinePercent = $totalDays > 0 ? min(100, round(($elapsedDays / $totalDays) * 100)) : 0;
    }
@endphp

<div class="p-6 space-y-6">

    {{-- Sektion 1: Projekt-Header --}}
    <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $project->name }}</h2>
                    @if($project->project_type)
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)] uppercase tracking-wide">
                            {{ $project->project_type->value }}
                        </span>
                    @endif
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded uppercase tracking-wide {{ $project->done ? 'bg-[var(--planner-status-done)]/10 text-[var(--planner-status-done)]' : 'bg-[var(--planner-status-active)]/10 text-[var(--planner-status-active)]' }}">
                        {{ $project->done ? 'Erledigt' : 'Offen' }}
                    </span>
                </div>
                <div class="flex items-center gap-4 text-xs text-[var(--ui-muted)]">
                    <span>Erstellt {{ $project->created_at->format('d.m.Y') }}</span>
                    @if($d['activities']->isNotEmpty())
                        <span>Letzte Aktivität {{ $d['activities']->first()->created_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if($plannedStart && $plannedEnd)
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-[var(--ui-muted)] mb-1">
                    <span>{{ $plannedStart->format('d.m.Y') }}</span>
                    <span>{{ $timelinePercent }}% — {{ $elapsedDays }} / {{ $totalDays }} Tage</span>
                    <span>{{ $plannedEnd->format('d.m.Y') }}</span>
                </div>
                <div class="w-full h-2 rounded-full bg-[var(--ui-muted-10)]">
                    <div class="h-2 rounded-full {{ $timelinePercent >= 100 ? 'bg-[var(--ui-danger)]' : 'bg-[var(--ui-primary)]' }} transition-all" style="width: {{ $timelinePercent }}%"></div>
                </div>
            </div>
        @endif
    </div>

    {{-- Sektion 2: KPI-Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Stunden --}}
        <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
            <div class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Stunden</div>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl font-bold {{ $hoursOver ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-secondary)]' }}">{{ number_format($loggedHours, 1, ',', '.') }}</span>
                @if($plannedHours > 0)
                    <span class="text-sm text-[var(--ui-muted)]">/ {{ number_format($plannedHours, 1, ',', '.') }}h</span>
                @endif
            </div>
            @if($plannedHours > 0)
                <div class="mt-2 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)]">
                    <div class="h-1.5 rounded-full transition-all {{ $hoursOver ? 'bg-[var(--ui-danger)]' : 'bg-[var(--ui-primary)]' }}" style="width: {{ min($hoursPercent, 100) }}%"></div>
                </div>
            @endif
            <div class="mt-2 flex items-center gap-3 text-[10px] text-[var(--ui-muted)]">
                <span>Abgerechnet: {{ number_format($d['billed_hours'], 1, ',', '.') }}h</span>
                <span>Offen: {{ number_format($d['unbilled_hours'], 1, ',', '.') }}h</span>
            </div>
        </div>

        {{-- Budget --}}
        <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
            <div class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Budget</div>
            @if($budgetAmount > 0)
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-bold {{ $budgetOver ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-secondary)]' }}">{{ number_format($budgetUsed, 0, ',', '.') }}</span>
                    <span class="text-sm text-[var(--ui-muted)]">/ {{ number_format($budgetAmount, 0, ',', '.') }} {{ $currency }}</span>
                </div>
                <div class="mt-2 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)]">
                    <div class="h-1.5 rounded-full transition-all {{ $budgetOver ? 'bg-[var(--ui-danger)]' : 'bg-[var(--ui-primary)]' }}" style="width: {{ min($budgetPercent, 100) }}%"></div>
                </div>
                <div class="mt-2 text-[10px] text-[var(--ui-muted)]">
                    Rest: {{ number_format(max(0, $budgetAmount - $budgetUsed), 0, ',', '.') }} {{ $currency }}
                </div>
            @else
                <div class="text-sm text-[var(--ui-muted)]">Kein Budget definiert</div>
            @endif
        </div>

        {{-- Aufgaben --}}
        <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
            <div class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Aufgaben</div>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $doneCount }}</span>
                <span class="text-sm text-[var(--ui-muted)]">/ {{ $totalCount }}</span>
                @if($totalCount > 0)
                    <span class="text-xs text-[var(--ui-muted)] ml-1">({{ $taskPercent }}%)</span>
                @endif
            </div>
            @if($totalCount > 0)
                <div class="mt-2 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)]">
                    <div class="h-1.5 rounded-full bg-[var(--ui-primary)] transition-all" style="width: {{ $taskPercent }}%"></div>
                </div>
            @endif
            <div class="mt-2 flex items-center gap-3 text-[10px]">
                <span class="text-[var(--planner-status-active)]">{{ $openCount }} offen</span>
                @if($d['overdue_tasks']->count() > 0)
                    <span class="text-[var(--ui-danger)] font-medium">{{ $d['overdue_tasks']->count() }} überfällig</span>
                @endif
            </div>
        </div>

        {{-- Story Points --}}
        <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
            <div class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Story Points</div>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $d['done_points'] }}</span>
                <span class="text-sm text-[var(--ui-muted)]">/ {{ $totalPoints }}</span>
            </div>
            @if($totalPoints > 0)
                <div class="mt-2 w-full h-1.5 rounded-full bg-[var(--ui-muted-10)]">
                    <div class="h-1.5 rounded-full bg-[var(--ui-primary)] transition-all" style="width: {{ round(($d['done_points'] / $totalPoints) * 100) }}%"></div>
                </div>
            @endif
            <div class="mt-2 text-[10px] text-[var(--ui-muted)]">
                {{ $d['open_points'] }} offen / {{ $d['done_points'] }} erledigt
            </div>
        </div>
    </div>

    {{-- Sektion 3: Zwei-Spalten-Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Links: 2/3 --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Aufgaben nach Spalte --}}
            <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Aufgaben nach Spalte</h3>
                @if($d['slots']->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($d['slots'] as $slot)
                            @php
                                $slotTotal = $slot->open_count + $slot->done_count;
                                $slotPercent = $slotTotal > 0 ? round(($slot->done_count / $slotTotal) * 100) : 0;
                            @endphp
                            <div class="flex items-center gap-3 text-xs py-1.5">
                                <span class="w-32 truncate font-medium text-[var(--ui-secondary)]">{{ $slot->name }}</span>
                                <span class="text-[var(--ui-muted)] w-16 text-right">{{ $slot->open_count }} offen</span>
                                <span class="text-[var(--ui-muted)] w-20 text-right">{{ $slot->done_count }} erledigt</span>
                                <div class="flex-1 h-1.5 rounded-full bg-[var(--ui-muted-10)]">
                                    <div class="h-1.5 rounded-full bg-[var(--ui-primary)] transition-all" style="width: {{ $slotPercent }}%"></div>
                                </div>
                                <span class="text-[var(--ui-muted)] w-10 text-right">{{ $slotPercent }}%</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-[var(--ui-muted)]">Keine Spalten vorhanden</p>
                @endif
            </div>

            {{-- Überfällige Aufgaben --}}
            @if($d['overdue_tasks']->isNotEmpty())
                <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
                    <h3 class="text-sm font-semibold text-[var(--ui-danger)] mb-3">Überfällige Aufgaben</h3>
                    <div class="space-y-2">
                        @foreach($d['overdue_tasks'] as $task)
                            <div class="flex items-center justify-between py-1.5 text-xs">
                                <div class="flex items-center gap-2 min-w-0">
                                    @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5 text-[var(--ui-danger)] flex-shrink-0')
                                    <span class="truncate text-[var(--ui-secondary)]">{{ $task['title'] }}</span>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0 ml-2">
                                    @if($task['assignee'])
                                        <span class="text-[var(--ui-muted)]">{{ $task['assignee'] }}</span>
                                    @endif
                                    <span class="text-[var(--ui-danger)] font-medium">{{ $task['days_overdue'] }}d</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Rechts: 1/3 --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Board Teaser --}}
            <button
                type="button"
                wire:click="$set('activeTab', 'board')"
                class="block w-full text-left bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4 hover:border-[var(--ui-primary)]/60 hover:shadow-sm transition-all group"
            >
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Board</h3>
                    <span class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors">
                        Öffnen
                        @svg('heroicon-o-arrow-right', 'w-3 h-3')
                    </span>
                </div>

                @if($d['slots']->isNotEmpty())
                    {{-- Mini-Spalten: pro Slot eine kleine Bar --}}
                    <div class="space-y-1.5">
                        @foreach($d['slots'] as $slot)
                            @php
                                $slotTotal = $slot->open_count + $slot->done_count;
                                $maxTotal = max($d['slots']->max(fn($s) => $s->open_count + $s->done_count), 1);
                                $widthPct = max(4, round(($slotTotal / $maxTotal) * 100));
                            @endphp
                            <div class="flex items-center gap-2 text-[11px]">
                                <span class="truncate text-[var(--ui-secondary)] flex-1 min-w-0">{{ $slot->label ?? $slot->name ?? 'Spalte' }}</span>
                                <span class="h-1.5 rounded-full bg-[var(--planner-track-fill)]/60 flex-shrink-0" style="width: {{ $widthPct * 0.6 }}px"></span>
                                <span class="tabular-nums text-[var(--ui-muted)] w-6 text-right">{{ $slotTotal }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-[var(--ui-muted)]">Noch keine Spalten</p>
                @endif

                <div class="mt-3 pt-3 border-t border-[var(--ui-border)]/40 flex items-center gap-4 text-[11px]">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-active)]"></span>
                        <span class="font-semibold tabular-nums">{{ $d['open_count'] }}</span>
                        <span class="text-[var(--ui-muted)]">offen</span>
                    </span>
                    @if($d['overdue_tasks']->count() > 0)
                        <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-overdue)]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[var(--planner-status-overdue)]"></span>
                            <span class="font-semibold tabular-nums">{{ $d['overdue_tasks']->count() }}</span>
                            <span>überfällig</span>
                        </span>
                    @endif
                </div>
            </button>

            {{-- Canvas --}}
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
                    class="block bg-[var(--ui-surface)] rounded-lg border-2 p-4 hover:shadow-md hover:-translate-y-px transition-all"
                    style="border-color: {{ $ct['border'] }};"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="inline-flex items-center gap-2">
                            @svg('heroicon-o-squares-2x2', 'w-4 h-4', ['style' => 'color: ' . $ct['color']])
                            <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Project Canvas</h3>
                        </div>
                        <span class="inline-flex items-center gap-1 text-[10px] font-medium" style="color: {{ $ct['color'] }};">
                            Öffnen
                            @svg('heroicon-o-arrow-right', 'w-3 h-3')
                        </span>
                    </div>

                    {{-- Big completeness display --}}
                    @if($completeness !== null)
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-2xl font-bold tabular-nums" style="color: {{ $ct['color'] }};">{{ $completeness }}%</span>
                            <span class="text-[11px] text-[var(--ui-muted)]">vollständig</span>
                            <span class="ml-auto inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded" style="background-color: {{ $ct['bg'] }}; color: {{ $ct['color'] }};">
                                {{ $ct['label'] }}
                            </span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-[var(--ui-muted-10)] overflow-hidden mb-3">
                            <div class="h-full rounded-full transition-all" style="width: {{ $completeness }}%; background-color: {{ $ct['color'] }};"></div>
                        </div>
                    @endif

                    {{-- Warnings preview --}}
                    @if(!empty($canvasWarnings))
                        <div class="space-y-1 pt-2 border-t border-[var(--ui-border)]/40">
                            @foreach(array_slice($canvasWarnings, 0, 3) as $warning)
                                <div class="text-[11px] flex items-start gap-1.5" style="color: {{ $ct['color'] }};">
                                    @svg('heroicon-o-exclamation-triangle', 'w-3 h-3 flex-shrink-0 mt-0.5 opacity-70')
                                    <span class="text-[var(--ui-secondary)] leading-snug">{{ $warning }}</span>
                                </div>
                            @endforeach
                            @if(count($canvasWarnings) > 3)
                                <div class="text-[10px] text-[var(--ui-muted)] pl-4.5">+ {{ count($canvasWarnings) - 3 }} weitere</div>
                            @endif
                        </div>
                    @endif
                </a>
            @else
                <button
                    type="button"
                    wire:click="openCanvas"
                    class="block w-full text-left bg-[var(--ui-surface)] rounded-lg border-2 border-dashed border-[var(--ui-border)] p-4 hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all group/canvas"
                >
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-muted)] group-hover/canvas:text-[var(--ui-primary)] transition-colors')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Project Canvas</h3>
                    </div>
                    <p class="text-[11px] text-[var(--ui-muted)] mb-3">Noch nicht angelegt — Strukturiere Ziele, Stakeholder und Annahmen an einem Ort.</p>
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-[var(--ui-primary)]">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                        Canvas anlegen
                    </span>
                </button>
            @endif

            {{-- Team --}}
            <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Team</h3>
                @if($d['team_members']->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($d['team_members'] as $member)
                            <div class="flex items-center justify-between text-xs py-1">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="truncate text-[var(--ui-secondary)]">{{ $member['name'] }}</span>
                                    @if($member['role'])
                                        <span class="text-[10px] text-[var(--ui-muted)]">({{ $member['role'] }})</span>
                                    @endif
                                </div>
                                <span class="text-[var(--ui-muted)] flex-shrink-0">{{ $member['open_tasks'] }} offen</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-[var(--ui-muted)]">Keine Mitglieder</p>
                @endif
            </div>

            {{-- Aktivitäten --}}
            <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] p-4">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Letzte Aktivitäten</h3>
                @if($d['activities']->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($d['activities'] as $activity)
                            <div class="text-xs py-1 border-b border-[var(--ui-border)]/30 last:border-0">
                                <div class="text-[var(--ui-secondary)]">{{ $activity->event ?? $activity->description ?? 'Aktivität' }}</div>
                                <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)] mt-0.5">
                                    @if($activity->user)
                                        <span>{{ $activity->user->name }}</span>
                                    @endif
                                    <span>{{ $activity->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-[var(--ui-muted)]">Keine Aktivitäten</p>
                @endif
            </div>
        </div>
    </div>
</div>
