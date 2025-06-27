<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShuDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'shu_plan_id',
        'member_id',
        'service_amount',
        'capital_amount',
        'total_amount',
        'service_score',
        'capital_score',
        'payment_status',
        'payment_date',
    ];

    protected $casts = [
        'service_amount' => 'decimal:2',
        'capital_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'service_score' => 'decimal:2',
        'capital_score' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Get the SHU plan that owns the distribution.
     */
    public function shuPlan()
    {
        return $this->belongsTo(ShuPlan::class);
    }

    /**
     * Get the member for the distribution.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
