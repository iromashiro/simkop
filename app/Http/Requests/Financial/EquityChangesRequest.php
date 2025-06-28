<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquityChangesRequest extends FormRequest
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
                    ->where('report_type', 'equity_changes')
                    ->ignore($this->route('equity_changes'))
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

            // Equity Changes
            'equity_changes' => 'required|array|min:1',
            'equity_changes.*.equity_component' => [
                'required',
                'string',
                'in:simpanan_pokok,simpanan_wajib,simpanan_sukarela,cadangan,shu_belum_dibagi,laba_ditahan'
            ],
            'equity_changes.*.beginning_balance' => [
                'required',
                'numeric',
                'max:999999999999.99'
            ],
            'equity_changes.*.additions' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'equity_changes.*.reductions' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'equity_changes.*.ending_balance' => [
                'required',
                'numeric',
                'max:999999999999.99'
            ],
            'equity_changes.*.note_reference' => 'nullable|string|max:50',
            'equity_changes.*.sort_order' => 'sometimes|integer|min:0|max:9999'
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
            'reporting_year.unique' => 'Laporan perubahan ekuitas untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun pelaporan tidak boleh kurang dari 2020.',
            'reporting_year.max' => 'Tahun pelaporan tidak boleh lebih dari tahun depan.',
            'equity_changes.required' => 'Minimal harus ada satu komponen ekuitas.',
            'equity_changes.*.equity_component.required' => 'Komponen ekuitas wajib dipilih.',
            'equity_changes.*.equity_component.in' => 'Komponen ekuitas tidak valid.',
            'equity_changes.*.beginning_balance.required' => 'Saldo awal wajib diisi.',
            'equity_changes.*.beginning_balance.numeric' => 'Saldo awal harus berupa angka.',
            'equity_changes.*.additions.numeric' => 'Penambahan harus berupa angka.',
            'equity_changes.*.additions.min' => 'Penambahan tidak boleh negatif.',
            'equity_changes.*.reductions.numeric' => 'Pengurangan harus berupa angka.',
            'equity_changes.*.reductions.min' => 'Pengurangan tidak boleh negatif.',
            'equity_changes.*.ending_balance.required' => 'Saldo akhir wajib diisi.',
            'equity_changes.*.ending_balance.numeric' => 'Saldo akhir harus berupa angka.',
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
            // Validate equity component uniqueness
            $this->validateEquityComponentUniqueness($validator);

            // Validate equity changes calculation
            $this->validateEquityChangesCalculation($validator);
        });
    }

    /**
     * Validate equity component uniqueness within the request.
     */
    private function validateEquityComponentUniqueness($validator): void
    {
        $equityChanges = $this->input('equity_changes', []);
        $components = array_column($equityChanges, 'equity_component');
        $duplicates = array_diff_assoc($components, array_unique($components));

        if (!empty($duplicates)) {
            $validator->errors()->add(
                'equity_changes',
                'Komponen ekuitas tidak boleh duplikat: ' . implode(', ', array_unique($duplicates))
            );
        }
    }

    /**
     * Validate equity changes calculation.
     */
    private function validateEquityChangesCalculation($validator): void
    {
        $equityChanges = $this->input('equity_changes', []);

        foreach ($equityChanges as $index => $change) {
            $beginningBalance = (float) ($change['beginning_balance'] ?? 0);
            $additions = (float) ($change['additions'] ?? 0);
            $reductions = (float) ($change['reductions'] ?? 0);
            $endingBalance = (float) ($change['ending_balance'] ?? 0);

            $calculatedEndingBalance = $beginningBalance + $additions - $reductions;
            $difference = abs($endingBalance - $calculatedEndingBalance);

            // Allow small rounding differences (1 rupiah)
            if ($difference > 1) {
                $validator->errors()->add(
                    "equity_changes.{$index}.ending_balance",
                    "Saldo akhir tidak sesuai perhitungan. Seharusnya: Rp " . number_format($calculatedEndingBalance, 2) .
                        " (Saldo Awal + Penambahan - Pengurangan)"
                );
            }
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
        $validated['report_type'] = 'equity_changes';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
