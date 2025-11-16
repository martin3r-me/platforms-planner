{{-- resources/views/vendor/planner/livewire/sidebar-content.blade.php --}}
<div 
    x-data="{
        init() {
            // Zustand aus localStorage laden beim Initialisieren
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
    
    {{-- Abschnitt: Allgemein (über UI-Komponenten) --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('planner.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('planner.my-tasks')">
            @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Meine Aufgaben</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('planner.delegated-tasks')">
            @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Delegierte Aufgaben</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('planner.completed-tasks')">
            @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Erledigte Aufgaben</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Neues Projekt --}}
    <x-ui-sidebar-list>
        <x-ui-sidebar-item wire:click="createProject">
            @svg('heroicon-o-plus-circle', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Neues Projekt</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only für Allgemein --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('planner.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.my-tasks') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-clipboard-document-check', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.delegated-tasks') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-user-group', 'w-5 h-5')
            </a>
            <a href="{{ route('planner.completed-tasks') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-check-circle', 'w-5 h-5')
            </a>
        </div>
    </div>
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <button type="button" wire:click="createProject" class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
            @svg('heroicon-o-plus-circle', 'w-5 h-5')
        </button>
    </div>

    {{-- Abschnitt: Projekte --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            {{-- Kundenprojekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($customerProjects->isNotEmpty())
                <x-ui-sidebar-list :label="'Kundenprojekte' . ($showAllProjects ? ' (' . $allCustomerProjectsCount . ')' : '')">
                    @foreach($customerProjects as $project)
                        <x-ui-sidebar-item :href="route('planner.projects.show', ['plannerProject' => $project])">
                            @svg('heroicon-o-folder', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                            <div class="flex-1 min-w-0 ml-2">
                                <div class="truncate text-sm font-medium">{{ $project->name }}</div>
                            </div>
                        </x-ui-sidebar-item>
                    @endforeach
                </x-ui-sidebar-list>
            @endif

            {{-- Interne Projekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($internalProjects->isNotEmpty())
                <x-ui-sidebar-list :label="'Interne Projekte' . ($showAllProjects ? ' (' . $allInternalProjectsCount . ')' : '')">
                    @foreach($internalProjects as $project)
                        <x-ui-sidebar-item :href="route('planner.projects.show', ['plannerProject' => $project])">
                            @svg('heroicon-o-folder', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                            <div class="flex-1 min-w-0 ml-2">
                                <div class="truncate text-sm font-medium">{{ $project->name }}</div>
                            </div>
                        </x-ui-sidebar-item>
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
            @if($customerProjects->isEmpty() && $internalProjects->isEmpty())
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