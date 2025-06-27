<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidFinancialAmount implements Rule
{
    private $maxAmount;
    private $allowNegative;

    public function __construct(float $maxAmount = 999999999999.99, bool $allowNegative = false)
    {
        $this->maxAmount = $maxAmount;
        $this->allowNegative = $allowNegative;
    }

    public function passes($attribute, $value)
    {
        // Check if numeric
        if (!is_numeric($value)) {
            return false;
        }

        $numericValue = (float) $value;

        // Check negative values
        if (!$this->allowNegative && $numericValue < 0) {
            return false;
        }

        // Check maximum amount
        if (abs($numericValue) > $this->maxAmount) {
            return false;
        }

        // Check for suspicious patterns (too many zeros)
        $valueStr = (string) abs($numericValue);
        if (preg_match('/^[0-9]*0{8,}$/', str_replace('.', '', $valueStr))) {
            return false; // Suspicious amount like 1000000000
        }

        // Check decimal places (max 2)
        if (strpos($valueStr, '.') !== false) {
            $decimalPart = substr($valueStr, strpos($valueStr, '.') + 1);
            if (strlen($decimalPart) > 2) {
                return false;
            }
        }

        return true;
    }

    public function message()
    {
        return 'Jumlah keuangan tidak valid. Pastikan format angka benar dan tidak melebihi batas maksimal.';
    }
}
