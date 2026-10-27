{{-- Projekt-Reiter: Board · Canvas · Logbuch · Health — die drei Achsen
     (Arbeit · Zustand · Verlauf) plus Lagebild, auf jeder Projektseite gleich.
     @include('planner::livewire.partials.project-tabs', ['project' => $project, 'active' => 'board'|'canvas'|'logbook'|'health']) --}}
@php
    $nav = \Platform\Planner\Support\ProjectNavSummary::for($project);
    $healthDot = match($nav['health']['color']) {
        'green' => 'bg-[color:var(--nx-success)]',
        'yellow' => 'bg-[color:var(--nx-warning)]',
        'red' => 'bg-[color:var(--nx-danger)]',
        default => null,
    };
    $projectTabs = [
        'board' => [
            'href' => route('planner.projects.show', $project),
            'label' => 'Board',
            'icon' => 'heroicon-o-view-columns',
            'title' => 'Aufgaben — die Arbeit',
            'meta' => null,
        ],
        'canvas' => [
            'href' => $nav['canvas']['url'],
            'label' => 'Canvas',
            'icon' => 'heroicon-o-squares-2x2',
            'title' => 'Canvas — der aktuelle Stand (wird überschrieben)'
                . ($nav['canvas']['completeness'] !== null ? ' · ' . $nav['canvas']['completeness'] . '% ausgefüllt' : '')
                . ($nav['canvas']['count'] === 0 ? ' · noch nicht angelegt' : ''),
            'meta' => $nav['canvas']['completeness'] !== null
                ? $nav['canvas']['completeness'] . '%'
                : ($nav['canvas']['count'] === 0 ? '+' : ($nav['canvas']['count'] > 1 ? (string) $nav['canvas']['count'] : null)),
        ],
        'logbook' => [
            'href' => $nav['logbook']['url'],
            'label' => 'Logbuch',
            'icon' => 'heroicon-o-book-open',
            'title' => 'Logbuch — der Verlauf: Entscheidungen, Meilensteine, Risiken'
                . ($nav['logbook']['has_entries'] ? ' · letzter Eintrag vor ' . $nav['logbook']['days_since_entry'] . ' Tagen' : ' · noch leer')
                . ($nav['logbook']['open_risks'] > 0 ? ' · ' . $nav['logbook']['open_risks'] . ' offene Risiken' : ''),
            'meta' => $nav['logbook']['has_entries']
                ? ($nav['logbook']['days_since_entry'] === 0 ? 'heute' : $nav['logbook']['days_since_entry'] . 'd')
                : null,
            'stale' => $nav['logbook']['stale'],
            'risks' => $nav['logbook']['open_risks'],
        ],
        'health' => [
            'href' => $nav['health']['url'],
            'label' => 'Health',
            'icon' => 'heroicon-o-heart',
            'title' => 'Health — das Lagebild' . ($nav['health']['score'] !== null ? ' · Index ' . $nav['health']['score'] : ''),
            'meta' => $nav['health']['score'] !== null ? (string) $nav['health']['score'] : null,
            'dot' => $healthDot,
        ],
    ];
@endphp
<nav class="inline-flex rounded-md border border-[color:var(--nx-line-strong)] overflow-hidden" aria-label="Projekt-Bereiche">
    @foreach($projectTabs as $key => $tab)
        @php $isActive = ($active ?? null) === $key; @endphp
        <a href="{{ $tab['href'] }}" wire:navigate
           title="{{ $tab['title'] }}"
           @if($isActive) aria-current="page" @endif
           class="relative inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium transition-colors {{ $loop->first ? '' : 'border-l border-[color:var(--nx-line-strong)]' }} {{ $isActive ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
            @svg($tab['icon'], 'w-3.5 h-3.5')
            <span class="hidden sm:inline">{{ $tab['label'] }}</span>
            @if(! empty($tab['dot']))
                <span class="w-1.5 h-1.5 rounded-full {{ $tab['dot'] }}"></span>
            @endif
            @if($tab['meta'] !== null)
                <span class="tabular-nums text-[10px] {{ $isActive ? 'opacity-80' : 'opacity-70' }}">{{ $tab['meta'] }}</span>
            @endif
            @if(($tab['risks'] ?? 0) > 0)
                <span class="inline-flex items-center justify-center min-w-[14px] h-[14px] px-1 rounded-full text-[9px] font-semibold bg-[color:var(--nx-danger)] text-white tabular-nums">{{ $tab['risks'] }}</span>
            @elseif(($tab['stale'] ?? false) && ! $isActive)
                <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-warning)]" title="Länger kein Eintrag"></span>
            @endif
        </a>
    @endforeach
</nav>
