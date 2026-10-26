<?php

namespace Platform\Planner\Tools\Logbook;

use Illuminate\Support\Carbon;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Planner\Services\ProjectLogbookService;
use Platform\Planner\Tools\Logbook\Concerns\ResolvesLogbookProject;

class DraftLogbookEntryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLogbookProject;

    public function getName(): string
    {
        return 'planner.project.logbook.draft.GET';
    }

    public function getDescription(): string
    {
        return 'GET /projects/{id}/logbook/draft - Entwurfs-Motor fuers Logbuch: erzeugt Faktenprosa (planner.project.verbalize-Pipeline) zur Bewegung seit dem letzten Logbuch-Eintrag plus Verweis-Vorschlaege (erledigte Aufgaben, neue Canvas-Staende). Schreibt NICHTS. Ablauf: Entwurf zeigen → Nutzer entscheidet, was davon Entscheidung/Meilenstein/Risiko ist und warum → planner.project.logbook.POST. Parameter: project_id (required), since (optional, YYYY-MM-DD; default: letzter Eintrag, max. 90 Tage), dry_run (optional: true = ohne LLM, nur Faktenbasis).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'ID des Projekts.'],
                'since' => ['type' => 'string', 'description' => 'Optional: Zeitraum-Beginn (YYYY-MM-DD).'],
                'dry_run' => ['type' => 'boolean', 'description' => 'Optional: true = kein LLM-Call, nur deterministische Faktenbasis.'],
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
            $since = ! empty($arguments['since']) ? Carbon::parse($arguments['since'])->startOfDay() : null;
            $draft = app(ProjectLogbookService::class)->draft($project, $since, ! ($arguments['dry_run'] ?? false));
        } catch (\Throwable $e) {
            return ToolResult::error('DRAFT_FAILED', $e->getMessage());
        }

        return ToolResult::success([
            'project_id' => $project->id,
            'project_name' => $project->name,
            ...$draft,
            'hint' => 'Entwurf, kein Eintrag. Nur Wendepunkte mit Begruendung uebernehmen; Arbeit ist ueber suggested_refs verlinkbar.',
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize',
            'tags' => ['planner', 'project', 'logbook', 'draft', 'llm'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => false,
        ];
    }
}
