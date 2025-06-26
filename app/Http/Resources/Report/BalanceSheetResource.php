<?php
// app/Http/Resources/Report/BalanceSheetResource.php
namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceSheetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'report_date' => $this->report_date,
            'cooperative' => [
                'id' => $this->cooperative->id,
                'name' => $this->cooperative->name,
                'code' => $this->cooperative->code,
            ],
            'assets' => [
                'current_assets' => $this->current_assets,
                'fixed_assets' => $this->fixed_assets,
                'other_assets' => $this->other_assets,
                'total_assets' => $this->total_assets,
                'details' => $this->asset_details,
            ],
            'liabilities' => [
                'current_liabilities' => $this->current_liabilities,
                'long_term_liabilities' => $this->long_term_liabilities,
                'total_liabilities' => $this->total_liabilities,
                'details' => $this->liability_details,
            ],
            'equity' => [
                'member_equity' => $this->member_equity,
                'retained_earnings' => $this->retained_earnings,
                'current_year_earnings' => $this->current_year_earnings,
                'total_equity' => $this->total_equity,
                'details' => $this->equity_details,
            ],
            'totals' => [
                'total_liabilities_and_equity' => $this->total_liabilities_and_equity,
                'balance_check' => $this->total_assets === $this->total_liabilities_and_equity,
            ],
            'generated_at' => $this->generated_at?->toISOString(),
        ];
    }
}
