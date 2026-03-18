<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Planner\Enums\BillingUnit;

class PlannerProjectBillingItem extends Model
{
    use SoftDeletes;

    protected $table = 'planner_project_billing_items';

    protected $fillable = [
        'project_id', 'team_id', 'user_id',
        'service_date', 'description', 'unit', 'quantity', 'unit_price', 'currency', 'tax_rate',
        'billable', 'cost_center',
        'net_amount', 'tax_amount', 'gross_amount',
        'invoice_number', 'invoiced_at',
        'task_id', 'external_ref',
    ];

    protected $casts = [
        'service_date' => 'date',
        'invoiced_at' => 'date',
        'billable' => 'boolean',
        'unit' => BillingUnit::class,
    ];

    public function project()
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }
}
