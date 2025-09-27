<div>
    <x-ui-task-layout
        :task="$task"
        :breadcrumbItems="[
            $task->project ? [
                'label' => 'Projekt: ' . $task->project->name,
                'href' => auth()->user()->can('view', $task->project) ? route('planner.projects.show', $task->project) : null
            ] : null,
            [
                'label' => 'Meine Aufgaben',
                'href' => route('planner.my-tasks')
            ],
            [
                'label' => $task->title,
                'current' => true
            ]
        ]"
        :canUpdate="auth()->user()->can('update', $task)"
        :canDelete="auth()->user()->can('delete', $task)"
        projectRoute="planner.projects.show"
        myTasksRoute="planner.my-tasks"
        :priorityOptions="\Platform\Planner\Enums\TaskPriority::cases()"
        :storyPointsOptions="\Platform\Planner\Enums\TaskStoryPoints::cases()"
    />

    <!-- Print Modal direkt hier einbinden -->
    <livewire:planner.print-modal />
</div>