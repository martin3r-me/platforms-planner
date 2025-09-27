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
    <x-ui-sidebar-projects 
        :customerProjects="$customerProjects"
        :internalProjects="$internalProjects"
        :projectRoute="'planner.projects.show'"
        :routeParam="'plannerProject'"
        alpineStoreName="plannerSidebar"
    />
</div>