<?php

namespace Platform\Planner\Tools\Logbook;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Tools\Logbook\Concerns\ResolvesLogbookProject;

class ListLogbookEntriesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLogbookProject;

    public function getName(): string
    {
        return 'planner.project.logbook.GET';
    }

    public function getDescription(): string
    {
        return 'Liest das Projekt-Logbuch: chronologische, unveraenderliche Eintraege (Entscheidung/Meilenstein/Risiko) mit Begruendung und Verweisen auf Aufgaben/Commits/Canvas. Parameter: project_id (required), type (optional: decision|milestone|risk), since (optional, YYYY-MM-DD), include_superseded (optional, default false), order (optional: asc|desc, default asc), limit (optional, default 50, max 200). Abgrenzung: Canvas = aktueller Zustand, Logbuch = Verlauf/Warum, Aufgaben/Zeiten = Arbeit.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'ID des Projekts.'],
                'type' => ['type' => 'string', 'enum' => LogbookEntryType::values(), 'description' => 'Optional: nur dieser Eintragstyp.'],
                'since' => ['type' => 'string', 'description' => 'Optional: nur Eintraege ab diesem Datum (YYYY-MM-DD, bezogen auf occurred_on).'],
                'include_superseded' => ['type' => 'boolean', 'description' => 'Optional: auch ersetzte (korrigierte) Eintraege liefern. Default false.'],
                'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'description' => 'Optional: Sortierung nach Datum. Default asc (chronologisch).'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl. Default 50, max 200.'],
            ],
            'required' => ['project_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $project = $this->resolveProject($arguments, $context, 'view');
        if ($project instanceof ToolResult) {
            return $project;
        }

        try {
            $query = PlannerProjectLogbookEntry::with('createdBy:id,name')->forProject($project->id);

            if (! empty($arguments['type'])) {
                if (! LogbookEntryType::tryFrom($arguments['type'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'type muss einer von ' . implode(', ', LogbookEntryType::values()) . ' sein.');
                }
                $query->where('type', $arguments['type']);
            }
            if (! empty($arguments['since'])) {
                $query->where('occurred_on', '>=', \Illuminate\Support\Carbon::parse($arguments['since'])->toDateString());
            }
            if (empty($arguments['include_superseded'])) {
                $query->current();
            }

            $desc = ($arguments['order'] ?? 'asc') === 'desc';
            $query->orderBy('occurred_on', $desc ? 'desc' : 'asc')->orderBy('id', $desc ? 'desc' : 'asc');

            $limit = max(1, min(200, (int) ($arguments['limit'] ?? 50)));
            $entries = $query->limit($limit)->get();

            return ToolResult::success([
                'project_id' => $project->id,
                'project_name' => $project->name,
                'entries' => $entries->map->toToolArray()->all(),
                'count' => $entries->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Lesen des Logbuchs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'project', 'logbook', 'decisions'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
