<?php

namespace Platform\Planner\Tools\Canvas;

use Illuminate\Support\Carbon;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;
use Platform\Planner\Services\ProjectCanvasVersioning;
use Platform\Planner\Tools\Canvas\Concerns\ResolvesProjectCanvas;

/**
 * Was hat sich am Canvas geaendert? Seit einem Zeitpunkt, seit dem Kickoff oder
 * zwischen zwei Versionen. Ersetzt den alten Snapshot-Vergleich.
 */
class CanvasChangesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesProjectCanvas;

    public function getName(): string
    {
        return 'planner.canvas.changes.GET';
    }

    public function getDescription(): string
    {
        return 'Zeigt, was sich am Projekt-Canvas geändert hat (neu/geändert/entfernt je Block). Pflicht: project_id oder canvas_id. '
            . 'Basis wählen: since (Datum, z.B. letzter Logbuch-Eintrag), from="kickoff" oder from_version (Zahl); Ziel: to_version oder (Default) der aktuelle Stand. '
            . 'Ohne Angabe: seit dem Kickoff. Ideal, um vor einem Logbuch-Eintrag zu prüfen, was sich am Zustand getan hat.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->canvasTargetSchema(),
                'since' => ['type' => 'string', 'description' => 'Optional: Datum/Zeitpunkt (YYYY-MM-DD oder ISO 8601). Basis = letzter Stand bis dahin.'],
                'from' => ['type' => 'string', 'enum' => ['kickoff', 'latest'], 'description' => 'Optional: kickoff = Kickoff-Stand (Default), latest = zuletzt gesicherter Stand.'],
                'from_version' => ['type' => 'integer', 'description' => 'Optional: Versionsnummer als Basis.'],
                'to_version' => ['type' => 'integer', 'description' => 'Optional: Versionsnummer als Ziel. Default: aktueller Stand.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$canvas, $error] = $this->resolveCanvas($arguments, $context, 'view');
            if ($error) {
                return $error;
            }
            $versioning = app(ProjectCanvasVersioning::class);
            $version = fn (int $v) => PlannerProjectCanvasSnapshot::where('canvas_id', $canvas->id)->where('version', $v)->first();

            if (! empty($arguments['since'])) {
                try {
                    $since = Carbon::parse($arguments['since']);
                } catch (\Throwable) {
                    return ToolResult::error('VALIDATION_ERROR', 'since ist kein gültiges Datum.');
                }
                return ToolResult::success(['canvas_id' => $canvas->id, ...$versioning->changesSince($canvas, $since)]);
            }

            if (! empty($arguments['from_version'])) {
                $base = $version((int) $arguments['from_version']);
            } elseif (($arguments['from'] ?? 'kickoff') === 'latest') {
                $base = $canvas->snapshots()->orderByDesc('version')->first();
            } else {
                $base = $canvas->snapshots()->where('source', ProjectCanvasVersioning::SOURCE_KICKOFF)->orderBy('version')->first();
                if (! $base) {
                    return ToolResult::error('NO_KICKOFF', 'Für dieses Canvas ist noch kein Kickoff festgehalten (planner.canvas.kickoff.POST). Alternativ since oder from_version angeben.');
                }
            }
            if (! $base) {
                return ToolResult::error('NOT_FOUND', 'Basis-Version nicht gefunden.');
            }

            if (! empty($arguments['to_version'])) {
                $target = $version((int) $arguments['to_version']);
                if (! $target) {
                    return ToolResult::error('NOT_FOUND', 'Ziel-Version nicht gefunden.');
                }
                $diff = $versioning->diff($base->snapshot_data ?? [], $target->snapshot_data ?? []);
                $to = $versioning->snapshotMeta($target);
            } else {
                $diff = $versioning->diffToCurrent($base);
                $to = 'current';
            }

            return ToolResult::success([
                'canvas_id' => $canvas->id,
                'from' => $versioning->snapshotMeta($base),
                'to' => $to,
                ...$diff,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Vergleich: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['planner', 'canvas', 'versions', 'diff'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => true,
        ];
    }
}
