<?php
// app/Domain/SHU/Models/ShuMemberCalculation.php
namespace App\Domain\SHU\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Member\Models\Member;

/**
 * SHU Member Calculation Model
 */
class ShuMemberCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'shu_plan_id',
        'member_id',
        'total_shu_amount',
        'savings_shu_amount',
        'transaction_shu_amount',
        'activity_shu_amount',
        'membership_shu_amount',
        'calculation_data',
        'is_distributed',
        'distributed_at',
    ];

    protected $casts = [
        'total_shu_amount' => 'decimal:2',
        'savings_shu_amount' => 'decimal:2',
        'transaction_shu_amount' => 'decimal:2',
        'activity_shu_amount' => 'decimal:2',
        'membership_shu_amount' => 'decimal:2',
        'calculation_data' => 'array',
        'is_distributed' => 'boolean',
        'distributed_at' => 'datetime',
    ];

    // Relationships
    public function shuPlan()
    {
        return $this->belongsTo(ShuPlan::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    // Helper methods
    public function getBreakdownAttribute(): array
    {
        return [
            'savings' => $this->savings_shu_amount,
            'transaction' => $this->transaction_shu_amount,
            'activity' => $this->activity_shu_amount,
            'membership' => $this->membership_shu_amount,
        ];
    }
}
