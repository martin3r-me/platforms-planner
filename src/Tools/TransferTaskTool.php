<?php

namespace Platform\Planner\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerTaskGroup;

/**
 * Transferiert eine Aufgabe in ein anderes Team (ändert team_id).
 *
 * Regeln (bewusst konservativ):
 * - Tasks MIT project_id: Team kommt vom Projekt. Cross-Team Transfer der Task alleine ist nicht erlaubt.
 *   -> Nutze planner.projects.TRANSFER oder planner.tasks.PUT (move to project in target team).
 * - Tasks OHNE project_id (persönlich): team_id darf transferiert werden (wenn User Zugriff auf Zielteam hat).
 * - Wenn task_group_id gesetzt ist, muss die TaskGroup im Zielteam liegen (sonst abbrechen).
 */
class TransferTaskTool implements ToolContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'planner.tasks.TRANSFER';
    }

    public function getDescription(): string
    {
        return 'TRANSFER /planner/tasks - Verschiebt eine Aufgabe in ein anderes Team (ändert team_id). Für Projekt-Aufgaben ist ein Einzel-Transfer nicht erlaubt (nutze planner.projects.TRANSFER oder planner.tasks.PUT mit project_id). Ohne confirm=true nur Preview.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Aufgabe (ERFORDERLICH). Nutze "planner.tasks.GET" um Aufgaben zu finden.',
                ],
                'target_team_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Ziel-Teams (ERFORDERLICH). Nutze "core.teams.GET" um Teams zu finden.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, um den Transfer auszuführen. Wenn false/fehlend, gibt das Tool nur eine Preview zurück.',
                ],
            ],
            'required' => ['task_id', 'target_team_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'task_id',
                PlannerTask::class,
                'TASK_NOT_FOUND',
                'Die angegebene Aufgabe wurde nicht gefunden.'
            );

            if ($validation['error']) {
                return $validation['error'];
            }

            /** @var PlannerTask $task */
            $task = $validation['model'];

            try {
                Gate::forUser($context->user)->authorize('update', $task);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst diese Aufgabe nicht transferieren (Policy update).');
            }

            $targetTeamId = (int) ($arguments['target_team_id'] ?? 0);
            if ($targetTeamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'target_team_id ist erforderlich.');
            }

            $targetTeam = $context->user?->teams()?->find($targetTeamId);
            if (!$targetTeam) {
                return ToolResult::error('ACCESS_DENIED', 'Ziel-Team nicht gefunden oder kein Zugriff darauf. Nutze "core.teams.GET".');
            }

            // Projekt-Task? => nur via Projekt-Transfer oder Move-to-Project
            if ($task->project_id) {
                return ToolResult::error(
                    'TRANSFER_NOT_ALLOWED',
                    'Diese Aufgabe gehört zu einem Projekt. Ein Einzel-Transfer der Task über team_id ist nicht erlaubt. Nutze "planner.projects.TRANSFER" für das Projekt oder "planner.tasks.PUT" und verschiebe sie in ein Projekt im Ziel-Team.'
                );
            }

            // Wenn TaskGroup gesetzt: muss im Zielteam liegen
            if ($task->task_group_id) {
                /** @var PlannerTaskGroup|null $group */
                $group = PlannerTaskGroup::find($task->task_group_id);
                if ($group && (int) $group->team_id !== (int) $targetTeamId) {
                    return ToolResult::error(
                        'TEAM_MISMATCH',
                        'Die Aufgabe hängt an einer TaskGroup aus einem anderen Team. Entferne zuerst task_group_id (planner.tasks.PUT) oder transferiere die Gruppe (noch nicht implementiert).'
                    );
                }
            }

            if ((int) $task->team_id === (int) $targetTeamId) {
                return ToolResult::success([
                    'task_id' => $task->id,
                    'team_id' => $task->team_id,
                    'message' => 'Kein Transfer nötig: Aufgabe ist bereits im Ziel-Team.',
                ]);
            }

            $preview = [
                'source_team_id' => $task->team_id,
                'target_team_id' => $targetTeamId,
                'project_id' => $task->project_id,
                'project_slot_id' => $task->project_slot_id,
                'sprint_slot_id' => $task->sprint_slot_id,
                'task_group_id' => $task->task_group_id,
            ];

            $confirm = (bool) ($arguments['confirm'] ?? false);
            if (!$confirm) {
                return ToolResult::success([
                    'preview' => $preview,
                    'requires_confirmation' => true,
                    'message' => 'Preview: Transfer wird NICHT ausgeführt. Sende confirm=true, um den Transfer auszuführen.',
                ]);
            }

            $task->update(['team_id' => $targetTeamId]);
            $task->refresh();

            return ToolResult::success([
                'task_id' => $task->id,
                'team_id' => $task->team_id,
                'preview' => $preview,
                'message' => 'Aufgabe wurde erfolgreich in das Ziel-Team transferiert (team_id aktualisiert).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Task-Transfer: ' . $e->getMessage());
        }
    }
}


