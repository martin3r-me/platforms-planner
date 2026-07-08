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
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Planner
    </div>

    {{-- Abschnitt: Allgemein (kompakt) --}}
    @php
        $navItems = [
            ['route' => 'planner.dashboard',        'icon' => 'heroicon-o-home',                     'label' => 'Dashboard'],
            ['route' => 'planner.my-tasks',         'icon' => 'heroicon-o-clipboard-document-check', 'label' => 'Meine Aufgaben'],
            ['route' => 'planner.delegated-tasks',  'icon' => 'heroicon-o-user-group',               'label' => 'Delegierte Aufgaben'],
            ['route' => 'planner.completed-tasks',  'icon' => 'heroicon-o-check-circle',             'label' => 'Erledigte Aufgaben'],
            ['route' => 'planner.frog-tasks',       'icon' => 'heroicon-o-exclamation-triangle',     'label' => 'Frösche'],
            ['route' => 'planner.hygiene',          'icon' => 'heroicon-o-shield-check',             'label' => 'Hygiene'],
            ['route' => 'planner.projects.cleanup', 'icon' => 'heroicon-o-adjustments-horizontal',   'label' => 'Projects Cleanup'],
            ['route' => 'planner.health-index',     'icon' => 'heroicon-o-heart',                    'label' => 'Health-Index'],
            ['route' => 'planner.ops',              'icon' => 'heroicon-o-command-line',             'label' => 'Ops-Room'],
        ];
    @endphp
    <div x-show="!collapsed" class="px-2 pb-2">
        <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] px-1 py-1">Allgemein</div>
        <nav class="flex flex-col">
            @foreach($navItems as $item)
                @php $isActive = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}" wire:navigate
                   class="flex items-center gap-2 px-2 py-1 rounded text-xs transition {{ $isActive ? 'bg-[rgb(var(--ui-primary-rgb))] text-[var(--ui-on-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                    @svg($item['icon'], 'w-3.5 h-3.5 opacity-80 flex-shrink-0')
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
            <button type="button" wire:click="createProject"
                    class="flex items-center gap-2 px-2 py-1 mt-1 rounded text-xs text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition text-left">
                @svg('heroicon-o-plus-circle', 'w-3.5 h-3.5 opacity-80 flex-shrink-0')
                <span class="truncate">Neues Projekt</span>
            </button>
        </nav>
    </div>

    {{-- Collapsed: Icons-only für Allgemein --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('planner.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.dashboard') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.my-tasks') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.my-tasks') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-clipboard-document-check', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.delegated-tasks') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.delegated-tasks') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-user-group', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.completed-tasks') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.completed-tasks') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-check-circle', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.frog-tasks') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.frog-tasks') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-exclamation-triangle', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.health-index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.health-index') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-heart', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.ops') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.ops') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-command-line', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.hygiene') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.hygiene') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-shield-check', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.projects.cleanup') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.projects.cleanup') ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                @svg('heroicon-o-adjustments-horizontal', 'w-5 h-5')
            </a>
        </div>
    </div>
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <button type="button" wire:click="createProject" class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
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
                           class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                            <span class="w-1 h-1 rounded-full flex-shrink-0 bg-[var(--ui-muted)] opacity-40"></span>
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
                        class="flex items-center gap-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
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
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">
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
