<?php

namespace Platform\Planner\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;

/**
 * Transferiert einen Project-Slot.
 *
 * NOTE:
 * - Ein Slot gehört immer zu einem Projekt. Cross-Team Transfer eines Slots ohne Projekt-Transfer ist inkonsistent.
 * - Daher ist Transfer nur erlaubt, wenn Ziel-Team == Projekt.team_id (oder wenn man stattdessen planner.projects.TRANSFER nutzt).
 */
class TransferProjectSlotTool implements ToolContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'planner.project_slots.TRANSFER';
    }

    public function getDescription(): string
    {
        return 'TRANSFER /planner/project_slots - Passt team_id eines Project-Slots an (inkl. Tasks im Slot). Cross-Team Transfer ist nur erlaubt, wenn das zugehörige Projekt bereits im Ziel-Team ist. Ohne confirm=true nur Preview.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_slot_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Slots (ERFORDERLICH). Nutze "planner.project_slots.GET" um Slots zu finden.',
                ],
                'target_team_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Ziel-Teams (ERFORDERLICH). Muss dem Team des Projekts entsprechen.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, um den Transfer auszuführen. Wenn false/fehlend, gibt das Tool nur eine Preview zurück.',
                ],
            ],
            'required' => ['project_slot_id', 'target_team_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'project_slot_id',
                PlannerProjectSlot::class,
                'SLOT_NOT_FOUND',
                'Der angegebene Slot wurde nicht gefunden.'
            );

            if ($validation['error']) {
                return $validation['error'];
            }

            /** @var PlannerProjectSlot $slot */
            $slot = $validation['model'];
            $slot->load('project');

            if (!$slot->project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Slot hat kein zugeordnetes Projekt.');
            }

            // Slot-Änderungen sind update am Projekt (wie UI)
            try {
                Gate::forUser($context->user)->authorize('update', $slot->project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Slot nicht transferieren (Policy update am Projekt).');
            }

            $targetTeamId = (int) ($arguments['target_team_id'] ?? 0);
            if ($targetTeamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'target_team_id ist erforderlich.');
            }

            // Zielteam muss für User zugreifbar sein
            $targetTeam = $context->user?->teams()?->find($targetTeamId);
            if (!$targetTeam) {
                return ToolResult::error('ACCESS_DENIED', 'Ziel-Team nicht gefunden oder kein Zugriff darauf. Nutze "core.teams.GET".');
            }

            $projectTeamId = (int) ($slot->project->team_id ?? 0);
            if ($projectTeamId !== $targetTeamId) {
                return ToolResult::error(
                    'TEAM_MISMATCH',
                    'Cross-Team Transfer eines Slots ist nicht erlaubt, solange das Projekt nicht im Ziel-Team ist. Nutze stattdessen "planner.projects.TRANSFER" für das Projekt.'
                );
            }

            $tasksInSlot = PlannerTask::where('project_slot_id', $slot->id)->count();
            $preview = [
                'slot_id' => $slot->id,
                'source_team_id' => $slot->team_id,
                'target_team_id' => $targetTeamId,
                'tasks_in_slot' => $tasksInSlot,
            ];

            $confirm = (bool) ($arguments['confirm'] ?? false);
            if (!$confirm) {
                return ToolResult::success([
                    'preview' => $preview,
                    'requires_confirmation' => true,
                    'message' => 'Preview: Transfer wird NICHT ausgeführt. Sende confirm=true, um den Transfer auszuführen.',
                ]);
            }

            DB::transaction(function () use ($slot, $targetTeamId) {
                $slot->update(['team_id' => $targetTeamId]);
                PlannerTask::where('project_slot_id', $slot->id)->update(['team_id' => $targetTeamId]);
            });

            $slot->refresh();

            return ToolResult::success([
                'slot_id' => $slot->id,
                'team_id' => $slot->team_id,
                'preview' => $preview,
                'message' => 'Slot wurde erfolgreich (team_id) aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Slot-Transfer: ' . $e->getMessage());
        }
    }
}


