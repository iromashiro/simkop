<?php
// app/Http/Resources/Financial/LoanAccountResource.php
namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'principal_amount' => $this->principal_amount,
            'interest_rate' => $this->interest_rate,
            'term_months' => $this->term_months,
            'monthly_payment' => $this->monthly_payment,
            'outstanding_balance' => $this->outstanding_balance,
            'status' => $this->status,
            'disbursement_date' => $this->disbursement_date?->format('Y-m-d'),
            'maturity_date' => $this->maturity_date?->format('Y-m-d'),
            'purpose' => $this->purpose,
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

            'loan_product' => $this->whenLoaded('loanProduct', function () {
                return [
                    'id' => $this->loanProduct->id,
                    'name' => $this->loanProduct->name,
                    'code' => $this->loanProduct->code,
                    'max_amount' => $this->loanProduct->max_amount,
                    'max_term' => $this->loanProduct->max_term,
                ];
            }),

            'recent_payments' => $this->whenLoaded('recentPayments', function () {
                return $this->recentPayments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'principal_amount' => $payment->principal_amount,
                        'interest_amount' => $payment->interest_amount,
                        'balance_after' => $payment->balance_after,
                        'payment_date' => $payment->payment_date->format('Y-m-d H:i:s'),
                        'notes' => $payment->notes,
                    ];
                });
            }),

            'payment_schedule' => $this->when($this->relationLoaded('paymentSchedule'), function () {
                return $this->paymentSchedule->map(function ($schedule) {
                    return [
                        'installment_number' => $schedule->installment_number,
                        'due_date' => $schedule->due_date->format('Y-m-d'),
                        'principal_amount' => $schedule->principal_amount,
                        'interest_amount' => $schedule->interest_amount,
                        'total_amount' => $schedule->total_amount,
                        'is_paid' => $schedule->is_paid,
                        'paid_date' => $schedule->paid_date?->format('Y-m-d'),
                    ];
                });
            }),

            // Statistics
            'statistics' => $this->when($this->relationLoaded('statistics'), function () {
                return [
                    'total_payments' => $this->statistics->total_payments ?? 0,
                    'total_interest_paid' => $this->statistics->total_interest_paid ?? 0,
                    'payments_made' => $this->statistics->payments_made ?? 0,
                    'payments_remaining' => $this->statistics->payments_remaining ?? 0,
                    'days_overdue' => $this->statistics->days_overdue ?? 0,
                ];
            }),
        ];
    }
}
