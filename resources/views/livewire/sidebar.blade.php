{{-- resources/views/vendor/planner/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Planner
    </div>
    
    {{-- Abschnitt: Allgemein (lokal) --}}
    <div x-show="!collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-1">
            <a href="{{ route('planner.dashboard') }}" wire:navigate
               class="w-full text-left px-3 py-2 hover:bg-[rgba(255,255,255,0.06)] text-sm flex items-center text-white">
                @svg('heroicon-o-home', 'w-4 h-4 mr-2 text-white') Dashboard
            </a>
            <a href="{{ route('planner.my-tasks') }}" wire:navigate
               class="w-full text-left px-3 py-2 hover:bg-[rgba(255,255,255,0.06)] text-sm flex items-center text-white">
                @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 mr-2 text-white') Meine Aufgaben
            </a>
        </div>
    </div>

    {{-- Abschnitt: Projekte --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            {{-- Kundenprojekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($customerProjects->isNotEmpty())
                <div class="px-3 py-2 text-xs uppercase text-white/70">Kundenprojekte</div>
                @foreach($customerProjects as $project)
                    <a href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
                       class="relative flex items-center p-2 my-1 rounded-md font-medium transition gap-3 text-white"
                       :class="[
                           window.location.pathname.includes('/planner/projects/{{ $project->id }}') || 
                           window.location.pathname.endsWith('/planner/projects/{{ $project->id }}')
                               ? 'bg-[rgb(var(--ui-primary-rgb))] shadow-md'
                               : 'hover:bg-white/10 hover:shadow-md'
                       ]"
                       wire:navigate>
                        @svg('heroicon-o-folder', 'w-6 h-6 flex-shrink-0')
                        <span class="truncate">{{ $project->name }}</span>
                    </a>
                @endforeach
            @endif

            {{-- Interne Projekte nur anzeigen, wenn welche vorhanden sind --}}
            @if($internalProjects->isNotEmpty())
                <div class="px-3 py-2 text-xs uppercase text-white/70">Interne Projekte</div>
                @foreach($internalProjects as $project)
                    <a href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
                       class="relative flex items-center p-2 my-1 rounded-md font-medium transition gap-3 text-white"
                       :class="[
                           window.location.pathname.includes('/planner/projects/{{ $project->id }}') || 
                           window.location.pathname.endsWith('/planner/projects/{{ $project->id }}')
                               ? 'bg-[rgb(var(--ui-primary-rgb))] shadow-md'
                               : 'hover:bg-white/10 hover:shadow-md'
                       ]"
                       wire:navigate>
                        @svg('heroicon-o-folder', 'w-6 h-6 flex-shrink-0')
                        <span class="truncate">{{ $project->name }}</span>
                    </a>
                @endforeach
            @endif

            {{-- Keine Projekte --}}
            @if($customerProjects->isEmpty() && $internalProjects->isEmpty())
                <div class="px-3 py-1 text-xs text-white/60">Keine Projekte</div>
            @endif
        </div>
    </div>
</div>