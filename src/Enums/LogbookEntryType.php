<?php

namespace Platform\Planner\Enums;

/**
 * Eintragstypen des Projekt-Logbuchs. Bewusst klein gehalten: das Logbuch haelt
 * Wendepunkte mit Begruendung fest, keine Arbeit (die steht in Aufgaben/Zeiten).
 */
enum LogbookEntryType: string
{
    case DECISION = 'decision';
    case MILESTONE = 'milestone';
    case RISK = 'risk';

    public function label(): string
    {
        return match ($this) {
            self::DECISION => 'Entscheidung',
            self::MILESTONE => 'Meilenstein',
            self::RISK => 'Risiko',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DECISION => 'heroicon-o-scale',
            self::MILESTONE => 'heroicon-o-flag',
            self::RISK => 'heroicon-o-exclamation-triangle',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
