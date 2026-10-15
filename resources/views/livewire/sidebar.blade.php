{{-- resources/views/livewire/sidebar.blade.php --}}
<div
    x-data="{
        init() {
            const savedState = localStorage.getItem('planner.showAllProjects');
            if (savedState !== null) {
                @this.set('showAllProjects', savedState === 'true');
            }
        }
    }"
>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[var(--nx-line)] mb-2">
        Planner
    </div>

    {{-- Nav-Sektionen (kompakt) --}}
    @php
        // Allgemein = persoenliches Tagesgeschaeft.
        $generalItems = [
            ['route' => 'planner.dashboard',        'icon' => 'heroicon-o-home',                     'label' => 'Dashboard'],
            // Aufgaben = ein Einstieg; Meine/Delegiert/Erledigt sind Tabs auf der Seite.
            ['route' => 'planner.my-tasks',         'icon' => 'heroicon-o-clipboard-document-check', 'label' => 'Aufgaben', 'active' => ['planner.my-tasks', 'planner.delegated-tasks', 'planner.completed-tasks']],
        ];
        // Steuerung = Portfolio-/Masse-Werkzeuge (nicht das Tagesgeschaeft).
        // Pflege = Hygiene+Cleanup (Meins/Team), Portfolio-Health = Health-Index+Ops (Analyse/Wand).
        $steuerungItems = [
            ['route' => 'planner.hygiene',          'icon' => 'heroicon-o-shield-check',             'label' => 'Pflege',           'active' => ['planner.hygiene', 'planner.projects.cleanup']],
            ['route' => 'planner.health-index',     'icon' => 'heroicon-o-heart',                    'label' => 'Portfolio-Health', 'active' => ['planner.health-index', 'planner.ops']],
        ];
        $navSections = [
            'Allgemein' => $generalItems,
            'Steuerung' => $steuerungItems,
        ];
    @endphp
    <div x-show="!collapsed" class="px-2 pb-2 space-y-2">
        @foreach($navSections as $sectionLabel => $items)
            <div>
                <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)] px-1 py-1">{{ $sectionLabel }}</div>
                <nav class="flex flex-col">
                    @foreach($items as $item)
                        @php $isActive = request()->routeIs(...($item['active'] ?? [$item['route']])); @endphp
                        <a href="{{ route($item['route']) }}" wire:navigate
                           class="flex items-center gap-2 px-2 py-1 rounded text-xs transition {{ $isActive ? 'bg-[color:var(--nx-accent)] text-[var(--nx-on-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                            @svg($item['icon'], 'w-3.5 h-3.5 opacity-80 flex-shrink-0')
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        @endforeach
        <nav class="flex flex-col">
            <button type="button" wire:click="createProject"
                    class="flex items-center gap-2 px-2 py-1 rounded text-xs text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition text-left">
                @svg('heroicon-o-plus-circle', 'w-3.5 h-3.5 opacity-80 flex-shrink-0')
                <span class="truncate">Neues Projekt</span>
            </button>
        </nav>
    </div>

    {{-- Collapsed: Icons-only für Allgemein --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-line)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('planner.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.dashboard') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.my-tasks') }}" wire:navigate title="Aufgaben" class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.my-tasks', 'planner.delegated-tasks', 'planner.completed-tasks') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                @svg('heroicon-o-clipboard-document-check', 'w-5 h-5')
            </a>
            {{-- Steuerung --}}
            <div class="my-0.5 border-t border-[var(--nx-line)] opacity-60"></div>
            <a href="{{ route('planner.hygiene') }}" wire:navigate title="Pflege" class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.hygiene', 'planner.projects.cleanup') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                @svg('heroicon-o-shield-check', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.health-index') }}" wire:navigate title="Portfolio-Health" class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.health-index', 'planner.ops') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
                @svg('heroicon-o-heart', 'w-5 h-5')
            </a>
        </div>
    </div>
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-line)]">
        <button type="button" wire:click="createProject" class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
            @svg('heroicon-o-plus-circle', 'w-5 h-5')
        </button>
    </div>

    {{-- Abschnitt: Projekte (Entity-basierte Gruppierung) --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            {{-- Entity Type Gruppen (Baum-Darstellung) --}}
            @foreach($entityTypeGroups as $typeGroup)
                <x-ui-sidebar-list wire:key="type-group-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                    @foreach($typeGroup['entities'] as $entityNode)
                        @include('planner::livewire.partials.sidebar-entity-node', [
                            'node' => $entityNode,
                            'typeIcon' => $typeGroup['type_icon'] ?? null,
                        ])
                    @endforeach
                </x-ui-sidebar-list>
            @endforeach

            {{-- Unverknüpfte Projekte --}}
            @if($unlinkedProjects->isNotEmpty())
                <x-ui-sidebar-list label="Unverknüpft">
                    @foreach($unlinkedProjects as $project)
                        <a wire:key="unlinked-project-{{ $project->id }}"
                           href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
                           wire:navigate
                           title="{{ $project->title }}"
                           class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--nx-text)] hover:text-[var(--nx-accent)] transition truncate">
                            <span class="w-1 h-1 rounded-full flex-shrink-0 bg-[var(--nx-muted)] opacity-40"></span>
                            <span class="truncate text-[11px]">{{ $project->title }}</span>
                            @if($project->color)
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 ml-auto" style="background-color: {{ $project->color }}"></span>
                            @endif
                        </a>
                    @endforeach
                </x-ui-sidebar-list>
            @endif

            {{-- Button zum Ein-/Ausblenden aller Projekte --}}
            @if($hasMoreProjects)
                <div class="px-3 py-2">
                    <button
                        type="button"
                        wire:click="toggleShowAllProjects"
                        x-on:click="localStorage.setItem('planner.showAllProjects', (!$wire.showAllProjects).toString())"
                        class="flex items-center gap-2 text-xs text-[var(--nx-muted)] hover:text-[var(--nx-text)] transition-colors"
                    >
                        @if($showAllProjects)
                            @svg('heroicon-o-eye-slash', 'w-4 h-4')
                            <span>Nur meine Projekte</span>
                        @else
                            @svg('heroicon-o-eye', 'w-4 h-4')
                            <span>Alle Projekte anzeigen</span>
                        @endif
                    </button>
                </div>
            @endif

            {{-- Keine Projekte --}}
            @if($entityTypeGroups->isEmpty() && $unlinkedProjects->isEmpty())
                <div class="px-3 py-1 text-xs text-[var(--nx-muted)]">
                    @if($showAllProjects)
                        Keine Projekte
                    @else
                        Keine Projekte mit Aufgaben
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
