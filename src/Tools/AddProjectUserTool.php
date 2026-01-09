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
 * Tool: Team-Mitglied zu einem Projekt hinzufügen (Projekt-Teilnehmer / project_users)
 *
 * Entspricht der UI-Logik in ProjectSettingsModal::addProjectUser():
 * - Nur Owner/Admin darf einladen
 * - Owner kann nicht per Tool gesetzt werden (Ownership-Transfer separat)
 * - Nur Team-Mitglieder des Projekt-Teams dürfen hinzugefügt werden (Team-Scope)
 */
class AddProjectUserTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.project_users.POST';
    }

    public function getDescription(): string
    {
        return 'POST /project_users - Fügt ein Team-Mitglied als Teilnehmer zu einem Projekt hinzu. REST-Parameter: project_id (required, int), user_id (required, int), role (optional: admin|member|viewer; default member). Hinweis: Owner kann hier nicht gesetzt werden (Ownership-Transfer ist separat).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Projekts (required). Nutze planner.projects.GET oder planner.project.GET, um die ID zu finden.',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'User-ID (required) des Team-Mitglieds, das dem Projekt hinzugefügt werden soll.',
                ],
                'role' => [
                    'type' => 'string',
                    'description' => 'Optional: Rolle im Projekt. Erlaubt: admin, member, viewer. Default: member.',
                    'enum' => [ProjectRole::ADMIN->value, ProjectRole::MEMBER->value, ProjectRole::VIEWER->value],
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
            $role = (string) ($arguments['role'] ?? ProjectRole::MEMBER->value);

            if ($projectId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', "Feld 'project_id' ist erforderlich.");
            }
            if ($userId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', "Feld 'user_id' ist erforderlich.");
            }

            // Role validate (no owner here)
            $allowed = [ProjectRole::ADMIN->value, ProjectRole::MEMBER->value, ProjectRole::VIEWER->value];
            if (!in_array($role, $allowed, true)) {
                return ToolResult::error('VALIDATION_ERROR', "Ungültige Rolle '{$role}'. Erlaubt: admin, member, viewer.");
            }

            $project = PlannerProject::with(['team', 'projectUsers'])->find($projectId);
            if (!$project) {
                return ToolResult::error('PROJECT_NOT_FOUND', 'Projekt nicht gefunden.');
            }

            // Policy wie UI: invite
            try {
                Gate::forUser($context->user)->authorize('invite', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Mitglieder zu diesem Projekt hinzufügen (Policy: invite).');
            }

            // Team-scope: target user must be member of the project's team
            if (!$project->team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Projekt-Team konnte nicht geladen werden.');
            }
            $isInTeam = $project->team->users()->where('users.id', $userId)->exists();
            if (!$isInTeam) {
                return ToolResult::error('USER_NOT_IN_TEAM', 'Der angegebene User ist kein Mitglied des Projekt-Teams.');
            }

            $existing = PlannerProjectUser::query()
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                return ToolResult::success([
                    'project_id' => $project->id,
                    'user_id' => $userId,
                    'role' => $existing->role,
                    'already_member' => true,
                    'message' => 'User ist bereits Teilnehmer dieses Projekts.',
                ]);
            }

            PlannerProjectUser::create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'role' => $role,
            ]);

            $project->refresh()->load(['projectUsers.user']);
            $members = $project->projectUsers->map(fn ($pu) => [
                'user_id' => $pu->user_id,
                'user_name' => $pu->user?->name,
                'role' => $pu->role,
            ])->values()->all();

            return ToolResult::success([
                'project_id' => $project->id,
                'added_user_id' => $userId,
                'role' => $role,
                'members' => $members,
                'message' => 'Teilnehmer wurde zum Projekt hinzugefügt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Hinzufügen des Teilnehmers: ' . $e->getMessage());
        }
    }
}


