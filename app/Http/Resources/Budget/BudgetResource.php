<?php
// app/Http/Resources/Budget/BudgetResource.php
namespace App\Http\Resources\Budget;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'budget_year' => $this->budget_year,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_budget' => $this->total_budget,
            'total_actual' => $this->total_actual,
            'variance' => $this->variance,
            'variance_percentage' => $this->variance_percentage,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Budget items
            'budget_items' => $this->whenLoaded('budgetItems', function () {
                return $this->budgetItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'account' => [
                            'id' => $item->account->id,
                            'code' => $item->account->code,
                            'name' => $item->account->name,
                        ],
                        'budget_amount' => $item->budget_amount,
                        'actual_amount' => $item->actual_amount,
                        'variance' => $item->variance,
                        'variance_percentage' => $item->variance_percentage,
                        'notes' => $item->notes,
                    ];
                });
            }),

            // Monthly breakdown
            'monthly_breakdown' => $this->when($this->relationLoaded('monthlyBreakdown'), function () {
                return $this->monthlyBreakdown->map(function ($month) {
                    return [
                        'month' => $month->month,
                        'budget_amount' => $month->budget_amount,
                        'actual_amount' => $month->actual_amount,
                        'variance' => $month->variance,
                        'variance_percentage' => $month->variance_percentage,
                    ];
                });
            }),

            // Statistics
            'statistics' => [
                'total_budget_items' => $this->budget_items_count ?? 0,
                'over_budget_items' => $this->over_budget_items_count ?? 0,
                'under_budget_items' => $this->under_budget_items_count ?? 0,
                'on_track_items' => $this->on_track_items_count ?? 0,
                'completion_percentage' => $this->completion_percentage ?? 0,
            ],
        ];
    }
}
