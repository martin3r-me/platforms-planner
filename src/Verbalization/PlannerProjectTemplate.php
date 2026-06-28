<?php

namespace Platform\Planner\Verbalization;

use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\Template\NarrativeTemplate;

/**
 * Erzaehlvorlage fuer Planner-Projects.
 *
 * Dramaturgie (Baustein 4):
 *  1. Identitaet: Was ist es (Name, Verantwortlich)
 *  2. Lage: Health, Tasks, Termine
 *  3. Kontext: Canvas, Story Points
 *  4. Daten-Grundlage: Frische, Konfidenz
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

        // Wer ist verantwortlich? (CORE-Edge zuerst)
        $owner = collect($subject->edges)
            ->first(fn ($e) => $e->relation === 'verantwortet_von');
        if ($owner) {
            $lines[] = 'Verantwortlich: ' . $owner->targetLabel;
        }

        $costCenter = collect($subject->edges)
            ->first(fn ($e) => $e->relation === 'abgerechnet_auf');
        if ($costCenter) {
            $lines[] = 'Kostenstelle: ' . $costCenter->targetLabel;
        }

        $lines[] = '';

        // 2. Lage — CORE-Facts (Status, Health, Task-Lage, kritische Termine)
        $coreFacts = collect($subject->facts)->filter(fn ($f) => $f->priority === FactPriority::CORE);
        if ($coreFacts->isNotEmpty()) {
            $lines[] = '### Aktuelle Lage';
            foreach ($coreFacts as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        // 3. Kontext — QUALIFYING (Canvas, Story Points, weniger kritische Termine)
        $qualifyingFacts = collect($subject->facts)->filter(fn ($f) => $f->priority === FactPriority::QUALIFYING);
        if ($qualifyingFacts->isNotEmpty()) {
            $lines[] = '### Weitere Kennzahlen';
            foreach ($qualifyingFacts as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        // 4. Daten-Grundlage (Frische + Konfidenz-Hinweise aus CONTEXT)
        $lines[] = '### Daten-Grundlage';
        $lines[] = '- ' . $this->describeFreshness($subject);
        $contextFacts = collect($subject->facts)->filter(fn ($f) => $f->priority === FactPriority::CONTEXT);
        foreach ($contextFacts as $f) {
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
}
