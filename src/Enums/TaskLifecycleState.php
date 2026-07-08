<?php

namespace Platform\Planner\Enums;

/**
 * Lebenszyklus einer Task — feiner als Projekt, ohne "ruhend".
 *
 * Manuell:
 *   aktiv -> erledigt   (Aufgabe erledigt, Read-only)
 *   aktiv -> verworfen  (Aufgabe wird nicht mehr gemacht, Read-only)
 *
 * Kaskade:
 *   Wenn das Projekt auf 'verworfen' geht, werden offene Tasks des Projekts
 *   automatisch auf 'verworfen' gesetzt.
 *
 * Keine Aktivitaets-Automatik: Tasks sind fein genug, dass der Mensch sie
 * ohnehin manuell schliesst oder verwirft.
 */
enum TaskLifecycleState: string
{
    case AKTIV = 'aktiv';
    case ERLEDIGT = 'erledigt';
    case VERWORFEN = 'verworfen';

    public function label(): string
    {
        return match ($this) {
            self::AKTIV => 'Aktiv',
            self::ERLEDIGT => 'Erledigt',
            self::VERWORFEN => 'Verworfen',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::ERLEDIGT, self::VERWORFEN], true);
    }

    public function isLive(): bool
    {
        return $this === self::AKTIV;
    }

    public function tone(): string
    {
        return match ($this) {
            self::AKTIV => 'green',
            self::ERLEDIGT => 'blue',
            self::VERWORFEN => 'zinc',
        };
    }
}
