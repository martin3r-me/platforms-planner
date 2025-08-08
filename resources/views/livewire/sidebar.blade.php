{{-- resources/views/vendor/planner/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Abschnitt: Allgemein --}}
    <div>
        <h4 x-show="!collapsed" class="p-3 text-sm italic text-secondary uppercase">Allgemein</h4>

        {{-- Dashboard --}}
        <a href="{{ route('planner.dashboard') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname === '/' || 
               window.location.pathname.endsWith('/planner') || 
               window.location.pathname.endsWith('/planner/') ||
               (window.location.pathname.split('/').length === 1 && window.location.pathname === '/')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-chart-bar class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Dashboard</span>
        </a>

        {{-- Meine Aufgaben --}}
        <a href="{{ route('planner.my-tasks') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/my-tasks') || 
               window.location.pathname.endsWith('/my-tasks')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-home class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Meine Aufgaben</span>
        </a>

        {{-- Projekt anlegen --}}
        <a href="#"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="collapsed ? 'justify-center' : 'gap-3'"
           wire:click="createProject">
            <x-heroicon-o-plus class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Projekt anlegen</span>
        </a>
    </div>

    {{-- Abschnitt: Projekte --}}
    <div x-show="!collapsed">
        <h4 class="p-3 text-sm italic text-secondary uppercase">Projekte</h4>

        @foreach($projects as $project)
            <a href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
               class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition gap-3"
               :class="[
                   window.location.pathname.includes('/projects/{{ $project->id }}/') || 
                   window.location.pathname.includes('/projects/{{ $project->uuid }}/') ||
                   window.location.pathname.endsWith('/projects/{{ $project->id }}') ||
                   window.location.pathname.endsWith('/projects/{{ $project->uuid }}') ||
                   (window.location.pathname.split('/').length === 2 && window.location.pathname.endsWith('/{{ $project->id }}')) ||
                   (window.location.pathname.split('/').length === 2 && window.location.pathname.endsWith('/{{ $project->uuid }}'))
                       ? 'bg-primary text-on-primary shadow-md'
                       : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md'
               ]"
               wire:navigate>
                <x-heroicon-o-folder class="w-6 h-6 flex-shrink-0"/>
                <span class="truncate">{{ $project->name }}</span>
            </a>
        @endforeach
    </div>
</div>