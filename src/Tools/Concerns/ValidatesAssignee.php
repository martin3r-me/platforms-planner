<?php

namespace Platform\Planner\Tools\Concerns;

use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\User;

/**
 * Zustaendige (user_in_charge_id) muessen existieren und Mitglied des Teams sein,
 * in dem die Aufgabe liegt — sonst entstehen Aufgaben, die niemand sieht.
 */
trait ValidatesAssignee
{
    protected function assigneeError(?int $userId, ?int $teamId): ?ToolResult
    {
        if (! $userId) {
            return null;
        }
        $user = User::find($userId);
        if (! $user) {
            return ToolResult::error('VALIDATION_ERROR', "user_in_charge_id {$userId} existiert nicht.");
        }
        if ($teamId && ! $user->teams()->where('teams.id', $teamId)->exists()) {
            return ToolResult::error('VALIDATION_ERROR', "{$user->name} ist kein Mitglied des Teams dieser Aufgabe und kann nicht zuständig sein.");
        }

        return null;
    }
}
