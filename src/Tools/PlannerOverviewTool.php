<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\ToolRegistry;
use Platform\Planner\Services\ProjectCanvasVersioning;
use Platform\Planner\Support\ProjectNavSummary;

/**
 * Konzept-Uebersicht des Planners fuer Agenten: Projekt = Canvas (Zustand) +
 * Logbuch (Verlauf) + Aufgaben/Zeiten (Arbeit). Tool-Liste kommt live aus der Registry.
 */
class PlannerOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.overview.GET';
    }

    public function getDescription(): string
    {
        return 'Erklärt das Planner-Konzept und listet alle Planner-Tools nach Bereich. Kern: jedes Projekt hat drei Achsen — '
            . 'Canvas (Kickoff + aktueller Zustand), Logbuch (Verlauf mit Begründung) und Aufgaben/Zeiten (Arbeit). '
            . 'Für ein konkretes Projekt direkt planner.project.brief.GET nutzen. Keine Parameter.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'planner',
                'principle' => 'Ein Projekt wird auf drei Achsen gepflegt. Nichts doppelt: das Logbuch verweist auf Aufgaben/Commits/Canvas-Stände, statt sie zu wiederholen. Der Agent entwirft, der Mensch kuratiert.',
                'axes' => [
                    'state_canvas' => [
                        'what' => 'Projekt-Canvas: Kickoff und aktueller Zustand (Ziel, Beteiligte, Nutzen, Rahmen, Risiken …). Genau EIN Canvas pro Projekt, entsteht automatisch mit dem Projekt.',
                        'semantics' => 'Wird überschrieben. Versionen entstehen automatisch je Bearbeitungssitzung (' . ProjectCanvasVersioning::SESSION_GAP_MINUTES . ' Min. Pause); bedeutsame Stände mit planner.canvas.snapshots.POST festhalten.',
                        'kickoff' => 'Ein Projekt beginnt mit dem Kickoff im Canvas. planner.canvas.kickoff.POST sichert den Kickoff-Stand (Anker für alle Vergleiche) und schreibt den Meilenstein ins Logbuch.',
                        'addressing' => 'Canvas-Tools nehmen project_id (oder canvas_id); Einträge hängen an Blöcken (block_id aus planner.canvas.GET).',
                    ],
                    'history_logbook' => [
                        'what' => 'Projekt-Logbuch: chronologisch, append-only. Typen: decision (Entscheidung), milestone (Meilenstein), risk (Risiko) — jeweils mit Begründung (rationale = das Warum).',
                        'semantics' => 'Unveränderlich; Korrektur = neuer Eintrag mit supersedes_entry_id. refs verweisen auf task, commit, canvas, canvas_snapshot, url.',
                        'flow' => 'planner.project.logbook.draft.GET erzeugt einen Entwurf aus der Faktenlage seit dem letzten Eintrag → mit dem Nutzer kuratieren → planner.project.logbook.POST (optional capture_canvas=true).',
                        'stale_after_days' => ProjectNavSummary::LOGBOOK_STALE_DAYS,
                    ],
                    'work_tasks' => [
                        'what' => 'Aufgaben (in Spalten/Slots oder im Backlog = ohne Slot), wiederkehrende Aufgaben, Zeiten. Persönliche Aufgaben haben kein Projekt.',
                        'lifecycle' => 'lifecycle_state: aktiv | erledigt | verworfen (verworfen ist endgültig).',
                        'staleness' => 'Lange nicht angesehene Aufgaben sind in Listen ausgeblendet → planner.tasks.GET include_stale=true.',
                    ],
                ],
                'project' => [
                    'lifecycle' => 'lifecycle_state: aktiv | ruhend (automatisch bei Inaktivität) | abgeschlossen | verworfen.',
                    'access' => 'Zugriff über den Organisations-Graphen (Ersteller oder verknüpfte Org-Einheit), nicht über Mitgliederlisten. Lesen = view, Schreiben = update, Owner-Wechsel/Transfer/Löschen = manage.',
                    'health' => 'Nächtlicher Health-Snapshot pro Projekt (planner.project_snapshots.*).',
                ],
                'workflows' => [
                    'understand_project' => ['planner.project.brief.GET (alle drei Achsen + nächste Schritte)'],
                    'new_project' => [
                        'planner.projects.POST (Canvas entsteht automatisch)',
                        'Canvas füllen: planner.canvas.GET → planner.canvas.entries.POST / entries.bulk.POST',
                        'planner.canvas.kickoff.POST',
                        'Arbeit anlegen: planner.tasks.POST / tasks.bulk.POST',
                    ],
                    'record_decision' => [
                        'planner.canvas.changes.GET (was hat sich am Zustand geändert?)',
                        'planner.project.logbook.draft.GET (Faktenlage)',
                        'mit dem Nutzer kuratieren → planner.project.logbook.POST (capture_canvas=true, wenn der Zustand mitgeändert wurde)',
                    ],
                    'finish_task' => ['planner.tasks.PUT lifecycle_state=erledigt; war es ein Meilenstein → planner.project.logbook.POST type=milestone mit refs task'],
                    'undo_canvas_change' => ['planner.canvas.snapshots.GET → planner.canvas.changes.GET from_version → planner.canvas.restore.POST (confirm=true)'],
                ],
                'tools' => $this->toolsByArea(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Planner-Übersicht: ' . $e->getMessage());
        }
    }

    /** Live aus der Registry, gruppiert nach Bereich — bleibt automatisch aktuell. */
    protected function toolsByArea(): array
    {
        $areas = [
            'einstieg' => ['planner.overview.', 'planner.project.brief.'],
            'canvas' => ['planner.canvas.'],
            'logbuch' => ['planner.project.logbook.'],
            'projekte' => ['planner.projects.', 'planner.project.'],
            'spalten' => ['planner.project_slots.', 'planner.project_slot.'],
            'aufgaben' => ['planner.tasks.', 'planner.recurring_tasks.'],
            'health' => ['planner.project_snapshots.', 'planner.snapshot_systems.'],
        ];

        $out = array_fill_keys(array_keys($areas), []);
        $registry = app(ToolRegistry::class);

        foreach ($registry->all() as $tool) {
            $name = $tool->getName();
            if (! str_starts_with($name, 'planner.')) {
                continue;
            }
            foreach ($areas as $area => $prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $desc = preg_replace('/^[A-Z]+ \/\S+ - /', '', $tool->getDescription());
                        $out[$area][$name] = mb_strimwidth($desc, 0, 160, '…');
                        continue 3;
                    }
                }
            }
        }

        return array_filter($out);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['overview', 'help', 'planner', 'concepts', 'canvas', 'logbook'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
            'recommended_for' => 'Verstehen des Planner-Konzepts (Canvas · Logbuch · Arbeit) und Finden des passenden Tools',
        ];
    }
}
