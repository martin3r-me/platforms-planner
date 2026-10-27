{{--
    Planner-Actionbar — EINE Leiste für alle Planner-Seiten, feste Plätze:

      [Breadcrumb]  [Reiter der Ebene]  ·········  [Filter ▾] [+ Neu ▾] [⋯]

    Die Leiste ist auf jeder Seite identisch: keine Seiten-Badges/Kennzahlen hier —
    die gehören in <x-planner::statusbar> als erstes Element des Inhalts.

    • Ebene Planner:  Dashboard · Meine · Delegiert · Erledigt · Pflege · Portfolio
    • Ebene Projekt:  Überblick · Board · Canvas · Logbuch · Health  (sobald :project gesetzt)
    • Filter:  Inhalt der Seite (Slot "filters"); ohne Filter bleibt der Button sichtbar, aber inaktiv.
    • + Neu:   immer Aufgabe + Projekt (im Projekt zusätzlich Logbuch-Eintrag); Seiten ergänzen über Slot "create".
    • ⋯:       Seitenoptionen (Slot "more"); im Projekt immer Projekt-Einstellungen.

    Reiter = Textreiter mit Akzentstrich (kein Rahmen). Responsiv: Reiter < md als Dropdown, Button-Beschriftungen < sm nur Icons, Popover passen sich der Breite an.

    <x-planner::actionbar :project="$project" :crumbs="[['label' => $canvas->name]]" :filters-active="2">
        <x-slot:filters> … </x-slot:filters>
        <x-slot:create> <x-planner::menu-item icon="…" wire:click="…">…</x-planner::menu-item> </x-slot:create>
        <x-slot:more> … </x-slot:more>
    </x-planner::actionbar>
--}}
@props([
    'project' => null,
    'active' => null,
    'crumbs' => [],
    'filtersActive' => 0,
    'taskAction' => null,   // JS-Ausdruck für "Aufgabe"; Default: zentral anlegen + öffnen
])

@php
    $routeTabs = [
        'planner.dashboard' => 'dashboard',
        'planner.my-tasks' => 'my-tasks',
        'planner.delegated-tasks' => 'delegated',
        'planner.completed-tasks' => 'completed',
        'planner.hygiene' => 'hygiene',
        'planner.projects.cleanup' => 'hygiene',
        'planner.health-index' => 'portfolio',
        'planner.projects.overview' => 'overview',
        'planner.projects.show' => 'board',
        'planner.tasks.show' => $project ? 'board' : 'my-tasks',
        'planner.projects.canvas.index' => 'canvas',
        'planner.projects.canvas.show' => 'canvas',
        'planner.projects.logbook' => 'logbook',
        'planner.projects.health' => 'health',
    ];
    // Bei Livewire-Updates (wire:click …) ist request()->route() die Update-Route —
    // dann die Route der ursprünglichen Seite auflösen, sonst verliert die Leiste den aktiven Reiter.
    $route = request()->route();
    if (\Livewire\Livewire::isLivewireRequest()) {
        try {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create(\Livewire\Livewire::originalUrl(), 'GET'));
        } catch (\Throwable $e) {
            $route = null;
        }
    }
    $active ??= $routeTabs[$route?->getName()] ?? null;

    if ($project) {
        $nav = \Platform\Planner\Support\ProjectNavSummary::for($project);
        $healthDot = match ($nav['health']['color']) {
            'green' => 'bg-[color:var(--nx-success)]',
            'yellow' => 'bg-[color:var(--nx-warning)]',
            'red' => 'bg-[color:var(--nx-danger)]',
            default => null,
        };
        $tabs = [
            'overview' => ['href' => route('planner.projects.overview', $project), 'label' => 'Überblick', 'icon' => 'heroicon-o-presentation-chart-line',
                'title' => 'Überblick — Fortschritt, Verlauf, Risiken, Fälligkeiten'],
            'board' => ['href' => route('planner.projects.show', $project), 'label' => 'Board', 'icon' => 'heroicon-o-view-columns',
                'title' => 'Board — die Arbeit'],
            'canvas' => ['href' => $nav['canvas']['url'], 'label' => 'Canvas', 'icon' => 'heroicon-o-squares-2x2',
                'title' => 'Canvas — Kickoff und aktueller Stand'
                    . (! $nav['canvas']['kickoff_done'] ? ' · Kickoff offen' : '')
                    . ($nav['canvas']['completeness'] !== null ? ' · ' . $nav['canvas']['completeness'] . '% ausgefüllt' : ''),
                'meta' => ! $nav['canvas']['kickoff_done'] ? 'Kickoff' : ($nav['canvas']['completeness'] !== null ? $nav['canvas']['completeness'] . '%' : null),
                'warn' => ! $nav['canvas']['kickoff_done']],
            'logbook' => ['href' => $nav['logbook']['url'], 'label' => 'Logbuch', 'icon' => 'heroicon-o-book-open',
                'title' => 'Logbuch — Entscheidungen, Meilensteine, Risiken'
                    . ($nav['logbook']['has_entries'] ? ' · letzter Eintrag vor ' . $nav['logbook']['days_since_entry'] . ' Tagen' : ' · noch leer'),
                'meta' => $nav['logbook']['has_entries'] ? ($nav['logbook']['days_since_entry'] === 0 ? 'heute' : $nav['logbook']['days_since_entry'] . 'd') : null,
                'warn' => $nav['logbook']['stale'], 'count' => $nav['logbook']['open_risks']],
            'health' => ['href' => $nav['health']['url'], 'label' => 'Health', 'icon' => 'heroicon-o-heart',
                'title' => 'Health — das Lagebild' . ($nav['health']['score'] !== null ? ' · Index ' . $nav['health']['score'] : ''),
                'meta' => $nav['health']['score'] !== null ? (string) $nav['health']['score'] : null, 'dot' => $healthDot],
        ];
        $trail = array_merge(
            [['label' => 'Projekte', 'href' => route('planner.dashboard'), 'icon' => 'home']],
            [['label' => $project->name, 'href' => route('planner.projects.show', $project)]],
            $crumbs,
        );
    } else {
        $tabs = [
            'dashboard' => ['href' => route('planner.dashboard'), 'label' => 'Dashboard', 'icon' => 'heroicon-o-home'],
            'my-tasks' => ['href' => route('planner.my-tasks'), 'label' => 'Meine Aufgaben', 'short' => 'Meine', 'icon' => 'heroicon-o-clipboard-document-check'],
            'delegated' => ['href' => route('planner.delegated-tasks'), 'label' => 'Delegiert', 'icon' => 'heroicon-o-user-group'],
            'completed' => ['href' => route('planner.completed-tasks'), 'label' => 'Erledigt', 'icon' => 'heroicon-o-check-circle'],
            'hygiene' => ['href' => route('planner.hygiene'), 'label' => 'Pflege', 'icon' => 'heroicon-o-shield-check', 'title' => 'Pflege — was lange nicht angesehen wurde, aufräumen'],
            'portfolio' => ['href' => route('planner.health-index'), 'label' => 'Portfolio', 'icon' => 'heroicon-o-heart', 'title' => 'Portfolio — Health aller Projekte'],
        ];
        $trail = array_merge([['label' => 'Projekte', 'href' => route('planner.dashboard'), 'icon' => 'home']], $crumbs);
    }

    $activeTab = $tabs[$active] ?? null;
    $hasFilters = isset($filters) && trim((string) $filters) !== '';
    $canUpdate = $project ? auth()->user()?->can('update', $project) : true;
    $canSettings = $project ? auth()->user()?->can('settings', $project) : false;
    $taskAction ??= "\$dispatch('planner-create', { type: 'task', projectId: " . ($project?->id ?? 'null') . ' })';

    $iconBtn = 'inline-flex items-center justify-center gap-1.5 h-8 sm:h-7 min-w-8 sm:min-w-7 px-2 rounded-md text-xs font-medium transition-colors';
    $popover = 'absolute top-full mt-1.5 z-40 bg-[color:var(--nx-surface)] border border-[color:var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)]';
@endphp

<div x-data="{ open: null, toggle(m) { this.open = this.open === m ? null : m } }"
     @keydown.escape.window="open = null"
     class="relative shrink-0 z-30 h-12 sm:h-11 px-2 sm:px-4 bg-[color:var(--nx-surface)] border-b border-[color:var(--nx-line)]">
    <div class="h-full flex items-center gap-1.5 sm:gap-2">

        {{-- Wo bin ich --}}
        <div class="min-w-0 flex-1 md:flex-initial md:max-w-[34%] lg:max-w-[30%]">
            <x-ui-breadcrumb :items="$trail" />
        </div>

        {{-- Reiter ab md: ruhige Textreiter, aktiver Reiter mit Akzentstrich auf der Unterkante der Leiste --}}
        <span class="hidden md:block h-4 w-px bg-[color:var(--nx-line)] mx-1.5 shrink-0" aria-hidden="true"></span>
        <nav class="hidden md:flex self-stretch items-stretch shrink-0 -mb-px" aria-label="Bereiche">
            @foreach($tabs as $key => $tab)
                @php $isActive = $active === $key; @endphp
                <a href="{{ $tab['href'] }}" wire:navigate title="{{ $tab['title'] ?? $tab['label'] }}" @if($isActive) aria-current="page" @endif
                   class="group relative inline-flex items-center gap-1.5 px-2 lg:px-3 text-[12px] whitespace-nowrap transition-colors {{ $isActive ? 'text-[color:var(--nx-text)] font-semibold' : 'text-[color:var(--nx-muted)] font-medium hover:text-[color:var(--nx-text)]' }}">
                    @svg($tab['icon'], 'w-4 h-4 shrink-0 ' . ($isActive ? 'text-[color:var(--nx-accent)]' : 'opacity-70 group-hover:opacity-100'))
                    <span class="{{ $isActive ? '' : 'hidden lg:inline' }}">{{ $tab['label'] }}</span>
                    @if(! empty($tab['dot']))<span class="w-1.5 h-1.5 rounded-full {{ $tab['dot'] }}"></span>@endif
                    @if(($tab['meta'] ?? null) !== null)
                        <span class="hidden xl:inline tabular-nums text-[10px] font-normal text-[color:var(--nx-faint)]">{{ $tab['meta'] }}</span>
                    @endif
                    @if(($tab['count'] ?? 0) > 0)
                        <span class="inline-flex items-center justify-center min-w-[15px] h-[15px] px-1 rounded-full text-[9px] font-semibold bg-[color:var(--nx-danger)] text-white tabular-nums">{{ $tab['count'] }}</span>
                    @elseif(($tab['warn'] ?? false) && ! $isActive)
                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-warning)]"></span>
                    @endif
                    <span class="absolute inset-x-1.5 lg:inset-x-2 bottom-0 h-0.5 rounded-t-full transition-colors {{ $isActive ? 'bg-[color:var(--nx-accent)]' : 'bg-transparent group-hover:bg-[color:var(--nx-line-strong)]' }}" aria-hidden="true"></span>
                </a>
            @endforeach
        </nav>

        {{-- Reiter: unter md als Dropdown --}}
        <div class="relative md:hidden shrink-0">
            <button type="button" @click="toggle('tabs')" :aria-expanded="open === 'tabs'"
                    class="{{ $iconBtn }} font-semibold text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @if($activeTab) @svg($activeTab['icon'], 'w-4 h-4 text-[color:var(--nx-accent)]') <span class="max-w-[7rem] truncate">{{ $activeTab['short'] ?? $activeTab['label'] }}</span>
                @else @svg('heroicon-o-squares-2x2', 'w-4 h-4') @endif
                @svg('heroicon-o-chevron-down', 'w-3.5 h-3.5 text-[color:var(--nx-muted)]')
            </button>
            <div x-show="open === 'tabs'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 class="{{ $popover }} left-0 w-56 p-1">
                @foreach($tabs as $key => $tab)
                    <a href="{{ $tab['href'] }}" wire:navigate
                       class="flex items-center gap-2.5 px-3 py-2 rounded-md text-[13px] {{ $active === $key ? 'bg-[color:var(--nx-accent)]/10 text-[color:var(--nx-accent)] font-medium' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
                        @svg($tab['icon'], 'w-4 h-4 shrink-0')
                        <span class="flex-1">{{ $tab['label'] }}</span>
                        @if(! empty($tab['dot']))<span class="w-1.5 h-1.5 rounded-full {{ $tab['dot'] }}"></span>@endif
                        @if(($tab['count'] ?? 0) > 0)
                            <span class="min-w-[16px] h-4 px-1 rounded-full text-[10px] font-semibold bg-[color:var(--nx-danger)] text-white text-center tabular-nums">{{ $tab['count'] }}</span>
                        @elseif(($tab['meta'] ?? null) !== null)
                            <span class="text-[11px] tabular-nums text-[color:var(--nx-muted)]">{{ $tab['meta'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="hidden md:block flex-1"></div>

        {{-- Filter --}}
        <div class="relative shrink-0">
            <button type="button" @if($hasFilters) @click="toggle('filter')" @else disabled @endif
                    :aria-expanded="open === 'filter'"
                    title="{{ $hasFilters ? 'Filter' : 'Keine Filter auf dieser Seite' }}"
                    class="{{ $iconBtn }} {{ $hasFilters ? ($filtersActive > 0 ? 'text-[color:var(--nx-accent)] bg-[color:var(--nx-accent)]/10 hover:bg-[color:var(--nx-accent)]/15' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]') : 'text-[color:var(--nx-faint)] opacity-50 cursor-not-allowed' }}">
                @svg('heroicon-o-funnel', 'w-4 h-4')
                <span class="hidden sm:inline">Filter</span>
                @if($filtersActive > 0)
                    <span class="min-w-[16px] h-4 px-1 rounded-full text-[10px] font-semibold bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] tabular-nums leading-4 text-center">{{ $filtersActive }}</span>
                @endif
            </button>
            @if($hasFilters)
                <div x-show="open === 'filter'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                     class="{{ $popover }} right-0 w-[min(22rem,calc(100vw-1rem))] max-h-[min(70vh,36rem)] overflow-y-auto p-3 space-y-4">
                    {{ $filters }}
                </div>
            @endif
        </div>

        {{-- + Neu --}}
        <div class="relative shrink-0">
            <button type="button" @click="toggle('new')" :aria-expanded="open === 'new'" title="Neu anlegen"
                    class="{{ $iconBtn }} bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] hover:opacity-90">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span class="hidden sm:inline">Neu</span>
            </button>
            <div x-show="open === 'new'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 @click="if ($event.target.closest('[data-menu-item]')) open = null"
                 class="{{ $popover }} right-0 w-[min(15rem,calc(100vw-1rem))] p-1">
                @if($canUpdate)
                    <x-planner::menu-item icon="heroicon-o-check-circle" x-on:click="{{ $taskAction }}">
                        {{ $project ? 'Aufgabe im Projekt' : 'Aufgabe' }}
                    </x-planner::menu-item>
                @endif
                @if($project)
                    @if($canUpdate)
                        <x-planner::menu-item icon="heroicon-o-book-open" :href="route('planner.projects.logbook', $project) . '?neu=decision'">Logbuch-Eintrag</x-planner::menu-item>
                    @endif
                    <x-planner::menu-item icon="heroicon-o-user" x-on:click="$dispatch('planner-create', { type: 'task', projectId: null })">Persönliche Aufgabe</x-planner::menu-item>
                @endif
                @isset($create)
                    {{ $create }}
                @endisset
                <div class="my-1 border-t border-[color:var(--nx-line)]"></div>
                <x-planner::menu-item icon="heroicon-o-folder-plus" x-on:click="$dispatch('planner-create', { type: 'project' })" hint="startet mit Kickoff">Projekt</x-planner::menu-item>
            </div>
        </div>

        {{-- ⋯ --}}
        <div class="relative shrink-0">
            <button type="button" @click="toggle('more')" :aria-expanded="open === 'more'" title="Mehr"
                    class="{{ $iconBtn }} text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
            </button>
            <div x-show="open === 'more'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 @click="if ($event.target.closest('[data-menu-item]')) open = null"
                 class="{{ $popover }} right-0 w-[min(16rem,calc(100vw-1rem))] p-1">
                @isset($more)
                    {{ $more }}
                @endisset
                @if($project && $canSettings)
                    @isset($more)<div class="my-1 border-t border-[color:var(--nx-line)]"></div>@endisset
                    <x-planner::menu-item icon="heroicon-o-cog-6-tooth" x-on:click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })">Projekt-Einstellungen</x-planner::menu-item>
                @endif
                @if(! $project)
                    @isset($more)<div class="my-1 border-t border-[color:var(--nx-line)]"></div>@endisset
                    <x-planner::menu-item icon="heroicon-o-arrow-down-tray" :href="route('planner.export')">Export</x-planner::menu-item>
                @endif
            </div>
        </div>
    </div>

    @livewire('planner.quick-create', key('planner-quick-create'))
    @if($project && $canSettings && $route?->getName() !== 'planner.projects.show')
        @livewire('planner.project-settings-modal', key('planner-actionbar-settings-' . $project->id))
    @endif
</div>
