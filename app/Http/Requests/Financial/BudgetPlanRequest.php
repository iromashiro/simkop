<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetPlanRequest extends FormRequest
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
                'min:' . now()->year,
                'max:' . (now()->year + 5), // Budget can be planned up to 5 years ahead
                Rule::unique('financial_reports')
                    ->where('cooperative_id', $this->user()->cooperative_id)
                    ->where('report_type', 'budget_plan')
                    ->ignore($this->route('budget_plan'))
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
            'budget_type' => [
                'required',
                'string',
                'in:operational,capital,strategic'
            ],

            // Budget Plans
            'budget_plans' => 'required|array|min:1',
            'budget_plans.*.budget_category' => [
                'required',
                'string',
                'in:revenue,expense,investment,financing'
            ],
            'budget_plans.*.budget_subcategory' => 'required|string|max:100',
            'budget_plans.*.budget_item' => 'required|string|max:255',
            'budget_plans.*.budget_description' => 'nullable|string|max:500',
            'budget_plans.*.planned_amount' => [
                'required',
                'numeric',
                'max:999999999999.99'
            ],
            'budget_plans.*.previous_year_actual' => [
                'nullable',
                'numeric',
                'max:999999999999.99'
            ],
            'budget_plans.*.variance_percentage' => [
                'nullable',
                'numeric',
                'min:-100',
                'max:1000'
            ],
            'budget_plans.*.priority_level' => [
                'required',
                'string',
                'in:high,medium,low'
            ],
            'budget_plans.*.quarter_1_allocation' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'budget_plans.*.quarter_2_allocation' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'budget_plans.*.quarter_3_allocation' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'budget_plans.*.quarter_4_allocation' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'budget_plans.*.responsible_department' => 'nullable|string|max:100',
            'budget_plans.*.approval_required' => 'sometimes|boolean',
            'budget_plans.*.note_reference' => 'nullable|string|max:50',
            'budget_plans.*.sort_order' => 'sometimes|integer|min:0|max:9999'
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
            'reporting_year.unique' => 'Rencana anggaran untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun perencanaan tidak boleh kurang dari tahun berjalan.',
            'reporting_year.max' => 'Tahun perencanaan tidak boleh lebih dari 5 tahun ke depan.',
            'budget_type.required' => 'Jenis anggaran wajib dipilih.',
            'budget_type.in' => 'Jenis anggaran harus salah satu dari: Operasional, Modal, atau Strategis.',
            'budget_plans.required' => 'Minimal harus ada satu item anggaran.',
            'budget_plans.*.budget_category.required' => 'Kategori anggaran wajib dipilih.',
            'budget_plans.*.budget_category.in' => 'Kategori anggaran harus salah satu dari: Pendapatan, Beban, Investasi, atau Pendanaan.',
            'budget_plans.*.budget_subcategory.required' => 'Subkategori anggaran wajib diisi.',
            'budget_plans.*.budget_item.required' => 'Item anggaran wajib diisi.',
            'budget_plans.*.planned_amount.required' => 'Jumlah yang direncanakan wajib diisi.',
            'budget_plans.*.planned_amount.numeric' => 'Jumlah yang direncanakan harus berupa angka.',
            'budget_plans.*.planned_amount.max' => 'Jumlah yang direncanakan terlalu besar.',
            'budget_plans.*.previous_year_actual.numeric' => 'Realisasi tahun sebelumnya harus berupa angka.',
            'budget_plans.*.variance_percentage.numeric' => 'Persentase varians harus berupa angka.',
            'budget_plans.*.variance_percentage.min' => 'Persentase varians tidak boleh kurang dari -100%.',
            'budget_plans.*.variance_percentage.max' => 'Persentase varians tidak boleh lebih dari 1000%.',
            'budget_plans.*.priority_level.required' => 'Tingkat prioritas wajib dipilih.',
            'budget_plans.*.priority_level.in' => 'Tingkat prioritas harus salah satu dari: Tinggi, Sedang, atau Rendah.',
            'budget_plans.*.quarter_1_allocation.numeric' => 'Alokasi Q1 harus berupa angka.',
            'budget_plans.*.quarter_1_allocation.min' => 'Alokasi Q1 tidak boleh negatif.',
            'budget_plans.*.quarter_1_allocation.max' => 'Alokasi Q1 tidak boleh lebih dari 100%.',
            'budget_plans.*.quarter_2_allocation.numeric' => 'Alokasi Q2 harus berupa angka.',
            'budget_plans.*.quarter_2_allocation.min' => 'Alokasi Q2 tidak boleh negatif.',
            'budget_plans.*.quarter_2_allocation.max' => 'Alokasi Q2 tidak boleh lebih dari 100%.',
            'budget_plans.*.quarter_3_allocation.numeric' => 'Alokasi Q3 harus berupa angka.',
            'budget_plans.*.quarter_3_allocation.min' => 'Alokasi Q3 tidak boleh negatif.',
            'budget_plans.*.quarter_3_allocation.max' => 'Alokasi Q3 tidak boleh lebih dari 100%.',
            'budget_plans.*.quarter_4_allocation.numeric' => 'Alokasi Q4 harus berupa angka.',
            'budget_plans.*.quarter_4_allocation.min' => 'Alokasi Q4 tidak boleh negatif.',
            'budget_plans.*.quarter_4_allocation.max' => 'Alokasi Q4 tidak boleh lebih dari 100%.',
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
            // Validate quarterly allocation totals
            $this->validateQuarterlyAllocations($validator);

            // Validate budget balance
            $this->validateBudgetBalance($validator);

            // Validate variance calculation
            $this->validateVarianceCalculation($validator);
        });
    }

    /**
     * Validate quarterly allocation totals.
     */
    private function validateQuarterlyAllocations($validator): void
    {
        $budgetPlans = $this->input('budget_plans', []);

        foreach ($budgetPlans as $index => $plan) {
            $q1 = (float) ($plan['quarter_1_allocation'] ?? 0);
            $q2 = (float) ($plan['quarter_2_allocation'] ?? 0);
            $q3 = (float) ($plan['quarter_3_allocation'] ?? 0);
            $q4 = (float) ($plan['quarter_4_allocation'] ?? 0);

            $totalAllocation = $q1 + $q2 + $q3 + $q4;

            // If any quarterly allocation is provided, total should be 100%
            if ($totalAllocation > 0 && abs($totalAllocation - 100) > 0.01) {
                $validator->errors()->add(
                    "budget_plans.{$index}",
                    "Total alokasi kuartalan harus 100%. Saat ini: {$totalAllocation}%"
                );
            }
        }
    }

    /**
     * Validate budget balance (revenue vs expense).
     */
    private function validateBudgetBalance($validator): void
    {
        $budgetPlans = $this->input('budget_plans', []);

        $totalRevenue = 0;
        $totalExpense = 0;
        $totalInvestment = 0;
        $totalFinancing = 0;

        foreach ($budgetPlans as $plan) {
            $amount = (float) ($plan['planned_amount'] ?? 0);

            switch ($plan['budget_category'] ?? '') {
                case 'revenue':
                    $totalRevenue += $amount;
                    break;
                case 'expense':
                    $totalExpense += $amount;
                    break;
                case 'investment':
                    $totalInvestment += $amount;
                    break;
                case 'financing':
                    $totalFinancing += $amount;
                    break;
            }
        }

        // Warning if expenses exceed revenue significantly
        if ($totalExpense > $totalRevenue * 1.2) {
            $validator->errors()->add(
                'budget_plans',
                'Peringatan: Total beban melebihi 120% dari total pendapatan. ' .
                    'Pendapatan: Rp ' . number_format($totalRevenue, 2) .
                    ', Beban: Rp ' . number_format($totalExpense, 2)
            );
        }
    }

    /**
     * Validate variance calculation.
     */
    private function validateVarianceCalculation($validator): void
    {
        $budgetPlans = $this->input('budget_plans', []);

        foreach ($budgetPlans as $index => $plan) {
            $plannedAmount = (float) ($plan['planned_amount'] ?? 0);
            $previousYearActual = (float) ($plan['previous_year_actual'] ?? 0);
            $variancePercentage = (float) ($plan['variance_percentage'] ?? 0);

            if ($previousYearActual > 0 && $variancePercentage !== 0) {
                $calculatedVariance = (($plannedAmount - $previousYearActual) / $previousYearActual) * 100;
                $difference = abs($variancePercentage - $calculatedVariance);

                // Allow small rounding differences (0.1%)
                if ($difference > 0.1) {
                    $validator->errors()->add(
                        "budget_plans.{$index}.variance_percentage",
                        "Persentase varians tidak sesuai perhitungan. Seharusnya: " . number_format($calculatedVariance, 2) . "%"
                    );
                }
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
        $validated['report_type'] = 'budget_plan';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
