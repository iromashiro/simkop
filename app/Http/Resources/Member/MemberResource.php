<?php
// app/Http/Resources/Member/MemberResource.php
namespace App\Http\Resources\Member;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class MemberResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return $this->cacheResource($request, function () use ($request) {
            return $this->buildArray($request);
        });
    }

    /**
     * Build the resource array
     */
    private function buildArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'full_name' => $this->sanitizeString($this->full_name),
            'email' => $this->sanitizeEmail($this->email),
            'phone' => $this->sanitizePhone($this->phone),
            'address' => $this->sanitizeString($this->address),

            // Sensitive data with permission check
            'id_number' => $this->when(
                $this->canViewSensitiveData($request),
                $this->id_number
            ),

            'birth_date' => $this->when(
                $this->canViewSensitiveData($request),
                $this->birth_date?->format('Y-m-d')
            ),

            'join_date' => $this->join_date?->format('Y-m-d'),
            'status' => $this->status,
            'initial_deposit' => $this->formatCurrency($this->initial_deposit),
            'total_savings' => $this->formatCurrency($this->total_savings),
            'total_loans' => $this->formatCurrency($this->total_loans),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships with error handling
            'cooperative' => $this->whenLoaded('cooperative', function () {
                try {
                    return [
                        'id' => $this->cooperative->id,
                        'name' => $this->sanitizeString($this->cooperative->name),
                        'code' => $this->cooperative->code,
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error loading member cooperative: ' . $e->getMessage());
                    return null;
                }
            }),

            'user' => $this->whenLoaded('user', function () {
                try {
                    return [
                        'id' => $this->user->id,
                        'name' => $this->sanitizeString($this->user->name),
                        'email' => $this->sanitizeEmail($this->user->email),
                        'is_active' => $this->user->is_active,
                        'last_login' => $this->user->last_login_at?->toISOString(),
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error loading member user: ' . $e->getMessage());
                    return null;
                }
            }),

            'savings_accounts' => $this->whenLoaded('savingsAccounts', function () {
                return $this->savingsAccounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'account_number' => $account->account_number,
                        'product_name' => $this->sanitizeString($account->savingsProduct->name ?? 'N/A'),
                        'balance' => $this->formatCurrency($account->balance),
                        'status' => $account->status,
                        'last_transaction' => $account->updated_at?->toISOString(),
                    ];
                });
            }),

            'loan_accounts' => $this->whenLoaded('loanAccounts', function () {
                return $this->loanAccounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'account_number' => $account->account_number,
                        'product_name' => $this->sanitizeString($account->loanProduct->name ?? 'N/A'),
                        'principal_amount' => $this->formatCurrency($account->principal_amount),
                        'outstanding_balance' => $this->formatCurrency($account->outstanding_balance),
                        'status' => $account->status,
                        'next_payment_date' => $account->next_payment_date?->format('Y-m-d'),
                    ];
                });
            }),

            // Statistics with error handling
            'statistics' => $this->when($this->relationLoaded('statistics'), function () {
                try {
                    return [
                        'total_deposits' => $this->formatCurrency($this->statistics->total_deposits ?? 0),
                        'total_withdrawals' => $this->formatCurrency($this->statistics->total_withdrawals ?? 0),
                        'total_loan_payments' => $this->formatCurrency($this->statistics->total_loan_payments ?? 0),
                        'membership_duration_months' => $this->statistics->membership_duration_months ?? 0,
                        'credit_score' => $this->statistics->credit_score ?? 0,
                        'last_activity' => $this->statistics->last_activity_date?->toISOString(),
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error loading member statistics: ' . $e->getMessage());
                    return null;
                }
            }),

            // Risk assessment
            'risk_assessment' => $this->when(
                $request->user()?->can('view_risk_assessment'),
                function () {
                    return [
                        'risk_level' => $this->risk_level ?? 'low',
                        'credit_score' => $this->credit_score ?? 0,
                        'payment_history' => $this->payment_history_score ?? 0,
                    ];
                }
            ),
        ];
    }
}
