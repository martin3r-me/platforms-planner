<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-User-Kalender-Abo: welche Projekte im CalDAV-Feed erscheinen sollen.
 * Reine Abo-Wahl, kein Zugriff — Sichtbarkeit/Rechte kommen aus dem Org-Graphen.
 */
class PlannerCalendarExposure extends Model
{
    protected $table = 'planner_calendar_exposures';

    protected $fillable = ['user_id', 'project_id'];

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function project()
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }
}
