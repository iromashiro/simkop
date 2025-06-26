<?php
// app/Http/Resources/Cooperative/CooperativeResource.php
namespace App\Http\Resources\Cooperative;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CooperativeResource extends BaseResource
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
            'name' => $this->sanitizeString($this->name),
            'code' => $this->code,
            'registration_number' => $this->registration_number,
            'address' => $this->sanitizeString($this->address),
            'phone' => $this->sanitizePhone($this->phone),
            'email' => $this->sanitizeEmail($this->email),
            'website' => $this->website,
            'established_date' => $this->established_date?->format('Y-m-d'),
            'legal_status' => $this->legal_status,
            'business_type' => $this->business_type,
            'total_members' => $this->total_members,
            'total_assets' => $this->formatCurrency($this->total_assets),
            'status' => $this->status,

            // Sensitive data with permission check
            'settings' => $this->when(
                $this->canViewSensitiveData($request) && $request->user()?->can('view_cooperative_settings'),
                $this->settings
            ),

            'financial_details' => $this->when(
                $this->canViewSensitiveData($request),
                [
                    'bank_account' => $this->bank_account,
                    'tax_number' => $this->tax_number,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships with caching
            'members_count' => $this->whenCounted('members'),
            'users_count' => $this->whenCounted('users'),
            'savings_accounts_count' => $this->whenCounted('savingsAccounts'),
            'loan_accounts_count' => $this->whenCounted('loanAccounts'),

            // Statistics with error handling
            'statistics' => $this->when($this->relationLoaded('statistics'), function () {
                try {
                    return [
                        'total_savings' => $this->formatCurrency($this->statistics->total_savings ?? 0),
                        'total_loans' => $this->formatCurrency($this->statistics->total_loans ?? 0),
                        'total_deposits' => $this->formatCurrency($this->statistics->total_deposits ?? 0),
                        'active_members' => $this->statistics->active_members ?? 0,
                        'growth_rate' => round($this->statistics->growth_rate ?? 0, 2),
                        'last_updated' => $this->statistics->updated_at?->toISOString(),
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error loading cooperative statistics: ' . $e->getMessage());
                    return null;
                }
            }),

            // Performance metrics
            'performance_metrics' => $this->when(
                $request->user()?->can('view_performance_metrics'),
                function () {
                    return [
                        'member_satisfaction' => $this->member_satisfaction_score ?? 0,
                        'financial_health' => $this->financial_health_score ?? 0,
                        'operational_efficiency' => $this->operational_efficiency_score ?? 0,
                    ];
                }
            ),
        ];
    }
}
