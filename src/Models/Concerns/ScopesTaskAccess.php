<?php

namespace Platform\Planner\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Wiederverwendbare Query-Scopes fuer die Task-Sichten.
 *
 * Buendelt die WHERE-Bloecke, die zuvor in MyTasks/DelegatedTasks mehrfach
 * inline dupliziert waren, an einer Stelle. Verhalten 1:1 wie die frueheren
 * Inline-Closures — reine Entdopplung.
 */
trait ScopesTaskAccess
{
    /**
     * "Mein Board": private eigene Aufgaben (ohne Projekt) ODER Projekt-
     * Aufgaben, fuer die ich verantwortlich bin und die einem Slot zugeordnet
     * sind (also NICHT im Projekt-Backlog).
     *
     * $slotColumn bildet die Legacy-Variante der Performance-Berechnung ab
     * ('sprint_slot_id' statt 'project_slot_id') — ohne Verhaltensaenderung.
     */
    public function scopeForMyBoard(Builder $query, int $userId, string $slotColumn = 'project_slot_id'): Builder
    {
        return $query->where(function (Builder $q) use ($userId, $slotColumn) {
            $q->where(function (Builder $q) use ($userId) {
                $q->whereNull('project_id')
                  ->where('user_id', $userId); // private Aufgabe
            })->orWhere(function (Builder $q) use ($userId, $slotColumn) {
                $q->whereNotNull('project_id')
                  ->where('user_in_charge_id', $userId)
                  ->whereNotNull($slotColumn); // nur Projektaufgaben mit Slot (nicht Backlog)
            });
        });
    }

    /**
     * "Von mir delegiert": von mir erstellt, aber jemand anderes ist
     * verantwortlich.
     */
    public function scopeDelegatedByMe(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
            ->whereNotNull('user_in_charge_id')
            ->where('user_in_charge_id', '!=', $userId);
    }
}
