<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject as Project;
use Platform\Planner\Models\PlannerProjectSlot as ProjectSlot;
use Platform\Planner\Models\PlannerTask;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Livewire\Attributes\On;


class Sidebar extends Component
{
    public bool $showAllProjects = false;

    public function mount()
    {
        // Zustand aus localStorage laden (wird vom Frontend gesetzt)
        $this->showAllProjects = false; // Default-Wert, wird vom Frontend überschrieben
    }

    #[On('updateSidebar')]
    public function updateSidebar()
    {

    }

    public function toggleShowAllProjects()
    {
        $this->showAllProjects = !$this->showAllProjects;
    }

    public function createProject()
    {
        $user = Auth::user();
        $teamId = $user->currentTeam->id;

        // 1. Neues Projekt anlegen
        $project = new Project();
        $project->name = 'Neues Projekt';
        $project->user_id = $user->id;
        $project->team_id = $teamId;
        $project->order = Project::where('team_id', $teamId)->max('order') + 1;
        $project->save();

        // --> ProjectUser als Owner anlegen!
        $project->projectUsers()->create([
            'user_id' => $user->id,
            'role' => \Platform\Planner\Enums\ProjectRole::OWNER->value,
        ]);

        // 2. Standard-Project-Slots erzeugen: To Do, Doing, On Hold
        $defaultSlots = ['To Do', 'Doing', 'On Hold'];
        foreach ($defaultSlots as $index => $name) {
            ProjectSlot::create([
                'project_id' => $project->id,
                'name' => $name,
                'order' => $index + 1,
                'user_id' => $user->id,
                'team_id' => $teamId,
            ]);
        }

        return redirect()->route('planner.projects.show', ['plannerProject' => $project->id]);
    }

    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam->id ?? null;

        if (!$user || !$teamId) {
            return view('planner::livewire.sidebar', [
                'entityTypeGroups' => collect(),
                'unlinkedProjects' => collect(),
                'hasMoreProjects' => false,
            ]);
        }

        // 1. Projekte laden (gleicher User-Filter wie bisher)
        $projectsWithUserTasks = Project::query()
            ->with(['contextColors'])
            ->where('team_id', $teamId)
            ->where(function ($query) use ($user) {
                $query->whereHas('projectSlots.tasks', function ($q) use ($user) {
                    $q->where('user_in_charge_id', $user->id)
                      ->where('is_done', false);
                })
                ->orWhereHas('tasks', function ($q) use ($user) {
                    $q->where('user_in_charge_id', $user->id)
                      ->where('is_done', false)
                      ->whereNull('project_slot_id');
                })
                ->orWhereHas('projectUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            })
            ->orderBy('name')
            ->get();

        $allProjects = Project::query()
            ->with(['contextColors'])
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        $projectsToShow = $this->showAllProjects
            ? $allProjects
            : $projectsWithUserTasks;

        $hasMoreProjects = $allProjects->count() > $projectsWithUserTasks->count();

        // 2. Entity-Verknüpfungen laden aus BEIDEN Quellen:
        //    a) OrganizationContext (UI: ModalOrganization → HasOrganizationContexts trait)
        //    b) OrganizationEntityLink (DimensionLinker / LLM Tools)
        $projectIds = $projectsToShow->pluck('id')->toArray();

        $entityProjectMap = []; // entity_id => [project_ids]
        $linkedProjectIds = [];

        // a) OrganizationContext (primäre Quelle – wird von der UI erstellt)
        $contextMorphTypes = ['planner_project', Project::class];
        $contexts = OrganizationContext::query()
            ->whereIn('contextable_type', $contextMorphTypes)
            ->whereIn('contextable_id', $projectIds)
            ->where('is_active', true)
            ->with(['organizationEntity.type'])
            ->get();

        foreach ($contexts as $ctx) {
            $entityId = $ctx->organization_entity_id;
            $projectId = $ctx->contextable_id;
            if ($entityId) {
                $entityProjectMap[$entityId][] = $projectId;
                $linkedProjectIds[] = $projectId;
            }
        }

        // b) OrganizationEntityLink (sekundäre Quelle – DimensionLinker / LLM Tools)
        $entityLinks = OrganizationEntityLink::query()
            ->whereIn('linkable_type', $contextMorphTypes)
            ->whereIn('linkable_id', $projectIds)
            ->with(['entity.type'])
            ->get();

        foreach ($entityLinks as $link) {
            $entityId = $link->entity_id;
            $projectId = $link->linkable_id;
            $entityProjectMap[$entityId][] = $projectId;
            $linkedProjectIds[] = $projectId;
        }

        // Deduplizieren
        foreach ($entityProjectMap as $entityId => $pids) {
            $entityProjectMap[$entityId] = array_unique($pids);
        }
        $linkedProjectIds = array_unique($linkedProjectIds);

        // 3. Gruppieren: EntityType → Entity → Projekte
        $entityTypeGroups = collect();

        // Entities mit Types gruppieren
        $entityIds = array_keys($entityProjectMap);
        if (!empty($entityIds)) {
            $entities = OrganizationEntity::with('type')
                ->whereIn('id', $entityIds)
                ->get()
                ->keyBy('id');

            $groupedByType = [];
            foreach ($entityProjectMap as $entityId => $projectIdsList) {
                $entity = $entities->get($entityId);
                if (!$entity || !$entity->type) {
                    continue;
                }
                $typeId = $entity->type->id;
                if (!isset($groupedByType[$typeId])) {
                    $groupedByType[$typeId] = [
                        'type_id' => $typeId,
                        'type_name' => $entity->type->name,
                        'type_icon' => $entity->type->icon,
                        'sort_order' => $entity->type->sort_order ?? 999,
                        'entities' => [],
                    ];
                }
                if (!isset($groupedByType[$typeId]['entities'][$entityId])) {
                    $groupedByType[$typeId]['entities'][$entityId] = [
                        'entity_id' => $entityId,
                        'entity_name' => $entity->name,
                        'projects' => collect(),
                    ];
                }
                foreach ($projectIdsList as $pid) {
                    $project = $projectsToShow->firstWhere('id', $pid);
                    if ($project) {
                        $groupedByType[$typeId]['entities'][$entityId]['projects']->push($project);
                    }
                }
            }

            // Sortieren und in Collection umwandeln
            $entityTypeGroups = collect($groupedByType)
                ->sortBy('sort_order')
                ->map(function ($group) {
                    $group['entities'] = collect($group['entities'])
                        ->sortBy('entity_name')
                        ->values();
                    return $group;
                })
                ->values();
        }

        // 4. Unverknüpfte Projekte
        $unlinkedProjects = $projectsToShow->filter(function ($project) use ($linkedProjectIds) {
            return !in_array($project->id, $linkedProjectIds);
        })->values();

        return view('planner::livewire.sidebar', [
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedProjects' => $unlinkedProjects,
            'hasMoreProjects' => $hasMoreProjects,
        ]);
    }
}
