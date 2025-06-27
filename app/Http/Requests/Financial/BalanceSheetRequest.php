<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\BalanceSheetEquation;
use App\Rules\ValidFinancialAmount;
use App\Rules\UniqueAccountCodes;

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

            // ✅ ENHANCED: Add custom validation rules
            'accounts' => [
                'required',
                'array',
                new UniqueAccountCodes(),
                new BalanceSheetEquation(),
            ],

            // Assets
            'accounts.assets' => 'required|array|min:1',
            'accounts.assets.*.account_code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/', // ✅ ADDED: Only alphanumeric uppercase
            ],
            'accounts.assets.*.account_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\(\)\.]+$/', // ✅ ADDED: Safe characters only
            ],
            'accounts.assets.*.account_subcategory' => 'required|in:current_asset,fixed_asset,other_asset',
            'accounts.assets.*.current_year_amount' => [
                'required',
                new ValidFinancialAmount(),
            ],
            'accounts.assets.*.previous_year_amount' => [
                'nullable',
                new ValidFinancialAmount(),
            ],
            'accounts.assets.*.note_reference' => 'nullable|string|max:5|regex:/^[0-9a-zA-Z]+$/',
            'accounts.assets.*.sort_order' => 'nullable|integer|min:0|max:999',

            // Liabilities
            'accounts.liabilities' => 'required|array|min:1',
            'accounts.liabilities.*.account_code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/',
            ],
            'accounts.liabilities.*.account_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\(\)\.]+$/',
            ],
            'accounts.liabilities.*.account_subcategory' => 'required|in:current_liability,long_term_liability',
            'accounts.liabilities.*.current_year_amount' => [
                'required',
                new ValidFinancialAmount(),
            ],
            'accounts.liabilities.*.previous_year_amount' => [
                'nullable',
                new ValidFinancialAmount(),
            ],
            'accounts.liabilities.*.note_reference' => 'nullable|string|max:5|regex:/^[0-9a-zA-Z]+$/',
            'accounts.liabilities.*.sort_order' => 'nullable|integer|min:0|max:999',

            // Equity
            'accounts.equity' => 'required|array|min:1',
            'accounts.equity.*.account_code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/',
            ],
            'accounts.equity.*.account_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\(\)\.]+$/',
            ],
            'accounts.equity.*.account_subcategory' => 'required|in:member_equity,retained_earnings,other_equity',
            'accounts.equity.*.current_year_amount' => [
                'required',
                new ValidFinancialAmount(),
            ],
            'accounts.equity.*.previous_year_amount' => [
                'nullable',
                new ValidFinancialAmount(),
            ],
            'accounts.equity.*.note_reference' => 'nullable|string|max:5|regex:/^[0-9a-zA-Z]+$/',
            'accounts.equity.*.sort_order' => 'nullable|integer|min:0|max:999',
        ];
    }

    // ✅ REMOVED: withValidator method - using custom rules instead

    public function messages(): array
    {
        return [
            'cooperative_id.required' => 'Koperasi harus dipilih.',
            'cooperative_id.exists' => 'Koperasi tidak valid.',
            'reporting_year.required' => 'Tahun laporan harus diisi.',
            'reporting_year.integer' => 'Tahun laporan harus berupa angka.',
            'reporting_year.min' => 'Tahun laporan minimal 2020.',
            'reporting_year.max' => 'Tahun laporan maksimal ' . (date('Y') + 1) . '.',

            'accounts.required' => 'Data akun harus diisi.',
            'accounts.assets.required' => 'Data aset harus diisi.',
            'accounts.assets.min' => 'Minimal harus ada 1 akun aset.',
            'accounts.liabilities.required' => 'Data liabilitas harus diisi.',
            'accounts.liabilities.min' => 'Minimal harus ada 1 akun liabilitas.',
            'accounts.equity.required' => 'Data ekuitas harus diisi.',
            'accounts.equity.min' => 'Minimal harus ada 1 akun ekuitas.',

            '*.account_code.required' => 'Kode akun harus diisi.',
            '*.account_code.max' => 'Kode akun maksimal 10 karakter.',
            '*.account_code.regex' => 'Kode akun hanya boleh mengandung huruf besar dan angka.',
            '*.account_name.required' => 'Nama akun harus diisi.',
            '*.account_name.max' => 'Nama akun maksimal 255 karakter.',
            '*.account_name.regex' => 'Nama akun mengandung karakter yang tidak diizinkan.',
            '*.account_subcategory.required' => 'Subkategori akun harus dipilih.',
            '*.current_year_amount.required' => 'Jumlah tahun berjalan harus diisi.',
            '*.note_reference.regex' => 'Referensi catatan hanya boleh mengandung huruf dan angka.',
            '*.sort_order.integer' => 'Urutan harus berupa angka.',
            '*.sort_order.min' => 'Urutan tidak boleh negatif.',
            '*.sort_order.max' => 'Urutan maksimal 999.',
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

                    // ✅ ADDED: Clean account code and name
                    if (isset($account['account_code'])) {
                        $accounts[$category][$index]['account_code'] =
                            strtoupper(trim($account['account_code']));
                    }
                    if (isset($account['account_name'])) {
                        $accounts[$category][$index]['account_name'] =
                            trim($account['account_name']);
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
