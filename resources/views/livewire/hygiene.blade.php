@php
    $totalStale = $staleProjectsCount + $staleTasksCount;
    $hygieneVariant = ($neverViewedProjectsCount > 0 || $staleOverdue > 0)
        ? 'danger'
        : ($totalStale > 0 ? 'warning' : 'success');
    $hygieneTitle = implode(' · ', array_filter([
        $staleProjectsCount . ' vergessene Projekte',
        $staleTasksCount . ' vergessene Aufgaben',
        $staleOverdue > 0 ? $staleOverdue . ' überfällig' : null,
        $staleSP > 0 ? $staleSP . ' SP vergessen' : null,
        $neverViewedProjectsCount > 0 ? $neverViewedProjectsCount . ' nie besucht' : null,
    ]));
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Pflege" icon="heroicon-o-shield-check" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Pflege'],
        ]">
            {{-- Scope-Umschalter: Meins (aktiv) ⟷ Team --}}
            <div class="inline-flex rounded-md border border-[color:var(--nx-line-strong)] overflow-hidden">
                <span class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]">
                    @svg('heroicon-o-user', 'w-3.5 h-3.5')
                    Meins
                </span>
                <a href="{{ route('planner.projects.cleanup') }}" wire:navigate
                   title="Team-Sicht (Aufräum-Cockpit)"
                   class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] border-l border-[color:var(--nx-line-strong)] transition-colors">
                    @svg('heroicon-o-user-group', 'w-3.5 h-3.5')
                    Team
                </a>
            </div>

            {{-- Pflege-Zustand als EIN Badge rechts (Projekt-Standard), health-getönt --}}
            <x-nx-badge :variant="$hygieneVariant" title="{{ $hygieneTitle }}">
                @svg('heroicon-o-shield-check', 'w-3 h-3')
                @if($totalStale === 0)
                    <span>sauber</span>
                @else
                    <span class="tabular-nums">{{ $staleProjectsCount }} Proj.</span>
                    <span class="opacity-40" aria-hidden="true">·</span>
                    <span class="tabular-nums">{{ $staleTasksCount }} Aufg.</span>
                    @if($staleOverdue > 0)
                        <span class="opacity-40" aria-hidden="true">·</span>
                        <span class="tabular-nums">{{ $staleOverdue }} überfällig</span>
                    @endif
                @endif
            </x-nx-badge>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" icon="heroicon-o-funnel" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--nx-bg)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--nx-text)] leading-relaxed m-0">
                        Was wurde lange nicht angesehen? Projekte gelten nach <strong>{{ $projectHygieneDays }}</strong> Tagen als vernachlässigt, Aufgaben nach <strong>{{ $taskHygieneDays }}</strong>.
                    </p>
                </section>

                {{-- ANSICHT --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Ansicht</h3>
                    <div class="inline-flex rounded-md border border-[color:var(--nx-line-strong)] overflow-hidden w-full">
                        <button
                            wire:click="$set('tab', 'stale')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 h-7 text-[11px] transition-colors {{ $tab === 'stale' ? 'bg-[var(--nx-danger)] text-white' : 'bg-transparent text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                        >
                            @svg('heroicon-o-archive-box-x-mark', 'w-3.5 h-3.5')
                            Vergessen
                        </button>
                        <button
                            wire:click="$set('tab', 'recent')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 h-7 text-[11px] border-l border-[color:var(--nx-line-strong)] transition-colors {{ $tab === 'recent' ? 'bg-[var(--nx-accent)] text-white' : 'bg-transparent text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                        >
                            @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                            Kürzlich
                        </button>
                    </div>
                </section>

                {{-- ENTITY-TYP --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Anzeigen</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button wire:click="$set('entityType', 'all')" class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $entityType === 'all' ? 'bg-[var(--nx-text)] text-white' : 'bg-[var(--nx-bg)] text-[var(--nx-text)] hover:bg-[var(--nx-line)]' }}">Alles</button>
                        <button wire:click="$set('entityType', 'projects')" class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $entityType === 'projects' ? 'bg-[var(--nx-text)] text-white' : 'bg-[var(--nx-bg)] text-[var(--nx-text)] hover:bg-[var(--nx-line)]' }}">Projekte</button>
                        <button wire:click="$set('entityType', 'tasks')" class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $entityType === 'tasks' ? 'bg-[var(--nx-text)] text-white' : 'bg-[var(--nx-bg)] text-[var(--nx-text)] hover:bg-[var(--nx-line)]' }}">Aufgaben</button>
                    </div>
                </section>

                {{-- PROJEKT-FILTER (nur Stale) --}}
                @if($tab === 'stale' && $availableProjects->isNotEmpty())
                    <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Projekt</h3>
                        <div class="space-y-0.5 max-h-48 overflow-y-auto">
                            <button
                                wire:click="$set('projectFilter', null)"
                                class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $projectFilter === null ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                            >Alle</button>
                            @foreach($availableProjects as $proj)
                                <button
                                    wire:click="$set('projectFilter', {{ $proj->id }})"
                                    class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors flex items-center gap-2 {{ $projectFilter == $proj->id ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                                >
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $proj->color ?? 'var(--nx-muted)' }};"></span>
                                    <span class="truncate">{{ $proj->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- ════════ RIGHT SIDEBAR: Aktivität ════════ --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivität" icon="heroicon-o-bolt" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4 bg-[var(--nx-bg)]">

                {{-- TAGES-STATS --}}
                <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Heute</h3>
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div>
                            <div class="text-xl font-bold tabular-nums text-[color:var(--nx-success)]">{{ $tasksDoneToday }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">erledigt</div>
                        </div>
                        <div>
                            <div class="text-xl font-bold tabular-nums text-[var(--nx-text)]">{{ $projectsViewedToday->count() }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">Projekte besucht</div>
                        </div>
                    </div>
                </section>

                {{-- HEUTE BESUCHTE PROJEKTE --}}
                @if($projectsViewedToday->isNotEmpty())
                    <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2 inline-flex items-center gap-1.5">
                            @svg('heroicon-o-folder-open', 'w-3 h-3')
                            <span>Heute besucht — Projekte</span>
                        </h3>
                        <ul class="space-y-1">
                            @foreach($projectsViewedToday as $proj)
                                <li>
                                    <a href="{{ route('planner.projects.show', $proj) }}"
                                       wire:navigate
                                       class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--nx-bg)] transition-colors group">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $proj->color ?? 'var(--nx-muted)' }};"></span>
                                        <span class="flex-1 min-w-0 text-[12px] text-[var(--nx-text)] truncate group-hover:text-[var(--nx-accent)]">{{ $proj->name }}</span>
                                        <span class="text-[10px] text-[var(--nx-muted)] flex-shrink-0">{{ $proj->last_viewed_at?->format('H:i') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- HEUTE BESUCHTE TASKS --}}
                @if($tasksViewedToday->isNotEmpty())
                    <section class="p-3 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)]">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2 inline-flex items-center gap-1.5">
                            @svg('heroicon-o-clipboard-document-check', 'w-3 h-3')
                            <span>Heute besucht — Aufgaben</span>
                        </h3>
                        <ul class="space-y-1">
                            @foreach($tasksViewedToday as $task)
                                <li>
                                    <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}?from=hygiene"
                                       wire:navigate
                                       class="flex items-start gap-2 px-2 py-1.5 rounded hover:bg-[var(--nx-bg)] transition-colors group">
                                        <span class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0 {{ $task->lifecycle_state === \Platform\Planner\Enums\TaskLifecycleState::COMPLETED ? 'bg-[color:var(--nx-success)]' : 'bg-[color:var(--nx-warning)]' }}"></span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-[12px] text-[var(--nx-text)] truncate group-hover:text-[var(--nx-accent)]">{{ $task->title }}</span>
                                            @if($task->project)
                                                <span class="block text-[10px] text-[var(--nx-muted)] truncate">{{ $task->project->name }}</span>
                                            @endif
                                        </span>
                                        <span class="text-[10px] text-[var(--nx-muted)] flex-shrink-0 mt-0.5">{{ $task->last_viewed_at?->format('H:i') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- EMPTY STATE wenn nichts heute passiert --}}
                @if($projectsViewedToday->isEmpty() && $tasksViewedToday->isEmpty() && $tasksDoneToday === 0)
                    <section class="p-4 rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] text-center">
                        @svg('heroicon-o-moon', 'w-6 h-6 mx-auto mb-1 text-[var(--nx-muted)] opacity-50')
                        <p class="text-[11px] text-[var(--nx-muted)] m-0">Heute noch nichts angesehen oder erledigt.</p>
                    </section>
                @endif

                {{-- PFLEGE-TIPP --}}
                <section class="p-3 rounded-lg bg-[var(--nx-accent)]/5 border border-[var(--nx-accent)]/20">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-accent)] mb-1.5 inline-flex items-center gap-1.5">
                        @svg('heroicon-o-light-bulb', 'w-3 h-3')
                        <span>Tipp</span>
                    </h3>
                    <p class="text-[11px] text-[var(--nx-text)] leading-relaxed m-0">
                        @if($staleProjectsCount > 5)
                            {{ $staleProjectsCount }} vergessene Projekte — vielleicht ein paar auf <strong>inaktiv</strong> setzen statt sie weiter mitzuschleifen.
                        @elseif($staleTasksCount > 10)
                            Viele alte Aufgaben — kurze Aufräum-Session: erledigt markieren oder löschen.
                        @else
                            Wenig Staub. Weiter so — kurze Tages-Sichtung hält die Hygiene niedrig.
                        @endif
                    </p>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto bg-[var(--nx-bg)]">
            <div class="p-6 space-y-6">

            @if($tab === 'stale')
                {{-- ========= VERGESSEN ========= --}}
                @if($staleProjectsCount === 0 && $staleTasksCount === 0)
                    <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] p-12 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--nx-success)]/10 mb-3">
                            @svg('heroicon-o-shield-check', 'w-7 h-7 text-[var(--nx-success)]')
                        </div>
                        <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 mb-1">Alles aufgeräumt</h3>
                        <p class="text-sm text-[var(--nx-muted)] m-0">Alle Projekte und Aufgaben wurden kürzlich besucht.</p>
                    </div>
                @else
                    {{-- Stale Projects --}}
                    @if(($entityType === 'all' || $entityType === 'projects') && $staleProjects->isNotEmpty())
                        <section>
                            <div class="flex items-center gap-2 mb-2 px-1">
                                <h2 class="text-sm font-semibold text-[var(--nx-danger)] m-0 inline-flex items-center gap-1.5">
                                    @svg('heroicon-o-archive-box-x-mark', 'w-4 h-4')
                                    Vergessene Projekte
                                </h2>
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-[var(--nx-danger)]/10 text-[var(--nx-danger)]">{{ $staleProjectsCount }}</span>
                            </div>
                            <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                                @foreach($staleProjects as $i => $project)
                                    @php
                                        $daysSince = $project->last_viewed_at ? (int) now()->diffInDays($project->last_viewed_at) : null;
                                        $neverViewed = $project->last_viewed_at === null;
                                        $pColor = $project->color ?? null;
                                        $severity = $neverViewed || $daysSince >= 30 ? 'critical' : ($daysSince >= 14 ? 'high' : 'medium');
                                        $edgeColor = match($severity) {
                                            'critical' => 'var(--nx-danger)',
                                            'high'     => 'var(--nx-warning)',
                                            default    => 'var(--nx-muted)',
                                        };
                                    @endphp
                                    <a href="{{ route('planner.projects.show', ['plannerProject' => $project->id]) }}" wire:navigate
                                       class="relative flex items-center gap-3 pl-5 pr-4 py-3 hover:bg-[var(--nx-bg)] transition-colors group {{ $i > 0 ? 'border-t border-[color:var(--nx-line)]' : '' }}">
                                        <span class="absolute top-2.5 bottom-2.5 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>

                                        @if($pColor)
                                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $pColor }}"></span>
                                        @else
                                            @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--nx-muted)] flex-shrink-0')
                                        @endif

                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-semibold text-[var(--nx-text)] truncate group-hover:text-[var(--nx-danger)]">{{ $project->name }}</div>
                                            <div class="flex items-center gap-3 text-[10px] text-[var(--nx-muted)] mt-0.5">
                                                <span class="tabular-nums">{{ $project->open_tasks_count }} offen / {{ $project->total_tasks_count }} gesamt</span>
                                                @if($project->open_tasks_count === 0 && $project->total_tasks_count > 0)
                                                    <span class="text-[var(--nx-success)] font-medium">Alle erledigt</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if($neverViewed)
                                            <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full text-white" style="background-color: {{ $edgeColor }};">
                                                @svg('heroicon-o-eye-slash', 'w-3 h-3')
                                                Nie
                                            </span>
                                        @elseif($daysSince !== null)
                                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums" style="background-color: color-mix(in srgb, {{ $edgeColor }} 14%, var(--nx-surface)); color: {{ $edgeColor }};">{{ $daysSince }}d</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- Stale Tasks --}}
                    @if(($entityType === 'all' || $entityType === 'tasks') && $staleTasks->isNotEmpty())
                        @php $groupedStaleTasks = $staleTasks->groupBy(fn($t) => $t->project?->name ?? 'Ohne Projekt'); @endphp
                        <section>
                            <div class="flex items-center gap-2 mb-2 px-1">
                                <h2 class="text-sm font-semibold text-[color:var(--nx-warning)] m-0 inline-flex items-center gap-1.5">
                                    @svg('heroicon-o-clock', 'w-4 h-4')
                                    Vergessene Aufgaben
                                </h2>
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-semibold rounded-full bg-[var(--nx-warning)]/10 text-[color:var(--nx-warning)]">{{ $staleTasksCount }}</span>
                            </div>
                            <div class="space-y-4">
                                @foreach($groupedStaleTasks as $projectName => $tasks)
                                    <div>
                                        <div class="flex items-center gap-2 mb-1.5 px-1">
                                            <span class="text-xs font-medium text-[var(--nx-text)]">{{ $projectName }}</span>
                                            <span class="text-[10px] text-[var(--nx-muted)] tabular-nums">{{ $tasks->count() }}</span>
                                        </div>
                                        <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                                            @foreach($tasks as $i => $task)
                                                @php
                                                    $daysSince = $task->last_viewed_at ? (int) now()->diffInDays($task->last_viewed_at) : null;
                                                    $neverViewed = $task->last_viewed_at === null;
                                                    $isOverdue = $task->due_date && $task->due_date->isPast();
                                                    $priorityColor = $task->priority?->color() ?? 'var(--nx-muted)';
                                                    $edgeColor = $isOverdue
                                                        ? 'var(--nx-danger)'
                                                        : ($neverViewed ? 'var(--nx-warning)' : $priorityColor);
                                                @endphp
                                                <div class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--nx-bg)] transition-colors group {{ $i > 0 ? 'border-t border-[color:var(--nx-line)]' : '' }}">
                                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></span>

                                                    <button
                                                        type="button"
                                                        x-data="{ press: null }"
                                                        @mousedown.stop="press = { x: $event.clientX, y: $event.clientY }"
                                                        @click.stop.prevent="
                                                            const ok = press && Math.abs($event.clientX - press.x) < 5 && Math.abs($event.clientY - press.y) < 5;
                                                            press = null;
                                                            if (ok) $wire.quickToggleDone({{ $task->id }});
                                                        "
                                                        class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors border-[color:var(--nx-line-strong)] text-transparent hover:border-[var(--nx-success)] hover:text-[var(--nx-success)] cursor-pointer"
                                                        title="Als erledigt markieren"
                                                    >
                                                        @svg('heroicon-s-check', 'w-3 h-3')
                                                    </button>

                                                    <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}?from=hygiene" wire:navigate class="flex-1 min-w-0">
                                                        <span class="text-sm font-medium text-[var(--nx-text)] truncate block group-hover:text-[color:var(--nx-warning)]">{{ $task->title }}</span>
                                                        <div class="flex items-center gap-2 text-[10px] text-[var(--nx-muted)] mt-0.5">
                                                            @if($task->userInCharge)
                                                                <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span>
                                                            @endif
                                                            @if($task->due_date)
                                                                <span class="{{ $isOverdue ? 'text-[var(--nx-danger)] font-semibold' : '' }} tabular-nums">{{ $task->due_date->format('d.m.Y') }}</span>
                                                            @endif
                                                        </div>
                                                    </a>

                                                    @if($isOverdue)
                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--nx-danger)]/10 text-[var(--nx-danger)]">überfällig</span>
                                                    @endif
                                                    @if($neverViewed)
                                                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-[color:var(--nx-warning)] text-white">
                                                            @svg('heroicon-o-eye-slash', 'w-3 h-3')
                                                            Nie
                                                        </span>
                                                    @elseif($daysSince !== null)
                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full tabular-nums bg-[var(--nx-warning)]/10 text-[color:var(--nx-warning)]">{{ $daysSince }}d</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif

            @else
                {{-- ========= KÜRZLICH ========= --}}

                @if(($entityType === 'all' || $entityType === 'projects') && $recentProjects->isNotEmpty())
                    <section>
                        <div class="flex items-center gap-2 mb-2 px-1">
                            <h2 class="text-sm font-semibold text-[var(--nx-text)] m-0 inline-flex items-center gap-1.5">
                                @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--nx-accent)]')
                                Kürzlich besucht — Projekte
                            </h2>
                            <span class="text-[10px] text-[var(--nx-muted)]">letzte 14 Tage</span>
                        </div>
                        <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                            @foreach($recentProjects as $i => $project)
                                @php $pColor = $project->color ?? null; @endphp
                                <a href="{{ route('planner.projects.show', ['plannerProject' => $project->id]) }}" wire:navigate
                                   class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--nx-bg)] transition-colors group {{ $i > 0 ? 'border-t border-[color:var(--nx-line)]' : '' }}">
                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $pColor ?? 'var(--nx-accent)' }};"></span>

                                    @if($pColor)
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $pColor }}"></span>
                                    @else
                                        @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--nx-muted)] flex-shrink-0')
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-[var(--nx-text)] truncate block">{{ $project->name }}</span>
                                        <span class="text-[10px] text-[var(--nx-muted)] tabular-nums">{{ $project->open_tasks_count }} offen</span>
                                    </div>
                                    <span class="flex-shrink-0 text-[10px] text-[var(--nx-muted)]">{{ $project->last_viewed_at->diffForHumans() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(($entityType === 'all' || $entityType === 'tasks') && $recentTasks->isNotEmpty())
                    <section>
                        <div class="flex items-center gap-2 mb-2 px-1">
                            <h2 class="text-sm font-semibold text-[var(--nx-text)] m-0 inline-flex items-center gap-1.5">
                                @svg('heroicon-o-clipboard-document', 'w-4 h-4 text-[var(--nx-accent)]')
                                Kürzlich besucht — Aufgaben
                            </h2>
                            <span class="text-[10px] text-[var(--nx-muted)]">letzte 7 Tage</span>
                        </div>
                        <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                            @foreach($recentTasks as $i => $task)
                                @php
                                    $priorityColor = match($task->priority?->value ?? null) {
                                        'high'   => 'var(--nx-danger)',
                                        'normal' => 'var(--nx-accent)',
                                        'low'    => 'var(--nx-muted)',
                                        default  => 'var(--nx-accent)',
                                    };
                                @endphp
                                <a href="{{ route('planner.tasks.show', ['plannerTask' => $task->id]) }}?from=hygiene" wire:navigate
                                   class="relative flex items-center gap-3 pl-5 pr-4 py-2.5 hover:bg-[var(--nx-bg)] transition-colors group {{ $i > 0 ? 'border-t border-[color:var(--nx-line)]' : '' }}">
                                    <span class="absolute top-2 bottom-2 left-1.5 w-[3px] rounded-full" style="background-color: {{ $priorityColor }};"></span>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-[var(--nx-text)] truncate block">{{ $task->title }}</span>
                                        <div class="flex items-center gap-2 text-[10px] text-[var(--nx-muted)] mt-0.5">
                                            @if($task->project)
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--nx-muted)' }};"></span>
                                                    {{ $task->project->name }}
                                                </span>
                                            @endif
                                            @if($task->userInCharge)
                                                <span>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="flex-shrink-0 text-[10px] text-[var(--nx-muted)]">{{ $task->last_viewed_at->diffForHumans() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(($entityType === 'all' || $entityType === 'projects') && $recentProjects->isEmpty() && ($entityType === 'all' || $entityType === 'tasks') && $recentTasks->isEmpty())
                    <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] p-12 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--nx-bg)] mb-3">
                            @svg('heroicon-o-eye', 'w-7 h-7 text-[var(--nx-muted)]')
                        </div>
                        <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 mb-1">Nichts Kürzliches</h3>
                        <p class="text-sm text-[var(--nx-muted)] m-0">Keine kürzlich besuchten Projekte oder Aufgaben.</p>
                    </div>
                @endif

            @endif

            </div>
        </div>
    </div>
</x-ui-page>
