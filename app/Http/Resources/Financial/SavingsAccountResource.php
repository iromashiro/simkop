<?php
// app/Http/Resources/Financial/SavingsAccountResource.php
namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'balance' => $this->balance,
            'status' => $this->status,
            'opened_date' => $this->opened_date?->format('Y-m-d'),
            'closed_date' => $this->closed_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member->id,
                    'member_number' => $this->member->member_number,
                    'full_name' => $this->member->full_name,
                ];
            }),

            'savings_product' => $this->whenLoaded('savingsProduct', function () {
                return [
                    'id' => $this->savingsProduct->id,
                    'name' => $this->savingsProduct->name,
                    'code' => $this->savingsProduct->code,
                    'interest_rate' => $this->savingsProduct->interest_rate,
                    'minimum_balance' => $this->savingsProduct->minimum_balance,
                ];
            }),

            'recent_transactions' => $this->whenLoaded('recentTransactions', function () {
                return $this->recentTransactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'amount' => $transaction->amount,
                        'balance_after' => $transaction->balance_after,
                        'transaction_date' => $transaction->transaction_date->format('Y-m-d H:i:s'),
                        'description' => $transaction->description,
                    ];
                });
            }),

            // Statistics
            'statistics' => $this->when($this->relationLoaded('statistics'), function () {
                return [
                    'total_deposits' => $this->statistics->total_deposits ?? 0,
                    'total_withdrawals' => $this->statistics->total_withdrawals ?? 0,
                    'transaction_count' => $this->statistics->transaction_count ?? 0,
                    'average_monthly_balance' => $this->statistics->average_monthly_balance ?? 0,
                    'interest_earned' => $this->statistics->interest_earned ?? 0,
                ];
            }),
        ];
    }
}
