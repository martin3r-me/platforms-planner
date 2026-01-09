<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Enums\ProjectRole;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool: Teilnehmer aus einem Projekt entfernen (Projekt-Teilnehmer / project_users)
 *
 * Entspricht der UI-Logik in ProjectSettingsModal::removeProjectUser():
 * - Nur Owner/Admin darf entfernen
 * - Owner kann nicht entfernt werden
 */
class RemoveProjectUserTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.project_users.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /project_users - Entfernt einen Teilnehmer aus einem Projekt. REST-Parameter: project_id (required, int), user_id (required, int). Hinweis: Owner kann nicht entfernt werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Projekts (required).',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'User-ID (required) des Teilnehmers, der entfernt werden soll.',
                ],
            ],
            'required' => ['project_id', 'user_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $projectId = isset($arguments['project_id']) ? (int) $arguments['project_id'] : 0;
            $userId = isset($arguments['user_id']) ? (int) $arguments['user_id'] : 0;

            if ($projectId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', "Feld 'project_id' ist erforderlich.");
            }
            if ($userId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', "Feld 'user_id' ist erforderlich.");
            }

            $project = PlannerProject::with(['projectUsers'])->find($projectId);
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden.');
            }

            // Policy wie UI: removeMember
            try {
                Gate::forUser($context->user)->authorize('removeMember', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Mitglieder aus diesem Projekt entfernen (Policy: removeMember).');
            }

            $ownerId = $project->projectUsers->firstWhere('role', ProjectRole::OWNER->value)?->user_id;
            if ($ownerId && $userId === (int) $ownerId) {
                return ToolResult::error('OWNER_PROTECTED', 'Owner kann nicht entfernt werden. Bitte zuerst Ownership übertragen.');
            }

            $deleted = PlannerProjectUser::query()
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->delete();

            $project->refresh()->load(['projectUsers.user']);
            $members = $project->projectUsers->map(fn ($pu) => [
                'user_id' => $pu->user_id,
                'user_name' => $pu->user?->name,
                'role' => $pu->role,
            ])->values()->all();

            return ToolResult::success([
                'project_id' => $project->id,
                'removed_user_id' => $userId,
                'removed' => $deleted > 0,
                'members' => $members,
                'message' => ($deleted > 0)
                    ? 'Teilnehmer wurde aus dem Projekt entfernt.'
                    : 'Teilnehmer war nicht im Projekt (nichts zu entfernen).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entfernen des Teilnehmers: ' . $e->getMessage());
        }
    }
}


