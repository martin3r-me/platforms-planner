<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerRecurringTask;
use Illuminate\Support\Facades\Gate;

/**
 * Tool zum Auflisten/Finden wiederkehrender Aufgaben-Vorlagen (PlannerRecurringTask).
 * Für eine einzelne Vorlage: filters mit field="id", op="eq", value=<id>.
 */
class ListRecurringTasksTool implements ToolContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'planner.recurring_tasks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /recurring-tasks - Listet wiederkehrende Aufgaben-Vorlagen auf. Filter: project_id, user_in_charge_id (Default: aktueller User), is_active, filters (z.B. field="id" für Einzel-Abruf), search, sort, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'project_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Projekt-ID. Nutze "planner.projects.GET" um Projekt-IDs zu finden.'
                    ],
                    'user_in_charge_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach verantwortlichem User. Wenn nicht angegeben, werden Vorlagen des aktuellen Users angezeigt.'
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach Aktiv-Status (pausierte Vorlagen haben is_active=false und erzeugen keine neuen Tasks mehr).'
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            if (!empty($arguments['project_id'])) {
                $project = PlannerProject::withStale()->find($arguments['project_id']);
                if (!$project) {
                    return ToolResult::error('PROJECT_NOT_FOUND', 'Das angegebene Projekt wurde nicht gefunden. Nutze "planner.projects.GET".');
                }
                if (!Gate::forUser($context->user)->allows('view', $project)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Projekt (Policy).');
                }
            }

            $query = PlannerRecurringTask::query()
                ->with(['project', 'projectSlot', 'user', 'userInCharge']);

            $hasIdFilter = $this->hasFilterForField($arguments['filters'] ?? [], 'id');

            if (!empty($arguments['project_id'])) {
                $query->where('project_id', $arguments['project_id']);
            }

            $userInChargeId = null;
            if (isset($arguments['user_in_charge_id']) && $arguments['user_in_charge_id'] !== 0 && $arguments['user_in_charge_id'] !== '0') {
                $userInChargeId = (int) $arguments['user_in_charge_id'];
            }
            $hasUserFilterInStandard = $this->hasFilterForField($arguments['filters'] ?? [], 'user_in_charge_id');

            if ($userInChargeId !== null && !$hasUserFilterInStandard) {
                $query->where('user_in_charge_id', $userInChargeId);
            } elseif ($userInChargeId === null && !$hasUserFilterInStandard && empty($arguments['project_id']) && !$hasIdFilter) {
                $query->where('user_in_charge_id', $context->user->id);
            }

            if (isset($arguments['is_active']) && $arguments['is_active'] !== null) {
                $query->where('is_active', (bool) $arguments['is_active']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'id', 'project_id', 'project_slot_id', 'user_in_charge_id', 'is_active',
                'recurrence_type', 'title', 'description', 'next_due_date', 'created_at', 'updated_at',
            ]);

            $this->applyStandardSearch($query, $arguments, ['title', 'description']);

            $this->applyStandardSort($query, $arguments, [
                'title', 'next_due_date', 'created_at', 'updated_at', 'recurrence_type', 'is_active',
            ], 'next_due_date', 'asc');

            $this->applyStandardPagination($query, $arguments);

            $recurring = $query->get()
                ->filter(fn ($r) => Gate::forUser($context->user)->allows('view', $r))
                ->values();

            $list = $recurring->map(function (PlannerRecurringTask $r) {
                return [
                    'id' => $r->id,
                    'uuid' => $r->uuid,
                    'title' => $r->title,
                    'description' => $r->description,
                    'recurrence_type' => $r->recurrence_type,
                    'recurrence_interval' => $r->recurrence_interval,
                    'weekday_mask' => $r->weekday_mask,
                    'next_due_date' => optional($r->next_due_date)->toIso8601String(),
                    'recurrence_end_date' => optional($r->recurrence_end_date)->toIso8601String(),
                    'is_active' => (bool) $r->is_active,
                    'auto_delete_old_tasks' => (bool) $r->auto_delete_old_tasks,
                    'auto_mark_as_done' => (bool) $r->auto_mark_as_done,
                    'lead_time_days' => $r->lead_time_days,
                    'max_occurrences' => $r->max_occurrences,
                    'occurrences_count' => $r->occurrences_count,
                    'skip_weekends' => (bool) $r->skip_weekends,
                    'project_id' => $r->project_id,
                    'project_name' => $r->project?->name,
                    'project_slot_id' => $r->project_slot_id,
                    'user_id' => $r->user_id,
                    'user_in_charge_id' => $r->user_in_charge_id,
                    'user_in_charge_name' => $r->userInCharge?->name ?? 'Unbekannt',
                    'created_at' => $r->created_at->toIso8601String(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'recurring_tasks' => $list,
                'count' => count($list),
                'message' => count($list) > 0
                    ? count($list) . ' wiederkehrende Aufgabe(n) gefunden.'
                    : 'Keine wiederkehrenden Aufgaben gefunden.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der wiederkehrenden Aufgaben: ' . $e->getMessage());
        }
    }

    private function hasFilterForField(array $filters, string $field): bool
    {
        foreach ($filters as $filter) {
            if (($filter['field'] ?? null) === $field) {
                return true;
            }
        }
        return false;
    }
}
