<?php

namespace Platform\Planner\Organization;

use Platform\Organization\Contracts\PersonActivityProvider;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;

class PlannerPersonActivityProvider implements PersonActivityProvider
{
    public function sectionKey(): string
    {
        return 'planner';
    }

    public function sectionConfig(): array
    {
        return [
            'label' => 'Projekte',
            'icon' => 'clipboard-document-check',
            'description' => 'Projekte und Aufgaben',
        ];
    }

    public function metricConfig(): array
    {
        return [
            'frogs' => ['label' => 'Frösche', 'type' => 'danger', 'sort_weight' => 4],
            'open_tasks' => ['label' => 'Offene Aufgaben', 'type' => 'warning', 'sort_weight' => 1],
            'overdue_tasks' => ['label' => 'Überfällig', 'type' => 'danger', 'sort_weight' => 3],
            'own_projects' => ['label' => 'Eigene Projekte', 'type' => 'info', 'sort_weight' => 0],
            'memberships' => ['label' => 'Beteiligte Projekte', 'type' => 'info', 'sort_weight' => 0],
        ];
    }

    public function vitalSigns(int $userId, int $teamId): array
    {
        $openTasks = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->count();

        $overdueTasks = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        $ownProjects = PlannerProject::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->count();

        // Beteiligte (fremde) Projekte = Projekte mit Aufgaben-Verantwortung, die
        // die Person nicht selbst erstellt hat. Ersetzt die frühere Mitgliedschaft
        // (Zugriff kommt jetzt aus dem Graphen).
        $memberships = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->whereNotNull('project_id')
            ->whereHas('project', fn($q) => $q->where('user_id', '!=', $userId))
            ->distinct()
            ->count('project_id');

        $frogs = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->where('is_frog', true)
            ->count();

        $signs = [
            [
                'key' => 'open_tasks',
                'label' => 'Offene Aufgaben',
                'value' => $openTasks,
                'variant' => $openTasks > 0 ? 'default' : 'success',
            ],
        ];

        // Frösche zuerst (Fokus).
        if ($frogs > 0) {
            array_unshift($signs, [
                'key' => 'frogs',
                'label' => 'Frösche',
                'value' => $frogs,
                'variant' => 'danger',
            ]);
        }

        if ($overdueTasks > 0) {
            $signs[] = [
                'key' => 'overdue_tasks',
                'label' => 'Überfällig',
                'value' => $overdueTasks,
                'variant' => 'danger',
            ];
        }

        $signs[] = [
            'key' => 'own_projects',
            'label' => 'Eigene Projekte',
            'value' => $ownProjects,
            'variant' => 'default',
        ];

        $signs[] = [
            'key' => 'memberships',
            'label' => 'Beteiligte Projekte',
            'value' => $memberships,
            'variant' => 'default',
        ];

        return $signs;
    }

    public function responsibilities(int $userId, int $teamId, int $limit = 5): array
    {
        $groups = [];

        // Frösche zuerst (absolute Fokus-Aufgaben) — überfällig zuerst.
        $frogQuery = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->where('is_frog', true)
            ->with('project')
            ->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < NOW() THEN 0 ELSE 1 END')
            ->orderBy('due_date');

        $totalFrogs = $frogQuery->count();
        $frogTasks = $frogQuery->limit($limit)->get();

        if ($totalFrogs > 0) {
            $groups[] = [
                'key' => 'frogs',
                'label' => 'Frösche',
                'icon' => 'fire',
                'total_count' => $totalFrogs,
                'items' => $frogTasks->map(function ($t) {
                    $meta = [];
                    if ($t->project) {
                        $meta[] = $t->project->name;
                    }
                    if ($t->due_date) {
                        $meta[] = $t->due_date->isPast() ? 'überfällig' : 'fällig ' . $t->due_date->format('d.m.Y');
                    }
                    return [
                        'id' => $t->id,
                        'name' => $t->title ?? '—',
                        'url' => route('planner.tasks.show', $t),
                        'meta' => implode(' · ', $meta) ?: null,
                    ];
                })->toArray(),
            ];
        }

        // Zugewiesene Aufgaben (offen) — Frösche hier ausgeschlossen (stehen oben).
        $taskQuery = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->where('is_frog', false)
            ->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < NOW() THEN 0 ELSE 1 END')
            ->orderBy('due_date');

        $totalTasks = $taskQuery->count();
        $tasks = $taskQuery->limit($limit)->get();

        if ($totalTasks > 0) {
            $groups[] = [
                'key' => 'assigned_tasks',
                'label' => 'Zugewiesene Aufgaben',
                'icon' => 'clipboard-document-check',
                'total_count' => $totalTasks,
                'items' => $tasks->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->title ?? $t->name ?? '—',
                    'url' => null,
                    'meta' => $t->due_date
                        ? ($t->due_date->isPast() ? 'Überfällig: ' : 'Fällig: ') . $t->due_date->format('d.m.Y')
                        : null,
                ])->toArray(),
            ];
        }

        // Eigene Projekte
        $projectQuery = PlannerProject::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->orderBy('name');

        $totalProjects = $projectQuery->count();
        $projects = $projectQuery->limit($limit)->get();

        if ($totalProjects > 0) {
            $groups[] = [
                'key' => 'own_projects',
                'label' => 'Eigene Projekte',
                'icon' => 'folder',
                'total_count' => $totalProjects,
                'items' => $projects->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->title,
                    'url' => route('planner.projects.show', $p),
                    'meta' => null,
                ])->toArray(),
            ];
        }

        return $groups;
    }
}
