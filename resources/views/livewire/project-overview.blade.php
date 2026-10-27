<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->name" icon="heroicon-o-presentation-chart-line" />
    </x-slot>

    <x-slot name="actionbar">
        <x-planner::actionbar :project="$project" active="overview" />
    </x-slot>

    <x-planner::body>
        @include('planner::livewire.project._dashboard', ['dashboardData' => $dashboardData, 'project' => $project])
    </x-planner::body>
</x-ui-page>
