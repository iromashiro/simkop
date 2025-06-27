<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShuPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'fiscal_year',
        'total_shu',
        'member_services_percentage',
        'member_capital_percentage',
        'reserve_fund_percentage',
        'management_percentage',
        'employee_percentage',
        'member_services_amount',
        'member_capital_amount',
        'reserve_fund_amount',
        'management_amount',
        'employee_amount',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'total_shu' => 'decimal:2',
        'member_services_percentage' => 'decimal:2',
        'member_capital_percentage' => 'decimal:2',
        'reserve_fund_percentage' => 'decimal:2',
        'management_percentage' => 'decimal:2',
        'employee_percentage' => 'decimal:2',
        'member_services_amount' => 'decimal:2',
        'member_capital_amount' => 'decimal:2',
        'reserve_fund_amount' => 'decimal:2',
        'management_amount' => 'decimal:2',
        'employee_amount' => 'decimal:2',
        'fiscal_year' => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the cooperative that owns the SHU plan.
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Get the distributions for the SHU plan.
     */
    public function distributions()
    {
        return $this->hasMany(ShuDistribution::class);
    }

    /**
     * Get the user who approved the plan.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
