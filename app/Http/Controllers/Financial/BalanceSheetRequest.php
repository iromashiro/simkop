<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\BalanceSheetEquation;

class BalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (
            auth()->user()->can('create_balance_sheet') ||
            auth()->user()->can('edit_balance_sheet')
        );
    }

    public function rules(): array
    {
        return [
            'cooperative_id' => 'required|exists:cooperatives,id',
            'reporting_year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'notes' => 'nullable|string|max:2000',

            // Assets
            'accounts.assets' => 'required|array|min:1',
            'accounts.assets.*.account_code' => 'required|string|max:10',
            'accounts.assets.*.account_name' => 'required|string|max:255',
            'accounts.assets.*.account_subcategory' => 'required|in:current_asset,fixed_asset,other_asset',
            'accounts.assets.*.current_year_amount' => 'required|numeric|min:0|max:999999999999.99',
            'accounts.assets.*.previous_year_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'accounts.assets.*.note_reference' => 'nullable|string|max:5',
            'accounts.assets.*.sort_order' => 'nullable|integer|min:0',

            // Liabilities
            'accounts.liabilities' => 'required|array|min:1',
            'accounts.liabilities.*.account_code' => 'required|string|max:10',
            'accounts.liabilities.*.account_name' => 'required|string|max:255',
            'accounts.liabilities.*.account_subcategory' => 'required|in:current_liability,long_term_liability',
            'accounts.liabilities.*.current_year_amount' => 'required|numeric|min:0|max:999999999999.99',
            'accounts.liabilities.*.previous_year_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'accounts.liabilities.*.note_reference' => 'nullable|string|max:5',
            'accounts.liabilities.*.sort_order' => 'nullable|integer|min:0',

            // Equity
            'accounts.equity' => 'required|array|min:1',
            'accounts.equity.*.account_code' => 'required|string|max:10',
            'accounts.equity.*.account_name' => 'required|string|max:255',
            'accounts.equity.*.account_subcategory' => 'required|in:member_equity,retained_earnings,other_equity',
            'accounts.equity.*.current_year_amount' => 'required|numeric|min:0|max:999999999999.99',
            'accounts.equity.*.previous_year_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'accounts.equity.*.note_reference' => 'nullable|string|max:5',
            'accounts.equity.*.sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateBalanceSheetEquation($validator);
            $this->validateAccountCodes($validator);
            $this->validateAmountConsistency($validator);
        });
    }

    protected function validateBalanceSheetEquation($validator)
    {
        $accounts = $this->input('accounts', []);

        $totalAssets = collect($accounts['assets'] ?? [])->sum('current_year_amount');
        $totalLiabilities = collect($accounts['liabilities'] ?? [])->sum('current_year_amount');
        $totalEquity = collect($accounts['equity'] ?? [])->sum('current_year_amount');

        $difference = abs($totalAssets - ($totalLiabilities + $totalEquity));

        if ($difference > 0.01) {
            $validator->errors()->add(
                'balance_equation',
                'Total Aset harus sama dengan Total Liabilitas + Total Ekuitas. ' .
                    'Selisih: Rp ' . number_format($difference, 2, ',', '.')
            );
        }
    }

    protected function validateAccountCodes($validator)
    {
        $accounts = $this->input('accounts', []);
        $allCodes = [];

        foreach (['assets', 'liabilities', 'equity'] as $category) {
            if (isset($accounts[$category])) {
                foreach ($accounts[$category] as $index => $account) {
                    $code = $account['account_code'] ?? '';
                    if (in_array($code, $allCodes)) {
                        $validator->errors()->add(
                            "accounts.{$category}.{$index}.account_code",
                            "Kode akun {$code} sudah digunakan."
                        );
                    }
                    $allCodes[] = $code;
                }
            }
        }
    }

    protected function validateAmountConsistency($validator)
    {
        $accounts = $this->input('accounts', []);

        foreach (['assets', 'liabilities', 'equity'] as $category) {
            if (isset($accounts[$category])) {
                foreach ($accounts[$category] as $index => $account) {
                    $currentAmount = $account['current_year_amount'] ?? 0;
                    $previousAmount = $account['previous_year_amount'] ?? 0;

                    // Check for unrealistic changes (more than 1000% increase)
                    if ($previousAmount > 0 && $currentAmount > ($previousAmount * 10)) {
                        $validator->errors()->add(
                            "accounts.{$category}.{$index}.current_year_amount",
                            "Perubahan jumlah terlalu besar untuk akun {$account['account_name']}. Mohon periksa kembali."
                        );
                    }
                }
            }
        }
    }

    public function messages(): array
    {
        return [
            'cooperative_id.required' => 'Koperasi harus dipilih.',
            'cooperative_id.exists' => 'Koperasi tidak valid.',
            'reporting_year.required' => 'Tahun laporan harus diisi.',
            'reporting_year.integer' => 'Tahun laporan harus berupa angka.',
            'reporting_year.min' => 'Tahun laporan minimal 2020.',
            'reporting_year.max' => 'Tahun laporan maksimal ' . (date('Y') + 1) . '.',

            'accounts.assets.required' => 'Data aset harus diisi.',
            'accounts.assets.min' => 'Minimal harus ada 1 akun aset.',
            'accounts.liabilities.required' => 'Data liabilitas harus diisi.',
            'accounts.liabilities.min' => 'Minimal harus ada 1 akun liabilitas.',
            'accounts.equity.required' => 'Data ekuitas harus diisi.',
            'accounts.equity.min' => 'Minimal harus ada 1 akun ekuitas.',

            '*.account_code.required' => 'Kode akun harus diisi.',
            '*.account_code.max' => 'Kode akun maksimal 10 karakter.',
            '*.account_name.required' => 'Nama akun harus diisi.',
            '*.account_name.max' => 'Nama akun maksimal 255 karakter.',
            '*.account_subcategory.required' => 'Subkategori akun harus dipilih.',
            '*.current_year_amount.required' => 'Jumlah tahun berjalan harus diisi.',
            '*.current_year_amount.numeric' => 'Jumlah tahun berjalan harus berupa angka.',
            '*.current_year_amount.min' => 'Jumlah tahun berjalan tidak boleh negatif.',
            '*.current_year_amount.max' => 'Jumlah tahun berjalan terlalu besar.',
            '*.previous_year_amount.numeric' => 'Jumlah tahun sebelumnya harus berupa angka.',
            '*.previous_year_amount.min' => 'Jumlah tahun sebelumnya tidak boleh negatif.',
            '*.previous_year_amount.max' => 'Jumlah tahun sebelumnya terlalu besar.',
        ];
    }

    protected function prepareForValidation()
    {
        // Ensure cooperative_id is set for non-admin users
        if (!auth()->user()->isAdminDinas() && !$this->has('cooperative_id')) {
            $this->merge([
                'cooperative_id' => auth()->user()->cooperative_id
            ]);
        }

        // Clean up numeric values
        $accounts = $this->input('accounts', []);

        foreach (['assets', 'liabilities', 'equity'] as $category) {
            if (isset($accounts[$category])) {
                foreach ($accounts[$category] as $index => $account) {
                    // Remove formatting from amounts
                    if (isset($account['current_year_amount'])) {
                        $accounts[$category][$index]['current_year_amount'] =
                            $this->cleanNumericValue($account['current_year_amount']);
                    }
                    if (isset($account['previous_year_amount'])) {
                        $accounts[$category][$index]['previous_year_amount'] =
                            $this->cleanNumericValue($account['previous_year_amount']);
                    }
                }
            }
        }

        $this->merge(['accounts' => $accounts]);
    }

    private function cleanNumericValue($value)
    {
        if (is_string($value)) {
            // Remove thousand separators and convert comma to dot
            $value = str_replace(['.', ','], ['', '.'], $value);
            $value = preg_replace('/[^0-9.]/', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }
}
