{{-- resources/views/vendor/planner/livewire/sidebar-content.blade.php --}}
<div>
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
        <x-ui-sidebar-item :href="route('planner.projects.create')">
            @svg('heroicon-o-plus-circle', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Neues Projekt</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Projekte --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            {{-- Kundenprojekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($customerProjects->isNotEmpty())
                <x-ui-sidebar-list label="Kundenprojekte">
                    @foreach($customerProjects as $project)
                        <x-ui-sidebar-item :href="route('planner.projects.show', ['plannerProject' => $project])">
                            @svg('heroicon-o-folder', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                            <span class="truncate text-sm ml-2">{{ $project->name }}</span>
                        </x-ui-sidebar-item>
                    @endforeach
                </x-ui-sidebar-list>
            @endif

            {{-- Interne Projekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($internalProjects->isNotEmpty())
                <x-ui-sidebar-list label="Interne Projekte">
                    @foreach($internalProjects as $project)
                        <x-ui-sidebar-item :href="route('planner.projects.show', ['plannerProject' => $project])">
                            @svg('heroicon-o-folder', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                            <span class="truncate text-sm ml-2">{{ $project->name }}</span>
                        </x-ui-sidebar-item>
                    @endforeach
                </x-ui-sidebar-list>
            @endif

            {{-- Keine Projekte --}}
            @if($customerProjects->isEmpty() && $internalProjects->isEmpty())
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">Keine Projekte</div>
            @endif
        </div>
    </div>
</div>