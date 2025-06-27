<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class BalanceSheetEquation implements Rule
{
    private $tolerance;
    private $difference;

    public function __construct(float $tolerance = 0.01)
    {
        $this->tolerance = $tolerance;
    }

    public function passes($attribute, $value)
    {
        if (!is_array($value)) {
            return false;
        }

        $totalAssets = $this->calculateCategoryTotal($value, 'assets');
        $totalLiabilities = $this->calculateCategoryTotal($value, 'liabilities');
        $totalEquity = $this->calculateCategoryTotal($value, 'equity');

        $this->difference = $totalAssets - ($totalLiabilities + $totalEquity);

        return abs($this->difference) <= $this->tolerance;
    }

    public function message()
    {
        $formattedDifference = number_format(abs($this->difference), 2, ',', '.');
        return "Persamaan neraca tidak seimbang. Selisih: Rp {$formattedDifference}. Total Aset harus sama dengan Total Liabilitas + Total Ekuitas.";
    }

    private function calculateCategoryTotal(array $accounts, string $category): float
    {
        if (!isset($accounts[$category]) || !is_array($accounts[$category])) {
            return 0;
        }

        $total = 0;
        foreach ($accounts[$category] as $account) {
            if (isset($account['current_year_amount']) && is_numeric($account['current_year_amount'])) {
                $total += (float) $account['current_year_amount'];
            }
        }

        return $total;
    }
}
