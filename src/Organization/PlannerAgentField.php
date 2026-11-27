<?php

namespace Platform\Planner\Organization;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Models\User;
use Platform\Organization\Contracts\AgentFieldSource;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Support\AgentDomain;
use Platform\Organization\Support\AgentFieldSources;
use Platform\Planner\Enums\ProjectKind;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\ProjectType;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Enums\TaskStoryPoints;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

/**
 * Organization-Bridge: die Projekte und Aufgaben im Arbeitsfeld eines Agenten (GET /api/org/agent/field).
 *
 * Im Feld: meine offenen Aufgaben; die Projekte, in denen sie liegen; die Projekte, die ich angelegt habe;
 * und die Projekte an den Einheiten, die ich verantworte (Wurzeln meines Bereichs) — je Projekt seine
 * offenen Aufgaben, auch die anderer. Anlegen: Projekt an einer Einheit, Aufgabe in einem Projekt —
 * gibt es dieselbe Arbeit schon offen, kommt die bestehende zurück.
 */
class PlannerAgentField implements AgentFieldSource
{
    private const MAX_PROJECTS = 25;

    private const TASKS_PER_PROJECT = 10;

    private const OPEN_PROJECT = ['aktiv', 'ruhend']; // ProjectLifecycleState::ACTIVE / DORMANT

    public function key(): string
    {
        return 'planner';
    }

    public function creatable(): array
    {
        return ['project' => 'entity', 'planner_task' => 'project'];
    }

    public function fieldFor(OrganizationEntity $agent, AgentDomain $domain): array
    {
        $userId = (int) $agent->linked_user_id;
        if ($userId < 1) {
            return [];
        }
        $today = now()->toDateString();

        $mine = PlannerTask::query()->with('userInCharge:id,name')
            ->where('user_in_charge_id', $userId)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
            ->orderByRaw('due_date is null')->orderBy('due_date')->latest('updated_at')
            ->limit(40)->get();

        $unitProjects = EntityDimensionBridge::linksForEntities($domain->roots)
            ->where('linkable_type', 'project')->pluck('linkable_id')->map(fn ($id) => (int) $id)->all();
        $ids = array_values(array_unique(array_merge(
            $mine->pluck('project_id')->filter()->map(fn ($id) => (int) $id)->all(),
            PlannerProject::query()->where('user_id', $userId)->whereIn('lifecycle_state', self::OPEN_PROJECT)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $unitProjects,
        )));
        $projects = PlannerProject::query()->whereIn('id', $ids)->whereIn('lifecycle_state', self::OPEN_PROJECT)
            ->latest('updated_at')->limit(self::MAX_PROJECTS)->get();

        $units = [];
        foreach (EntityDimensionBridge::linksForLinkables(['project'], $projects->pluck('id')->all()) as $l) {
            $units[(int) $l->linkable_id] ??= $l->entity ? ['id' => (int) $l->entity_id, 'name' => (string) $l->entity->name] : null;
        }

        $objects = [];
        $seen = [];
        foreach ($projects as $p) {
            $open = PlannerTask::query()->with('userInCharge:id,name')->where('project_id', $p->id)
                ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)
                ->orderByRaw('due_date is null')->orderBy('due_date')->latest('updated_at');
            $objects[] = [
                'type' => 'project', 'id' => (int) $p->id, 'title' => (string) $p->name,
                'state' => (string) $p->lifecycle_state?->value, 'open' => true, 'parent' => null,
                'unit' => $units[(int) $p->id] ?? null, 'mine' => (int) $p->user_id === $userId,
                'open_tasks' => (clone $open)->count(), 'updated_at' => $p->updated_at?->toIso8601String(),
            ];
            foreach ($open->limit(self::TASKS_PER_PROJECT)->get() as $t) {
                $objects[] = $this->task($t, $userId, $today);
                $seen[(int) $t->id] = true;
            }
        }
        // Meine Aufgaben, die nicht schon über ein Projekt drin sind (ohne Projekt oder Projekt außerhalb).
        foreach ($mine as $t) {
            if (! isset($seen[(int) $t->id])) {
                $objects[] = $this->task($t, $userId, $today);
            }
        }

        return $objects;
    }

    public function create(OrganizationEntity $agent, string $type, array $input): array
    {
        $user = User::find((int) $agent->linked_user_id);
        if (! $user) {
            throw new \InvalidArgumentException('Agent hat keinen Benutzer.');
        }

        return $type === 'project' ? $this->createProject($agent, $user, $input) : $this->createTask($user, $input);
    }

    /** @param array<string,mixed> $input */
    private function createProject(OrganizationEntity $agent, User $user, array $input): array
    {
        $entityId = (int) $input['parent']['id'];
        $atUnit = EntityDimensionBridge::linksForEntity($entityId)->where('linkable_type', 'project')->pluck('linkable_id')->all();
        $existing = PlannerProject::query()->whereIn('id', $atUnit)->whereIn('lifecycle_state', self::OPEN_PROJECT)->get()
            ->first(fn ($p) => AgentFieldSources::sameWork((string) $p->name, (string) $input['title']));
        if ($existing) {
            return ['object' => $this->projectRef($existing), 'existing' => true];
        }
        if (! Gate::forUser($user)->allows('create', PlannerProject::class)) {
            throw new \InvalidArgumentException('Du darfst keine Projekte anlegen.');
        }

        $teamId = (int) ($agent->team_id ?: $user->current_team_id);
        $project = PlannerProject::create([
            'name' => $input['title'],
            'description' => $input['description'] ?? null,
            'user_id' => $user->id,
            'team_id' => $teamId,
            'project_type' => ProjectType::INTERNAL,
            'kind' => ProjectKind::PROJECT,
            'lifecycle_state' => ProjectLifecycleState::ACTIVE,
            'order' => (PlannerProject::where('team_id', $teamId)->max('order') ?? 0) + 1,
        ]);
        EntityDimensionBridge::createLink($entityId, 'project', $project->id, ['team_id' => $teamId, 'created_by_user_id' => $user->id]);

        return ['object' => $this->projectRef($project), 'existing' => false];
    }

    /** @param array<string,mixed> $input */
    private function createTask(User $user, array $input): array
    {
        $project = PlannerProject::query()->whereIn('lifecycle_state', self::OPEN_PROJECT)->find((int) $input['parent']['id']);
        if (! $project || ! Gate::forUser($user)->allows('view', $project)) {
            throw new \InvalidArgumentException('Projekt nicht gefunden, abgeschlossen oder nicht sichtbar.');
        }
        $existing = PlannerTask::query()->where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::ACTIVE->value)->get()
            ->first(fn ($t) => AgentFieldSources::sameWork((string) $t->title, (string) $input['title']));
        if ($existing) {
            return ['object' => $this->task($existing, (int) $user->id, now()->toDateString()), 'existing' => true];
        }
        $points = ! empty($input['story_points']) ? TaskStoryPoints::tryFrom(strtolower((string) $input['story_points'])) : null;
        if (! empty($input['story_points']) && ! $points) {
            throw new \InvalidArgumentException('story_points: xs, s, m, l, xl oder xxl.');
        }

        $task = PlannerTask::create([
            'title' => $input['title'],
            'description' => $input['description'] ?? null,
            'user_id' => $user->id,
            'user_in_charge_id' => $user->id,
            'team_id' => $project->team_id,
            'project_id' => $project->id,
            'due_date' => $input['due_date'] ?? null,
            'story_points' => $points,
            'lifecycle_state' => TaskLifecycleState::ACTIVE->value,
            'order' => (PlannerTask::where('project_id', $project->id)->min('order') ?? 0) - 1,
            'agent_summary' => 'Vom Agenten in seinem Arbeitsfeld angelegt.',
        ]);
        $task->logActivity('Agent hat diese Aufgabe in seinem Arbeitsfeld angelegt.', ['source' => 'agent', 'status' => 'created']);

        return ['object' => $this->task($task, (int) $user->id, now()->toDateString()), 'existing' => false];
    }

    /** @return array<string,mixed> */
    private function task(PlannerTask $t, int $userId, string $today): array
    {
        $due = $t->due_date?->toDateString();

        return [
            'type' => 'planner_task', 'id' => (int) $t->id, 'title' => (string) $t->title,
            'state' => (string) ($t->lifecycle_state?->value ?? $t->lifecycle_state), 'open' => true,
            'parent' => $t->project_id ? ['type' => 'project', 'id' => (int) $t->project_id] : null,
            'mine' => (int) $t->user_in_charge_id === $userId,
            'assignee' => $t->userInCharge?->name,
            'due' => $due, 'overdue' => $due !== null && $due < $today,
            'points' => $t->story_points?->value,
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function projectRef(PlannerProject $p): array
    {
        return ['type' => 'project', 'id' => (int) $p->id, 'title' => (string) $p->name, 'state' => (string) $p->lifecycle_state?->value, 'open' => true];
    }
}
