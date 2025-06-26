<?php
// app/Http/Resources/Report/IncomeStatementResource.php
namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'cooperative' => [
                'id' => $this->cooperative->id,
                'name' => $this->cooperative->name,
                'code' => $this->cooperative->code,
            ],
            'revenues' => [
                'interest_income' => $this->interest_income,
                'fee_income' => $this->fee_income,
                'other_income' => $this->other_income,
                'total_revenues' => $this->total_revenues,
                'details' => $this->revenue_details,
            ],
            'expenses' => [
                'operating_expenses' => $this->operating_expenses,
                'interest_expenses' => $this->interest_expenses,
                'administrative_expenses' => $this->administrative_expenses,
                'other_expenses' => $this->other_expenses,
                'total_expenses' => $this->total_expenses,
                'details' => $this->expense_details,
            ],
            'net_income' => [
                'gross_income' => $this->gross_income,
                'operating_income' => $this->operating_income,
                'net_income_before_tax' => $this->net_income_before_tax,
                'tax_expense' => $this->tax_expense,
                'net_income' => $this->net_income,
            ],
            'ratios' => [
                'gross_margin' => $this->gross_margin,
                'operating_margin' => $this->operating_margin,
                'net_margin' => $this->net_margin,
                'expense_ratio' => $this->expense_ratio,
            ],
            'generated_at' => $this->generated_at?->toISOString(),
        ];
    }
}
