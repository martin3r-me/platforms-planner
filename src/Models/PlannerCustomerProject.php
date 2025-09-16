<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Planner\Enums\CustomerBillingMethod;

class PlannerCustomerProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id','team_id','user_id',
        'company_id','contact_id',
        'billing_method','hourly_rate','currency','budget_amount',
        'start_date','end_date','billing_status',
        'cost_center','invoice_account','notes',
    ];

    protected $casts = [
        'billing_method' => CustomerBillingMethod::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }

    public function parties()
    {
        return $this->hasMany(PlannerCustomerProjectParty::class, 'customer_project_id');
    }

    public function billingItems()
    {
        return $this->hasMany(PlannerCustomerProjectBillingItem::class, 'customer_project_id');
    }
}


