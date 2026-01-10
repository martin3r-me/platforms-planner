<?php

namespace Platform\Planner\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerSprint;
use Platform\Planner\Models\PlannerSprintSlot;
use Platform\Planner\Models\PlannerTask;

/**
 * Verschiebt ein Projekt (und abhängige Entities) in ein anderes Team, indem team_id angepasst wird.
 *
 * WICHTIG:
 * - Cross-Team Move ist ein "Transfer" und NICHT nur ein normales Update.
 * - Require confirm=true, sonst nur Preview.
 */
class TransferProjectTool implements ToolContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'planner.projects.TRANSFER';
    }

    public function getDescription(): string
    {
        return 'TRANSFER /planner/projects - Verschiebt ein Projekt in ein anderes Team (ändert team_id) und aktualisiert abhängige Daten (Slots, Tasks, Sprints). Ohne confirm=true wird nur eine Preview zurückgegeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Projekts (ERFORDERLICH). Nutze "planner.projects.GET" um Projekte zu finden.',
                ],
                'target_team_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Ziel-Teams (ERFORDERLICH). Nutze "core.teams.GET" um Teams zu finden.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, um den Transfer wirklich auszuführen. Wenn false/fehlend, gibt das Tool nur eine Preview zurück.',
                ],
            ],
            'required' => ['project_id', 'target_team_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'project_id',
                PlannerProject::class,
                'PROJECT_NOT_FOUND',
                'Das angegebene Projekt wurde nicht gefunden.'
            );

            if ($validation['error']) {
                return $validation['error'];
            }

            /** @var PlannerProject $project */
            $project = $validation['model'];

            try {
                Gate::forUser($context->user)->authorize('update', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Projekt nicht transferieren (Policy update).');
            }

            $targetTeamId = (int) ($arguments['target_team_id'] ?? 0);
            if ($targetTeamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'target_team_id ist erforderlich.');
            }

            // Zielteam muss für User zugreifbar sein (analog CreateProjectTool)
            $targetTeam = $context->user?->teams()?->find($targetTeamId);
            if (!$targetTeam) {
                return ToolResult::error('ACCESS_DENIED', 'Ziel-Team nicht gefunden oder kein Zugriff darauf. Nutze "core.teams.GET" und frage nach der Team-ID.');
            }

            $sourceTeamId = $project->team_id;
            if ((int) $sourceTeamId === (int) $targetTeamId) {
                return ToolResult::success([
                    'project_id' => $project->id,
                    'team_id' => $project->team_id,
                    'message' => 'Kein Transfer nötig: Projekt ist bereits im Ziel-Team.',
                ]);
            }

            // Preview: wie viele Datensätze sind betroffen?
            $projectSlotsCount = PlannerProjectSlot::where('project_id', $project->id)->count();
            $tasksCount = PlannerTask::where('project_id', $project->id)->count();
            $sprintsCount = PlannerSprint::where('project_id', $project->id)->count();
            $sprintSlotCount = PlannerSprintSlot::whereHas('sprint', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            })->count();

            $preview = [
                'source_team_id' => $sourceTeamId,
                'target_team_id' => $targetTeamId,
                'project_slots' => $projectSlotsCount,
                'tasks' => $tasksCount,
                'sprints' => $sprintsCount,
                'sprint_slots' => $sprintSlotCount,
            ];

            $confirm = (bool) ($arguments['confirm'] ?? false);
            if (!$confirm) {
                return ToolResult::success([
                    'preview' => $preview,
                    'requires_confirmation' => true,
                    'message' => 'Preview: Transfer wird NICHT ausgeführt. Sende confirm=true, um den Transfer auszuführen.',
                ]);
            }

            DB::transaction(function () use ($project, $targetTeamId) {
                $project->update(['team_id' => $targetTeamId]);

                PlannerProjectSlot::where('project_id', $project->id)->update(['team_id' => $targetTeamId]);
                PlannerTask::where('project_id', $project->id)->update(['team_id' => $targetTeamId]);

                PlannerSprint::where('project_id', $project->id)->update(['team_id' => $targetTeamId]);
                PlannerSprintSlot::whereHas('sprint', function ($q) use ($project) {
                    $q->where('project_id', $project->id);
                })->update(['team_id' => $targetTeamId]);
            });

            $project->refresh();

            return ToolResult::success([
                'project_id' => $project->id,
                'team_id' => $project->team_id,
                'preview' => $preview,
                'message' => 'Projekt wurde erfolgreich in das Ziel-Team transferiert (inkl. Slots/Tasks/Sprints). Hinweis: Zeitbuchungen bleiben im ursprünglichen Team.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Projekt-Transfer: ' . $e->getMessage());
        }
    }
}


