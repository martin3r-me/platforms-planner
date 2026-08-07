<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerTask;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Verwalten von Task-Abhängigkeiten (Finish-to-Start).
 *
 * Eine Kante sagt „task_id hängt an blocker_task_id" — der abhängige Task wird
 * erst ausführbar, wenn der Vorgänger terminal ist (erledigt/verworfen). Kanten
 * dürfen quer über Slots und Projekte gehen; Zyklen werden abgelehnt.
 */
class SetTaskDependencyTool implements ToolContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'planner.tasks.dependencies.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /tasks/{id}/dependencies - Verwaltet Abhängigkeiten (blockiert-von) einer Aufgabe. '
            .'action=add verknüpft blocker_task_id als Vorgänger: task_id wird erst ausführbar, wenn der '
            .'Vorgänger erledigt/verworfen ist. action=remove löst die Verknüpfung. Kanten dürfen quer über '
            .'Slots/Projekte gehen. Zyklen (A→B→A) werden abgelehnt. Für Phasen-Sequenzen innerhalb eines '
            .'Projekts ist stattdessen das Slot-Gate (blocked_until_previous_done am Slot) meist die bessere Wahl.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => [
                    'type' => 'integer',
                    'description' => 'ID der abhängigen Aufgabe (die wartet). Nutze "planner.tasks.GET" zum Finden.',
                ],
                'blocker_task_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Vorgänger-Aufgabe, die zuerst fertig sein muss.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['add', 'remove'],
                    'description' => 'add = Abhängigkeit anlegen (Default), remove = entfernen.',
                ],
            ],
            'required' => ['task_id', 'blocker_task_id'],
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
            $task = $validation['model'];

            try {
                Gate::forUser($context->user)->authorize('update', $task);
            } catch (AuthorizationException) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, diese Aufgabe zu bearbeiten (Policy).');
            }

            $blockerId = (int) ($arguments['blocker_task_id'] ?? 0);
            if ($blockerId < 1) {
                return ToolResult::error('VALIDATION_ERROR', 'blocker_task_id ist erforderlich.');
            }
            $blocker = PlannerTask::find($blockerId);
            if (! $blocker) {
                return ToolResult::error('BLOCKER_NOT_FOUND', 'Die Vorgänger-Aufgabe wurde nicht gefunden.');
            }
            // Sichtbarkeit des Vorgängers wie im UI (Ersteller/Zuständiger/erreichbares Projekt).
            $visible = PlannerTask::query()->visibleTo($context->user)->whereKey($blockerId)->exists();
            if (! $visible) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf die angegebene Vorgänger-Aufgabe.');
            }

            $action = strtolower(trim((string) ($arguments['action'] ?? 'add')));

            if ($action === 'remove') {
                $task->removeBlocker($blocker);
            } else {
                try {
                    $task->addBlocker($blocker);
                } catch (\InvalidArgumentException $e) {
                    return ToolResult::error('INVALID_DEPENDENCY', $e->getMessage());
                }
            }

            $task->refresh();

            return ToolResult::success([
                'task_id' => $task->id,
                'action' => $action === 'remove' ? 'removed' : 'added',
                'is_blocked' => $task->isBlocked(),
                'blockers' => $task->blockers()->get(['planner_tasks.id', 'planner_tasks.title', 'planner_tasks.lifecycle_state'])
                    ->map(fn ($b) => [
                        'id' => $b->id,
                        'title' => $b->title,
                        'lifecycle_state' => $b->lifecycle_state?->value,
                    ])->values()->all(),
                'message' => $action === 'remove'
                    ? "Abhängigkeit von Aufgabe #{$blocker->id} entfernt."
                    : "Aufgabe #{$task->id} hängt jetzt an Aufgabe #{$blocker->id}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Verwalten der Abhängigkeit: ' . $e->getMessage());
        }
    }
}
