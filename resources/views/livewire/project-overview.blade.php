<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->name" icon="heroicon-o-presentation-chart-line" />
    </x-slot>

    <x-slot name="actionbar">
        <x-planner::actionbar :project="$project" active="overview" />
    </x-slot>

    <div class="flex-1 min-w-0 overflow-y-auto bg-[var(--nx-bg)]">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 py-4 sm:py-6">
            @include('planner::livewire.project._dashboard', ['dashboardData' => $dashboardData, 'project' => $project])
        </div>
    </div>
</x-ui-page>
