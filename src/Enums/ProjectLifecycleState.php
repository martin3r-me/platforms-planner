<?php

namespace Platform\Planner\Enums;

/**
 * Lebenszyklus eines Projekts — eine Achse, vier Zustaende.
 *
 * Automatik:
 *   aktiv  <-> ruhend    (ActivityClock, 45d Schwelle)
 *
 * Manuell (Owner):
 *   aktiv/ruhend -> abgeschlossen  (Ziel erreicht, Read-only)
 *   aktiv/ruhend -> verworfen      (Ohne Ergebnis beendet; kaskadiert offene Tasks)
 *   abgeschlossen -> aktiv         (Re-Open, logged)
 *   verworfen    -> aktiv          (Revive, logged)
 *
 * Technisch (Aufraeumer/Admin):
 *   jeder -> soft-delete           (Bereinigung, kein fachlicher Zustand)
 *   soft-delete -> jeder           (Restore, nur Admin)
 */
enum ProjectLifecycleState: string
{
    case AKTIV = 'aktiv';
    case RUHEND = 'ruhend';
    case ABGESCHLOSSEN = 'abgeschlossen';
    case VERWORFEN = 'verworfen';

    public function label(): string
    {
        return match ($this) {
            self::AKTIV => 'Aktiv',
            self::RUHEND => 'Ruhend',
            self::ABGESCHLOSSEN => 'Abgeschlossen',
            self::VERWORFEN => 'Verworfen',
        };
    }

    /** Ist der Zustand ein Endzustand (Read-only, Owner kann re-open)? */
    public function isTerminal(): bool
    {
        return in_array($this, [self::ABGESCHLOSSEN, self::VERWORFEN], true);
    }

    /** Ist der Zustand "im Spiel" (zaehlt in Health, Portfolio, Bewegung)? */
    public function isLive(): bool
    {
        return $this === self::AKTIV;
    }

    /** Farb-Hint fuer UI (keine harte Kopplung an CSS, nur Semantik). */
    public function tone(): string
    {
        return match ($this) {
            self::AKTIV => 'green',
            self::RUHEND => 'amber',
            self::ABGESCHLOSSEN => 'blue',
            self::VERWORFEN => 'zinc',
        };
    }
}
