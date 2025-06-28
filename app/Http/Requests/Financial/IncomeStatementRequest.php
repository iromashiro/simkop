<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['admin_koperasi', 'admin_dinas']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'reporting_year' => [
                'required',
                'integer',
                'min:2020',
                'max:' . (now()->year + 1),
                Rule::unique('financial_reports')
                    ->where('cooperative_id', $this->user()->cooperative_id)
                    ->where('report_type', 'income_statement')
                    ->ignore($this->route('income_statement'))
            ],
            'reporting_period' => [
                'required',
                'string',
                'in:Q1,Q2,Q3,Q4,annual'
            ],
            'status' => [
                'sometimes',
                'string',
                'in:draft,submitted,approved,rejected'
            ],
            'notes' => 'nullable|string|max:5000',

            // Income Statement Accounts
            'accounts' => 'required|array|min:1',
            'accounts.*.account_code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\-\.]+$/'
            ],
            'accounts.*.account_name' => 'required|string|max:255',
            'accounts.*.account_category' => [
                'required',
                'string',
                'in:revenue,expense,other_income,other_expense'
            ],
            'accounts.*.account_subcategory' => 'nullable|string|max:100',
            'accounts.*.current_year_amount' => [
                'required',
                'numeric',
                'max:999999999999.99'
            ],
            'accounts.*.previous_year_amount' => [
                'nullable',
                'numeric',
                'max:999999999999.99'
            ],
            'accounts.*.note_reference' => 'nullable|string|max:50',
            'accounts.*.is_subtotal' => 'sometimes|boolean',
            'accounts.*.parent_account_code' => 'nullable|string|max:20',
            'accounts.*.sort_order' => 'sometimes|integer|min:0|max:9999'
        ];

        // Additional validation for admin_dinas
        if ($this->user()->hasRole('admin_dinas')) {
            $rules['cooperative_id'] = [
                'required',
                'exists:cooperatives,id'
            ];
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'reporting_year.unique' => 'Laporan laba rugi untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun pelaporan tidak boleh kurang dari 2020.',
            'reporting_year.max' => 'Tahun pelaporan tidak boleh lebih dari tahun depan.',
            'accounts.required' => 'Minimal harus ada satu akun.',
            'accounts.*.account_code.required' => 'Kode akun wajib diisi.',
            'accounts.*.account_code.regex' => 'Kode akun hanya boleh mengandung huruf besar, angka, tanda hubung, dan titik.',
            'accounts.*.account_name.required' => 'Nama akun wajib diisi.',
            'accounts.*.account_category.required' => 'Kategori akun wajib dipilih.',
            'accounts.*.account_category.in' => 'Kategori akun harus salah satu dari: Pendapatan, Beban, Pendapatan Lain, atau Beban Lain.',
            'accounts.*.current_year_amount.required' => 'Jumlah tahun berjalan wajib diisi.',
            'accounts.*.current_year_amount.numeric' => 'Jumlah tahun berjalan harus berupa angka.',
            'accounts.*.current_year_amount.max' => 'Jumlah terlalu besar.',
            'cooperative_id.required' => 'Koperasi wajib dipilih.',
            'cooperative_id.exists' => 'Koperasi tidak ditemukan.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate account code uniqueness within the request
            $this->validateAccountCodeUniqueness($validator);

            // Validate parent-child relationships
            $this->validateParentChildRelationships($validator);

            // Validate income statement logic
            $this->validateIncomeStatementLogic($validator);
        });
    }

    /**
     * Validate account code uniqueness within the request.
     */
    private function validateAccountCodeUniqueness($validator): void
    {
        $accounts = $this->input('accounts', []);
        $accountCodes = array_column($accounts, 'account_code');
        $duplicates = array_diff_assoc($accountCodes, array_unique($accountCodes));

        if (!empty($duplicates)) {
            $validator->errors()->add(
                'accounts',
                'Kode akun tidak boleh duplikat: ' . implode(', ', array_unique($duplicates))
            );
        }
    }

    /**
     * Validate parent-child relationships.
     */
    private function validateParentChildRelationships($validator): void
    {
        $accounts = $this->input('accounts', []);
        $accountCodes = array_column($accounts, 'account_code');

        foreach ($accounts as $index => $account) {
            if (!empty($account['parent_account_code'])) {
                if (!in_array($account['parent_account_code'], $accountCodes)) {
                    $validator->errors()->add(
                        "accounts.{$index}.parent_account_code",
                        'Akun induk tidak ditemukan dalam daftar akun.'
                    );
                }

                // Prevent circular references
                if ($account['parent_account_code'] === $account['account_code']) {
                    $validator->errors()->add(
                        "accounts.{$index}.parent_account_code",
                        'Akun tidak boleh menjadi induk dari dirinya sendiri.'
                    );
                }
            }
        }
    }

    /**
     * Validate income statement logic.
     */
    private function validateIncomeStatementLogic($validator): void
    {
        $accounts = $this->input('accounts', []);

        $hasRevenue = false;
        $hasExpense = false;

        foreach ($accounts as $account) {
            if (!isset($account['is_subtotal']) || !$account['is_subtotal']) {
                switch ($account['account_category'] ?? '') {
                    case 'revenue':
                        $hasRevenue = true;
                        break;
                    case 'expense':
                        $hasExpense = true;
                        break;
                }
            }
        }

        if (!$hasRevenue) {
            $validator->errors()->add(
                'accounts',
                'Laporan laba rugi harus memiliki minimal satu akun pendapatan.'
            );
        }

        if (!$hasExpense) {
            $validator->errors()->add(
                'accounts',
                'Laporan laba rugi harus memiliki minimal satu akun beban.'
            );
        }
    }

    /**
     * Get validated data with additional processing.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Set cooperative_id for admin_koperasi
        if ($this->user()->hasRole('admin_koperasi')) {
            $validated['cooperative_id'] = $this->user()->cooperative_id;
        }

        // Set report_type
        $validated['report_type'] = 'income_statement';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
