<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;

class PlannerProjectUser extends Model
{
    protected $fillable = ['project_id', 'role', 'user_id']; // ggf. erweitern

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function project()
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }
}
