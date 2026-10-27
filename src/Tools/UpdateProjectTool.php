<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Enums\ProjectType;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Models\OrganizationTimePeriod;
use Platform\Organization\Services\StorePlannedTime;
use Platform\Organization\Services\StorePlannedPeriod;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Bearbeiten von Projekten im Planner-Modul
 */
class UpdateProjectTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    public function getName(): string
    {
        return 'planner.projects.PUT';
    }

    public function getDescription(): string
    {
        return 'Aktualisiert ein Projekt (Stammdaten, Lebenszyklus, Plan/Budget). Pflicht: project_id. '
            . 'Lebenszyklus über lifecycle_state (aktiv/abgeschlossen/verworfen; "ruhend" setzt das System). '
            . 'Owner-Wechsel braucht Manage-Recht. Zugriff/Sichtbarkeit laufen über den Organisations-Graphen, nicht über dieses Tool. '
            . 'Kickoff/Ist-Zustand → planner.canvas.*, Entscheidungen/Meilensteine/Risiken → planner.project.logbook.*.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu bearbeitenden Projekts (ERFORDERLICH). Nutze "planner.projects.GET" um Projekte zu finden.'
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name des Projekts. Frage nach, wenn der Nutzer den Namen ändern möchte.'
                ],
                'description' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional: Neue Beschreibung des Projekts (null oder "" leert sie).'
                ],
                'project_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Projekttyp. Mögliche Werte: "internal", "customer", "event", "cooking". Frage nach, wenn der Nutzer den Typ ändern möchte.',
                    'enum' => ['internal', 'customer', 'event', 'cooking']
                ],
                'kind' => [
                    'type' => 'string',
                    'description' => 'Optional: Wesensart. "project" (abgegrenzt, hat Ende) oder "run" (laeuft fortlaufend).',
                    'enum' => ['project', 'run'],
                ],
                'lifecycle_state' => [
                    'type' => 'string',
                    'description' => 'Optional: Lebenszyklus setzen. "abgeschlossen" = Ziel erreicht, "verworfen" = ohne Ergebnis beendet (offene Aufgaben werden mit verworfen), "aktiv" = wieder öffnen. Vorher beim Nutzer rückfragen.',
                    'enum' => ['aktiv', 'abgeschlossen', 'verworfen'],
                ],
                'owner_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Owner-User-ID (Owner = Ersteller mit Manage-Recht). Erfordert selbst Manage-Recht; der User muss Mitglied des Projekt-Teams sein.'
                ],
                'planned_minutes' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue geplante Minuten. Frage nach, wenn der Nutzer das Zeitbudget ändern möchte.'
                ],
                'planned_start' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer geplanter Projektstart (Datum im Format YYYY-MM-DD, "" zum Leeren). Frage nach, wenn der Nutzer das Startdatum ändern möchte.'
                ],
                'planned_end' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues geplantes Projektende (Datum im Format YYYY-MM-DD, "" zum Leeren). Frage nach, wenn der Nutzer das Enddatum ändern möchte.'
                ],
                'estimated_hours' => [
                    'type' => 'number',
                    'description' => 'Optional: Neue geschätzte Stunden (Dezimalzahl, z.B. 40.5). Frage nach, wenn der Nutzer die Stundenabschätzung ändern möchte.'
                ],
                'customer_cost_center' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Kostenstelle für Kundenprojekte. Frage nach, wenn der Nutzer die Kostenstelle ändern möchte.'
                ],
                'billing_method' => [
                    'type' => 'string',
                    'description' => 'Optional: Abrechnungsmethode. Mögliche Werte: "time_and_material", "fixed_price", "retainer".',
                    'enum' => ['time_and_material', 'fixed_price', 'retainer']
                ],
                'hourly_rate' => [
                    'type' => 'number',
                    'description' => 'Optional: Stundensatz (Dezimalzahl, z.B. 120.00).'
                ],
                'budget_amount' => [
                    'type' => 'number',
                    'description' => 'Optional: Budget-Betrag (Dezimalzahl, z.B. 10000.00).'
                ],
                'currency' => [
                    'type' => 'string',
                    'description' => 'Optional: Währung (3-Buchstaben ISO-Code, z.B. "EUR", "USD").'
                ],
            ],
            'required' => ['project_id']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Nutze standardisierte ID-Validierung (loose coupled - optional)
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
            
            $project = $validation['model'];
            
            // Policy wie UI: Project::mount authorize('view'), mutierende Aktionen authorize('update')
            try {
                Gate::forUser($context->user)->authorize('update', $project);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Projekt nicht bearbeiten (Policy).');
            }

            // Update-Daten sammeln
            $updateData = [];

            if (isset($arguments['name'])) {
                $updateData['name'] = $arguments['name'];
            }

            if (array_key_exists('description', $arguments)) {
                $desc = trim((string) ($arguments['description'] ?? ''));
                $updateData['description'] = $desc === '' ? null : $desc;
            }

            if (isset($arguments['project_type'])) {
                $projectType = ProjectType::tryFrom($arguments['project_type']);
                if (! $projectType) {
                    return ToolResult::error('VALIDATION_ERROR', 'project_type muss einer von internal, customer, event, cooking sein.');
                }
                $updateData['project_type'] = $projectType;
            }

            if (isset($arguments['kind'])) {
                $kindEnum = \Platform\Planner\Enums\ProjectKind::tryFrom($arguments['kind']);
                if (! $kindEnum) {
                    return ToolResult::error('VALIDATION_ERROR', 'kind muss "project" oder "run" sein.');
                }
                $updateData['kind'] = $kindEnum;
            }

            // Lebenszyklus: lifecycle_state (done=true/false bleibt als Alt-Alias erhalten)
            $targetLifecycle = null;
            if (isset($arguments['lifecycle_state'])) {
                $targetLifecycle = \Platform\Planner\Enums\ProjectLifecycleState::tryFrom((string) $arguments['lifecycle_state']);
                if (! $targetLifecycle || $targetLifecycle === \Platform\Planner\Enums\ProjectLifecycleState::DORMANT) {
                    return ToolResult::error('VALIDATION_ERROR', 'lifecycle_state muss "aktiv", "abgeschlossen" oder "verworfen" sein.');
                }
            } elseif (isset($arguments['done'])) {
                $targetLifecycle = $arguments['done']
                    ? \Platform\Planner\Enums\ProjectLifecycleState::COMPLETED
                    : \Platform\Planner\Enums\ProjectLifecycleState::ACTIVE;
            }

            // Owner-Wechsel = Uebergabe der Manage-Rechte → eigene Policy-Schwelle
            if (isset($arguments['owner_user_id']) && (int) $arguments['owner_user_id'] !== (int) $project->user_id) {
                if (! Gate::forUser($context->user)->allows('transferOwnership', $project)) {
                    return ToolResult::error('ACCESS_DENIED', 'Owner-Wechsel erfordert Manage-Recht am Projekt.');
                }
                $newOwner = \Platform\Core\Models\User::find((int) $arguments['owner_user_id']);
                if (! $newOwner || ! $newOwner->teams()->where('teams.id', $project->team_id)->exists()) {
                    return ToolResult::error('VALIDATION_ERROR', 'owner_user_id ist kein Mitglied des Projekt-Teams.');
                }
            }

            // planned_minutes und estimated_hours → zentrales System
            $newPlannedMinutes = null;
            if (isset($arguments['planned_minutes'])) {
                $newPlannedMinutes = (int) $arguments['planned_minutes'];
            } elseif (isset($arguments['estimated_hours'])) {
                $newPlannedMinutes = (int) round((float) $arguments['estimated_hours'] * 60);
            }

            if ($newPlannedMinutes !== null) {
                if ($newPlannedMinutes > 0) {
                    $existing = OrganizationTimePlanned::where('context_type', PlannerProject::class)
                        ->where('context_id', $project->id)
                        ->where('is_active', true)
                        ->first();

                    if ($existing) {
                        app(StorePlannedTime::class)->update($existing, ['planned_minutes' => $newPlannedMinutes]);
                    } else {
                        app(StorePlannedTime::class)->store([
                            'team_id' => $project->team_id,
                            'user_id' => $context->user->id,
                            'context_type' => PlannerProject::class,
                            'context_id' => $project->id,
                            'planned_minutes' => $newPlannedMinutes,
                            'note' => null,
                            'is_active' => true,
                        ]);
                    }
                } else {
                    OrganizationTimePlanned::where('context_type', PlannerProject::class)
                        ->where('context_id', $project->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }
            }

            // Soll-Zeitraum über zentrales System speichern
            if (array_key_exists('planned_start', $arguments) || array_key_exists('planned_end', $arguments)) {
                $existing = OrganizationTimePeriod::where('context_type', PlannerProject::class)
                    ->where('context_id', $project->id)
                    ->where('is_active', true)
                    ->first();

                $periodData = [];
                if (array_key_exists('planned_start', $arguments)) {
                    $val = (string) ($arguments['planned_start'] ?? '');
                    $periodData['planned_start'] = $val === '' ? null : $val;
                }
                if (array_key_exists('planned_end', $arguments)) {
                    $val = (string) ($arguments['planned_end'] ?? '');
                    $periodData['planned_end'] = $val === '' ? null : $val;
                }

                if ($existing) {
                    app(StorePlannedPeriod::class)->update($existing, $periodData);
                } else {
                    $start = $periodData['planned_start'] ?? null;
                    $end = $periodData['planned_end'] ?? null;
                    if ($start || $end) {
                        app(StorePlannedPeriod::class)->store([
                            'team_id' => $project->team_id,
                            'user_id' => $context->user->id,
                            'context_type' => PlannerProject::class,
                            'context_id' => $project->id,
                            'planned_start' => $start,
                            'planned_end' => $end,
                            'note' => null,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            if (isset($arguments['customer_cost_center'])) {
                $updateData['customer_cost_center'] = $arguments['customer_cost_center'];
            }

            if (isset($arguments['billing_method'])) {
                $updateData['billing_method'] = $arguments['billing_method'];
            }

            if (isset($arguments['hourly_rate'])) {
                $updateData['hourly_rate'] = $arguments['hourly_rate'];
            }

            if (isset($arguments['budget_amount'])) {
                $updateData['budget_amount'] = $arguments['budget_amount'];
            }

            if (isset($arguments['currency'])) {
                $updateData['currency'] = $arguments['currency'];
            }

            if (isset($arguments['owner_user_id'])) {
                // Ersteller/Owner = user_id-Spalte (oben autorisiert + validiert).
                $updateData['user_id'] = (int) $arguments['owner_user_id'];
            }

            // Projekt aktualisieren
            if (!empty($updateData)) {
                $project->update($updateData);
            }

            if ($targetLifecycle !== null) {
                try {
                    app(\Platform\Planner\Services\LifecycleService::class)->transitionProjectTo($project, $targetLifecycle);
                } catch (\Platform\Planner\Exceptions\InvalidLifecycleTransitionException $e) {
                    return ToolResult::error('INVALID_TRANSITION', 'Lebenszyklus-Wechsel nicht möglich: ' . $e->getMessage() . ' (Stammdaten wurden gespeichert.)');
                }
            }

            // Aktualisiertes Projekt laden
            $project->refresh();
            $project->load(['user']);

            // Entity-Links laden (via DimensionLink Bridge)
            $entityLinks = \Platform\Organization\Services\EntityDimensionBridge::linksForLinkables(
                ['project'],
                [$project->id]
            );
            $entityLinksData = $entityLinks->map(fn($l) => [
                'entity_id' => $l->entity_id,
                'entity_name' => $l->entity?->name,
            ])->toArray();

            return ToolResult::success([
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'title' => $project->title,
                'description' => $project->description,
                'project_type' => $project->project_type?->value,
                'kind' => $project->kind?->value,
                'lifecycle_state' => $project->lifecycle_state?->value,
                'lifecycle_state_changed_at' => $project->lifecycle_state_changed_at?->toIso8601String(),
                'team_id' => $project->team_id,
                'owner_user_id' => $project->user_id,
                'owner_name' => $project->user->name ?? 'Unbekannt',
                'billing_method' => $project->billing_method?->value,
                'hourly_rate' => $project->hourly_rate ? (float) $project->hourly_rate : null,
                'budget_amount' => $project->budget_amount ? (float) $project->budget_amount : null,
                'currency' => $project->currency,
                'entity_links' => $entityLinksData,
                'planned_start' => $project->plannedStart()?->toDateString(),
                'planned_end' => $project->plannedEnd()?->toDateString(),
                'planned_minutes' => $project->totalPlannedMinutes(),
                'estimated_hours' => $project->totalPlannedHours(),
                'updated_at' => $project->updated_at->toIso8601String(),
                'message' => "Projekt '{$project->name}' erfolgreich aktualisiert."
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Projekts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['planner', 'projects', 'put'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
