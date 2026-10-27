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
    {{-- Modul-Navigation = nur die Projektliste. Bereiche (Dashboard, Aufgaben, Pflege,
         Portfolio) und "+ Neu" liegen in der Planner-Actionbar (<x-planner::actionbar>). --}}
    <a x-show="!collapsed" href="{{ route('planner.dashboard') }}" wire:navigate
       class="block p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[var(--nx-line)] mb-2 hover:text-[var(--nx-accent)]">
        Projekte
    </a>

    {{-- Eingeklappt: Einstieg ins Planner-Dashboard --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-line)]">
        <a href="{{ route('planner.dashboard') }}" wire:navigate title="Planner"
           class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('planner.*') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
            @svg('heroicon-o-home', 'w-5 h-5')
        </a>
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
