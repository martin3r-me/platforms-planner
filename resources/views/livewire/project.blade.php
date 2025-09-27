<div class="h-full d-flex">
    @php 
        $completedTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
        $stats = [
            [
                'title' => 'Story Points (offen)',
                'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'chart-bar',
                'variant' => 'warning'
            ],
            [
                'title' => 'Story Points (erledigt)',
                'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'check-circle',
                'variant' => 'success'
            ],
            [
                'title' => 'Offen',
                'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count()),
                'icon' => 'clock',
                'variant' => 'warning'
            ],
            [
                'title' => 'Gesamt',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->count(),
                'icon' => 'document-text',
                'variant' => 'secondary'
            ],
            [
                'title' => 'Erledigt',
                'count' => $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count()),
                'icon' => 'check-circle',
                'variant' => 'success'
            ],
            [
                'title' => 'Ohne Fälligkeit',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count(),
                'icon' => 'calendar',
                'variant' => 'neutral'
            ],
            [
                'title' => 'Frösche',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->is_frog)->count(),
                'icon' => 'exclamation-triangle',
                'variant' => 'danger'
            ],
            [
                'title' => 'Überfällig',
                'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(),
                'icon' => 'exclamation-circle',
                'variant' => 'danger'
            ]
        ];
        $actions = [
            [
                'label' => '+ Neue Aufgabe',
                'variant' => 'success',
                'size' => 'sm',
                'wire_click' => 'createTask()'
            ],
            [
                'label' => '+ Neue Spalte',
                'variant' => 'primary',
                'size' => 'sm',
                'wire_click' => 'createSprintSlot'
            ]
        ];
        
        // Projekt-spezifische Buttons hinzufügen
        if(($project->project_type?->value ?? $project->project_type) === 'customer') {
            $actions[] = [
                'label' => 'Kunden',
                'variant' => 'primary',
                'size' => 'sm',
                'wire_click' => null,
                'onclick' => '$dispatch(\'open-modal-customer-project\', { projectId: ' . $project->id . ' })'
            ];
        }
        
        $actions[] = [
            'label' => 'Projekt-Einstellungen',
            'variant' => 'info',
            'size' => 'sm',
            'wire_click' => null,
            'onclick' => '$dispatch(\'open-modal-project-settings\', { projectId: ' . $project->id . ' })'
        ];
    @endphp

    <x-ui-tasks-info-sidebar 
        :title="$project->name"
        :subtitle="'Projekt-Übersicht und Statistiken'"
        :stats="$stats"
        :completed-tasks="$completedTasks"
        :actions="$actions"
        :can-update="auth()->user()->can('update', $project)"
    />

    <x-ui-tasks-kanban-container 
        :groups="$groups"
        sortable-group-order="updateTaskGroupOrder"
        sortable-task-order="updateTaskOrder"
        settings-modal-event="open-modal-sprint-slot-settings"
        settings-modal-param="sprintSlotId"
        taskRoute="planner.tasks.show"
    />

    <livewire:planner.project-settings-modal/>
    <livewire:planner.sprint-slot-settings-modal/>
    <livewire:planner.customer-project-settings-modal/>

</div>