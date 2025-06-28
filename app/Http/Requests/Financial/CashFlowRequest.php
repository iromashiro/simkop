<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashFlowRequest extends FormRequest
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
                    ->where('report_type', 'cash_flow')
                    ->ignore($this->route('cash_flow'))
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

            // Cash Flow Activities
            'activities' => 'required|array|min:1',
            'activities.*.activity_category' => [
                'required',
                'string',
                'in:operating,investing,financing'
            ],
            'activities.*.activity_description' => 'required|string|max:255',
            'activities.*.current_year_amount' => [
                'required',
                'numeric',
                'max:999999999999.99'
            ],
            'activities.*.previous_year_amount' => [
                'nullable',
                'numeric',
                'max:999999999999.99'
            ],
            'activities.*.note_reference' => 'nullable|string|max:50',
            'activities.*.is_subtotal' => 'sometimes|boolean',
            'activities.*.sort_order' => 'sometimes|integer|min:0|max:9999',

            // Cash balances
            'beginning_cash_balance' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'ending_cash_balance' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ]
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
            'reporting_year.unique' => 'Laporan arus kas untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun pelaporan tidak boleh kurang dari 2020.',
            'reporting_year.max' => 'Tahun pelaporan tidak boleh lebih dari tahun depan.',
            'activities.required' => 'Minimal harus ada satu aktivitas arus kas.',
            'activities.*.activity_category.required' => 'Kategori aktivitas wajib dipilih.',
            'activities.*.activity_category.in' => 'Kategori aktivitas harus salah satu dari: Operasi, Investasi, atau Pendanaan.',
            'activities.*.activity_description.required' => 'Deskripsi aktivitas wajib diisi.',
            'activities.*.current_year_amount.required' => 'Jumlah tahun berjalan wajib diisi.',
            'activities.*.current_year_amount.numeric' => 'Jumlah tahun berjalan harus berupa angka.',
            'beginning_cash_balance.required' => 'Saldo kas awal wajib diisi.',
            'beginning_cash_balance.numeric' => 'Saldo kas awal harus berupa angka.',
            'beginning_cash_balance.min' => 'Saldo kas awal tidak boleh negatif.',
            'ending_cash_balance.required' => 'Saldo kas akhir wajib diisi.',
            'ending_cash_balance.numeric' => 'Saldo kas akhir harus berupa angka.',
            'ending_cash_balance.min' => 'Saldo kas akhir tidak boleh negatif.',
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
            // Validate cash flow consistency
            $this->validateCashFlowConsistency($validator);

            // Validate activity categories
            $this->validateActivityCategories($validator);
        });
    }

    /**
     * Validate cash flow consistency.
     */
    private function validateCashFlowConsistency($validator): void
    {
        $activities = $this->input('activities', []);
        $beginningBalance = (float) $this->input('beginning_cash_balance', 0);
        $endingBalance = (float) $this->input('ending_cash_balance', 0);

        $netCashFlow = 0;

        foreach ($activities as $activity) {
            if (!isset($activity['is_subtotal']) || !$activity['is_subtotal']) {
                $netCashFlow += (float) ($activity['current_year_amount'] ?? 0);
            }
        }

        $calculatedEndingBalance = $beginningBalance + $netCashFlow;
        $difference = abs($endingBalance - $calculatedEndingBalance);

        // Allow small rounding differences (1 rupiah)
        if ($difference > 1) {
            $validator->errors()->add(
                'ending_cash_balance',
                "Saldo kas akhir tidak konsisten. Berdasarkan perhitungan: Rp " . number_format($calculatedEndingBalance, 2) .
                    " (Saldo Awal + Arus Kas Bersih). Selisih: Rp " . number_format($difference, 2)
            );
        }
    }

    /**
     * Validate activity categories.
     */
    private function validateActivityCategories($validator): void
    {
        $activities = $this->input('activities', []);

        $hasOperating = false;
        $hasInvesting = false;
        $hasFinancing = false;

        foreach ($activities as $activity) {
            if (!isset($activity['is_subtotal']) || !$activity['is_subtotal']) {
                switch ($activity['activity_category'] ?? '') {
                    case 'operating':
                        $hasOperating = true;
                        break;
                    case 'investing':
                        $hasInvesting = true;
                        break;
                    case 'financing':
                        $hasFinancing = true;
                        break;
                }
            }
        }

        if (!$hasOperating) {
            $validator->errors()->add(
                'activities',
                'Laporan arus kas harus memiliki minimal satu aktivitas operasi.'
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
        $validated['report_type'] = 'cash_flow';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
