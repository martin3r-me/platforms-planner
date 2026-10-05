<?php

namespace Platform\Planner\Policies;

/**
 * PlannerRecurringTask hat dieselbe Form wie PlannerTask (user_id, user_in_charge_id,
 * project_id, project-Relation) — die Sichtbarkeits-/Schreib-/Löschregeln von
 * PlannerTaskPolicy gelten unveraendert, daher reine Wiederverwendung per Vererbung.
 */
class PlannerRecurringTaskPolicy extends PlannerTaskPolicy
{
}
