<?php

namespace Platform\Planner\Verbalization;

use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\Template\NarrativeTemplate;

/**
 * Erzaehlvorlage fuer Planner-Projects.
 *
 * Dramaturgie:
 *  1. Identitaet: Name + Owner + Zugehoerigkeit (Venture, Engagement)
 *  2. Aktuelle Lage: Status, Health, Tasks, Frösche, Termine
 *  3. Beteiligte: Team-Members + ihre Workload
 *  4. Kontext: Slots, Canvas-Highlights, Story Points, Budget
 *  5. Daten-Grundlage: Frische, Konfidenz, Kostenstelle
 */
class PlannerProjectTemplate implements NarrativeTemplate
{
    public function handles(): string
    {
        return 'planner_project';
    }

    public function renderFactSheet(Subject $subject): string
    {
        $lines = [];

        // 1. Identitaet
        $lines[] = '## ' . $subject->identity->primaryName;

        $owner = $this->firstEdge($subject, 'verantwortet_von');
        if ($owner) {
            $lines[] = 'Verantwortlich: ' . $owner->targetLabel;
        }

        $orgAnchors = $this->edgesByRelation($subject, 'gehört_zu');
        if (! empty($orgAnchors)) {
            $names = array_map(fn ($e) => $e->targetLabel, $orgAnchors);
            $lines[] = 'Eingehängt unter: ' . implode(', ', $names);
        }

        $costCenter = $this->firstEdge($subject, 'abgerechnet_auf');
        if ($costCenter) {
            $lines[] = 'Kostenstelle: ' . $costCenter->targetLabel;
        }

        $lines[] = '';

        // 2. Aktuelle Lage — CORE-Facts (Status, Health, Tasks, Frösche, kritische Termine,
        //    Description als Zweck)
        $coreFacts = $this->factsByPriority($subject, FactPriority::CORE);
        if (! empty($coreFacts)) {
            $lines[] = '### Aktuelle Lage';
            foreach ($coreFacts as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        // 3. Beteiligte (Team-Edges)
        $teamEdges = $this->edgesByRelation($subject, 'arbeitet_mit');
        if (! empty($teamEdges)) {
            $names = array_map(fn ($e) => $e->targetLabel, $teamEdges);
            $lines[] = '### Beteiligte';
            $lines[] = '- Im Team neben dem Verantwortlichen: ' . implode(', ', $names) . '.';
            $lines[] = '';
        }

        // 4. Kontext — QUALIFYING (Slots, SP, Workload-Verteilung, Canvas-Completeness,
        //    Budget, mittelfristige Termine)
        $qualifyingFacts = $this->factsByPriority($subject, FactPriority::QUALIFYING);
        if (! empty($qualifyingFacts)) {
            $lines[] = '### Weitere Kennzahlen und Kontext';
            foreach ($qualifyingFacts as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        // 5. Daten-Grundlage
        $lines[] = '### Daten-Grundlage';
        $lines[] = '- ' . $this->describeFreshness($subject);
        foreach ($this->factsByPriority($subject, FactPriority::CONTEXT) as $f) {
            $lines[] = '- ' . $f->text;
        }

        return implode("\n", $lines);
    }

    protected function describeFreshness(Subject $subject): string
    {
        $f = $subject->freshness;
        $when = $f->asOf->format('d.m.Y H:i');
        return match ($f->source) {
            DataSource::LIVE => "Live-Daten (Stand: {$when}).",
            DataSource::SNAPSHOT => "Daten aus Snapshot vom {$when}.",
            DataSource::SNAPSHOT_WITH_LIVE_TOPUP => "Basis: Snapshot vom {$when}, ergaenzt um Live-Bewegungen seitdem.",
        };
    }

    /** @return \Platform\Core\Verbalization\Fact[] */
    protected function factsByPriority(Subject $subject, FactPriority $priority): array
    {
        return array_values(array_filter($subject->facts, fn ($f) => $f->priority === $priority));
    }

    /** @return \Platform\Core\Verbalization\Edge[] */
    protected function edgesByRelation(Subject $subject, string $relation): array
    {
        return array_values(array_filter($subject->edges, fn ($e) => $e->relation === $relation));
    }

    protected function firstEdge(Subject $subject, string $relation): ?\Platform\Core\Verbalization\Edge
    {
        foreach ($subject->edges as $e) {
            if ($e->relation === $relation) {
                return $e;
            }
        }
        return null;
    }
}
