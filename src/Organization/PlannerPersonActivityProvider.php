<?php

namespace Platform\Planner\Organization;

use Platform\Organization\Contracts\PersonActivityProvider;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;

class PlannerPersonActivityProvider implements PersonActivityProvider
{
    public function sectionKey(): string
    {
        return 'planner';
    }

    public function sectionConfig(): array
    {
        return [
            'label' => 'Planner',
            'icon' => 'clipboard-document-check',
            'description' => 'Projekte und Aufgaben',
        ];
    }

    public function metricConfig(): array
    {
        return [
            'open_tasks' => ['label' => 'Offene Aufgaben', 'type' => 'warning', 'sort_weight' => 1],
            'overdue_tasks' => ['label' => 'Überfällig', 'type' => 'danger', 'sort_weight' => 3],
            'own_projects' => ['label' => 'Eigene Projekte', 'type' => 'info', 'sort_weight' => 0],
            'memberships' => ['label' => 'Mitgliedschaften', 'type' => 'info', 'sort_weight' => 0],
        ];
    }

    public function vitalSigns(int $userId, int $teamId): array
    {
        $openTasks = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_done', false)
            ->count();

        $overdueTasks = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        $ownProjects = PlannerProject::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->count();

        $memberships = PlannerProjectUser::where('user_id', $userId)
            ->whereHas('project', fn($q) => $q->where('team_id', $teamId))
            ->count();

        $signs = [
            [
                'key' => 'open_tasks',
                'label' => 'Offene Aufgaben',
                'value' => $openTasks,
                'variant' => $openTasks > 0 ? 'default' : 'success',
            ],
        ];

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
            'label' => 'Projekt-Mitgliedschaften',
            'value' => $memberships,
            'variant' => 'default',
        ];

        return $signs;
    }

    public function responsibilities(int $userId, int $teamId, int $limit = 5): array
    {
        $groups = [];

        // Zugewiesene Aufgaben (offen)
        $taskQuery = PlannerTask::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_done', false)
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
                    'name' => $p->name,
                    'url' => route('planner.projects.show', $p),
                    'meta' => null,
                ])->toArray(),
            ];
        }

        return $groups;
    }
}
