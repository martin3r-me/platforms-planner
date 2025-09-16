<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Planner\Enums\CustomerPartyRole;
use Platform\Planner\Enums\CustomerPartyType;

class PlannerCustomerProjectParty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_project_id','team_id','user_id',
        'company_id','contact_id',
        'party_type','role','is_primary',
        'email','phone','notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'party_type' => CustomerPartyType::class,
        'role' => CustomerPartyRole::class,
    ];

    public function customerProject()
    {
        return $this->belongsTo(PlannerCustomerProject::class, 'customer_project_id');
    }
}


