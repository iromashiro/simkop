<?php
// app/Http/Resources/SHU/SHUCalculationResource.php
namespace App\Http\Resources\SHU;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SHUCalculationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calculation_year' => $this->calculation_year,
            'total_shu' => $this->total_shu,
            'member_portion_percentage' => $this->member_portion_percentage,
            'member_portion_amount' => $this->member_portion_amount,
            'cooperative_portion_percentage' => $this->cooperative_portion_percentage,
            'cooperative_portion_amount' => $this->cooperative_portion_amount,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toISOString(),
            'distributed_at' => $this->distributed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),

            // Distribution criteria
            'distribution_criteria' => [
                'savings_weight' => $this->savings_weight,
                'loan_weight' => $this->loan_weight,
                'transaction_weight' => $this->transaction_weight,
                'membership_weight' => $this->membership_weight,
            ],

            // Member distributions
            'member_distributions' => $this->whenLoaded('memberDistributions', function () {
                return $this->memberDistributions->map(function ($distribution) {
                    return [
                        'member_id' => $distribution->member_id,
                        'member_name' => $distribution->member->full_name,
                        'member_number' => $distribution->member->member_number,
                        'savings_contribution' => $distribution->savings_contribution,
                        'loan_contribution' => $distribution->loan_contribution,
                        'transaction_contribution' => $distribution->transaction_contribution,
                        'membership_contribution' => $distribution->membership_contribution,
                        'total_contribution' => $distribution->total_contribution,
                        'shu_amount' => $distribution->shu_amount,
                        'status' => $distribution->status,
                        'distributed_at' => $distribution->distributed_at?->toISOString(),
                    ];
                });
            }),

            // Summary statistics
            'statistics' => [
                'total_members' => $this->total_members,
                'distributed_members' => $this->distributed_members,
                'pending_distributions' => $this->pending_distributions,
                'average_shu_per_member' => $this->average_shu_per_member,
                'highest_shu_amount' => $this->highest_shu_amount,
                'lowest_shu_amount' => $this->lowest_shu_amount,
            ],
        ];
    }
}
