{{-- resources/views/vendor/planner/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Modul Header --}}
    <x-sidebar-module-header module-name="Planner" />
    
    {{-- Abschnitt: Allgemein --}}
    <x-ui-sidebar-general 
        :dashboardRoute="route('planner.dashboard')"
        :myTasksRoute="route('planner.my-tasks')"
    />

    {{-- Abschnitt: Projekte --}}
    <div x-data="{ plannerSidebar: { collapsed: false } }">
        <div class="mt-2">
            {{-- Kundenprojekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($customerProjects->isNotEmpty())
                <div class="px-3 py-2 text-xs uppercase text-secondary">Kundenprojekte</div>
                @foreach($customerProjects as $project)
                    <a href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
                       class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition gap-3"
                       :class="[
                           window.location.pathname.includes('/planner/projects/{{ $project->id }}') || 
                           window.location.pathname.endsWith('/planner/projects/{{ $project->id }}')
                               ? 'bg-primary text-on-primary shadow-md'
                               : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md'
                       ]"
                       wire:navigate>
                        <x-heroicon-o-folder class="w-6 h-6 flex-shrink-0"/>
                        <span class="truncate">{{ $project->name }}</span>
                    </a>
                @endforeach
            @endif

            {{-- Interne Projekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($internalProjects->isNotEmpty())
                <div class="px-3 py-2 text-xs uppercase text-secondary">Interne Projekte</div>
                @foreach($internalProjects as $project)
                    <a href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
                       class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition gap-3"
                       :class="[
                           window.location.pathname.includes('/planner/projects/{{ $project->id }}') || 
                           window.location.pathname.endsWith('/planner/projects/{{ $project->id }}')
                               ? 'bg-primary text-on-primary shadow-md'
                               : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md'
                       ]"
                       wire:navigate>
                        <x-heroicon-o-folder class="w-6 h-6 flex-shrink-0"/>
                        <span class="truncate">{{ $project->name }}</span>
                    </a>
                @endforeach
            @endif

            {{-- Keine Projekte --}}
            @if($customerProjects->isEmpty() && $internalProjects->isEmpty())
                <div class="px-3 py-1 text-xs text-muted">Keine Projekte</div>
            @endif
        </div>
    </div>
</div>