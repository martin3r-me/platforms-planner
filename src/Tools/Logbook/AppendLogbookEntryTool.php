<?php

namespace Platform\Planner\Tools\Logbook;

use InvalidArgumentException;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Services\ProjectLogbookService;
use Platform\Planner\Tools\Logbook\Concerns\ResolvesLogbookProject;

class AppendLogbookEntryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLogbookProject;

    public function getName(): string
    {
        return 'planner.project.logbook.POST';
    }

    public function getDescription(): string
    {
        return 'POST /projects/{id}/logbook - Haengt einen Eintrag ans Projekt-Logbuch an (append-only, nicht editierbar). Parameter: project_id (required), type (required: decision|milestone|risk), title (required), rationale (empfohlen: das WARUM — Begruendung, verworfene Alternativen, Annahmen), occurred_on (optional, YYYY-MM-DD, default heute, nicht in der Zukunft), refs (optional: [{type: task|commit|canvas|canvas_snapshot|url, ref, label?}] — VERWEISE statt Wiederholung; commit als "sha" oder "repo@sha"), supersedes_entry_id (optional: korrigiert einen frueheren Eintrag). Arbeitsschritte gehoeren NICHT ins Logbuch (dafuer Aufgaben/Zeiten). Fuer einen Entwurf aus der Faktenlage vorher planner.project.logbook.draft.GET nutzen und mit dem Nutzer kuratieren.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'ID des Projekts.'],
                'type' => ['type' => 'string', 'enum' => LogbookEntryType::values(), 'description' => 'decision = Entscheidung, milestone = Meilenstein erreicht, risk = Risiko erkannt.'],
                'title' => ['type' => 'string', 'description' => 'Kurze Aussage, z.B. "Wir migrieren auf NECTA statt BankettProfi weiterzufuehren".'],
                'rationale' => ['type' => 'string', 'description' => 'Begruendung: warum, welche Alternativen verworfen, welche Annahmen.'],
                'occurred_on' => ['type' => 'string', 'description' => 'Datum des Ereignisses (YYYY-MM-DD). Default heute.'],
                'refs' => [
                    'type' => 'array',
                    'description' => 'Verweise auf Belege.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => PlannerProjectLogbookEntry::REF_TYPES],
                            'ref' => ['type' => 'string', 'description' => 'ID (task/canvas/canvas_snapshot), "sha"/"repo@sha" (commit) oder URL.'],
                            'label' => ['type' => 'string', 'description' => 'Optional: Anzeigename. Bei task/canvas wird der Name automatisch gesetzt.'],
                        ],
                        'required' => ['type', 'ref'],
                    ],
                ],
                'supersedes_entry_id' => ['type' => 'integer', 'description' => 'Optional: ID eines frueheren Eintrags, den dieser korrigiert/ersetzt.'],
            ],
            'required' => ['project_id', 'type', 'title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $project = $this->resolveProject($arguments, $context, 'update');
        if ($project instanceof ToolResult) {
            return $project;
        }

        try {
            $entry = app(ProjectLogbookService::class)->append($project, $context->user, [
                ...$arguments,
                'source' => 'agent',
            ]);
        } catch (InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Logbuch-Eintrags: ' . $e->getMessage());
        }

        $entry->load('createdBy:id,name');

        return ToolResult::success([
            'entry' => $entry->toToolArray(),
            'message' => "{$entry->type->label()} '{$entry->title}' im Logbuch von '{$project->name}' festgehalten.",
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['planner', 'project', 'logbook', 'decisions', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
