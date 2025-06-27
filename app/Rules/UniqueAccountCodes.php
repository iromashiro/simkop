<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class UniqueAccountCodes implements Rule
{
    public function passes($attribute, $value)
    {
        if (!is_array($value)) {
            return false;
        }

        $allCodes = [];

        foreach (['assets', 'liabilities', 'equity'] as $category) {
            if (isset($value[$category]) && is_array($value[$category])) {
                foreach ($value[$category] as $account) {
                    if (isset($account['account_code'])) {
                        $code = trim($account['account_code']);
                        if (in_array($code, $allCodes)) {
                            return false; // Duplicate found
                        }
                        $allCodes[] = $code;
                    }
                }
            }
        }

        return true;
    }

    public function message()
    {
        return 'Terdapat kode akun yang duplikat. Setiap kode akun harus unik.';
    }
}
