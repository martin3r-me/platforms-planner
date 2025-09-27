<x-ui-task-card 
    :task="$task"
    title="AUFGABE"
    :href="route('planner.tasks.show', $task)"
    :timestamp="$task->updated_at->format('d.m.Y')"
    :taskRoute="route('planner.tasks.show', $task)"
/>