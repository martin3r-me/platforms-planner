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
use Platform\Planner\Models\PlannerTask;

/**
 * Verschiebt einen ProjectSlot in ein anderes Projekt.
 *
 * Wichtig:
 * - Slot gehört immer zu genau einem Projekt (project_id).
 * - Beim Move müssen auch Tasks im Slot angepasst werden (project_id + team_id),
 *   sonst wären sie inkonsistent (Slot in Projekt A, Task.project_id noch Projekt B).
 *
 * Ohne confirm=true wird nur eine Preview zurückgegeben.
 */
class MoveProjectSlotTool implements ToolContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'planner.project_slots.MOVE';
    }

    public function getDescription(): string
    {
        return 'MOVE /planner/project_slots - Verschiebt einen Slot in ein anderes Projekt (ändert project_id) und zieht Tasks im Slot mit. Ohne confirm=true gibt es nur eine Preview.';
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
                'target_project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Ziel-Projekts (ERFORDERLICH). Nutze "planner.projects.GET" um Projekte zu finden.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, um den Move wirklich auszuführen. Wenn false/fehlend, gibt das Tool nur eine Preview zurück.',
                ],
            ],
            'required' => ['project_slot_id', 'target_project_id'],
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

            $sourceProject = $slot->project;
            if (!$sourceProject) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Das zugehörige Projekt wurde nicht gefunden.');
            }

            $targetProjectId = (int) ($arguments['target_project_id'] ?? 0);
            if ($targetProjectId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'target_project_id ist erforderlich.');
            }

            /** @var PlannerProject|null $targetProject */
            $targetProject = PlannerProject::find($targetProjectId);
            if (!$targetProject) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Das Ziel-Projekt wurde nicht gefunden.');
            }

            if ((int) $slot->project_id === (int) $targetProject->id) {
                return ToolResult::success([
                    'slot_id' => $slot->id,
                    'project_id' => $slot->project_id,
                    'message' => 'Kein Move nötig: Slot ist bereits im Ziel-Projekt.',
                ]);
            }

            // Policy: Slot-Änderungen sind update am Quellprojekt
            try {
                Gate::forUser($context->user)->authorize('update', $sourceProject);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst das Quell-Projekt nicht bearbeiten (Policy).');
            }

            // Policy: Move in Zielprojekt erfordert update am Zielprojekt
            try {
                Gate::forUser($context->user)->authorize('update', $targetProject);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst das Ziel-Projekt nicht bearbeiten (Policy).');
            }

            $tasksInSlotCount = PlannerTask::where('project_slot_id', $slot->id)->count();

            $preview = [
                'slot_id' => $slot->id,
                'slot_name' => $slot->name,
                'source_project_id' => $sourceProject->id,
                'source_project_name' => $sourceProject->name,
                'source_team_id' => $sourceProject->team_id,
                'target_project_id' => $targetProject->id,
                'target_project_name' => $targetProject->name,
                'target_team_id' => $targetProject->team_id,
                'tasks_in_slot' => $tasksInSlotCount,
                'notes' => [
                    'Tasks im Slot werden auf target_project_id gesetzt und erben team_id vom Ziel-Projekt.',
                    'Sprint-Zuordnung der Tasks (sprint_slot_id) wird beim Move entfernt (null), weil Sprints projektspezifisch sind.',
                ],
            ];

            $confirm = (bool) ($arguments['confirm'] ?? false);
            if (!$confirm) {
                return ToolResult::success([
                    'preview' => $preview,
                    'requires_confirmation' => true,
                    'message' => 'Preview: Move wird NICHT ausgeführt. Sende confirm=true, um den Slot (inkl. Tasks) in das Ziel-Projekt zu verschieben.',
                ]);
            }

            DB::transaction(function () use ($slot, $targetProject) {
                // Slot umhängen
                $slot->update([
                    'project_id' => $targetProject->id,
                    'team_id' => $targetProject->team_id,
                ]);

                // Tasks im Slot umhängen (Projekt + Team) und Sprint-Infos resetten
                PlannerTask::where('project_slot_id', $slot->id)->update([
                    'project_id' => $targetProject->id,
                    'team_id' => $targetProject->team_id,
                    'sprint_slot_id' => null,
                    'sprint_slot_order' => 0,
                ]);
            });

            $slot->refresh();

            return ToolResult::success([
                'slot_id' => $slot->id,
                'project_id' => $slot->project_id,
                'team_id' => $slot->team_id,
                'preview' => $preview,
                'message' => "Slot '{$slot->name}' wurde erfolgreich in das Projekt '{$targetProject->name}' verschoben (inkl. Tasks).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Slot-Move: ' . $e->getMessage());
        }
    }
}


