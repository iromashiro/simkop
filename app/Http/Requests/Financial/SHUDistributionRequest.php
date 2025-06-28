<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SHUDistributionRequest extends FormRequest
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
                    ->where('report_type', 'shu_distribution')
                    ->ignore($this->route('shu_distribution'))
            ],
            'reporting_period' => [
                'required',
                'string',
                'in:annual' // SHU distribution is typically annual
            ],
            'status' => [
                'sometimes',
                'string',
                'in:draft,submitted,approved,rejected'
            ],
            'notes' => 'nullable|string|max:5000',

            // SHU Distribution Summary
            'total_shu' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'distribution_date' => 'required|date|after_or_equal:' . now()->subYear()->format('Y-01-01'),

            // SHU Distribution Details
            'shu_distributions' => 'required|array|min:1',
            'shu_distributions.*.member_id' => 'required|string|max:50',
            'shu_distributions.*.member_name' => 'required|string|max:255',
            'shu_distributions.*.member_type' => [
                'required',
                'string',
                'in:active,inactive,new'
            ],
            'shu_distributions.*.savings_contribution' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'shu_distributions.*.transaction_contribution' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'shu_distributions.*.shu_from_savings' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'shu_distributions.*.shu_from_transactions' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'shu_distributions.*.total_shu_received' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'shu_distributions.*.tax_deduction' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'shu_distributions.*.net_shu_received' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'shu_distributions.*.payment_method' => [
                'required',
                'string',
                'in:cash,transfer,savings_account'
            ],
            'shu_distributions.*.payment_status' => [
                'required',
                'string',
                'in:pending,paid,cancelled'
            ],
            'shu_distributions.*.note_reference' => 'nullable|string|max:50'
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
            'reporting_year.unique' => 'Laporan distribusi SHU untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun pelaporan tidak boleh kurang dari 2020.',
            'reporting_year.max' => 'Tahun pelaporan tidak boleh lebih dari tahun depan.',
            'reporting_period.in' => 'Distribusi SHU hanya dilakukan secara tahunan.',
            'total_shu.required' => 'Total SHU wajib diisi.',
            'total_shu.numeric' => 'Total SHU harus berupa angka.',
            'total_shu.min' => 'Total SHU tidak boleh negatif.',
            'distribution_date.required' => 'Tanggal distribusi wajib diisi.',
            'distribution_date.date' => 'Tanggal distribusi harus berupa tanggal yang valid.',
            'distribution_date.after_or_equal' => 'Tanggal distribusi tidak boleh lebih dari 1 tahun yang lalu.',
            'shu_distributions.required' => 'Minimal harus ada satu data distribusi SHU.',
            'shu_distributions.*.member_id.required' => 'ID anggota wajib diisi.',
            'shu_distributions.*.member_name.required' => 'Nama anggota wajib diisi.',
            'shu_distributions.*.member_type.required' => 'Tipe anggota wajib dipilih.',
            'shu_distributions.*.member_type.in' => 'Tipe anggota harus salah satu dari: Aktif, Tidak Aktif, atau Baru.',
            'shu_distributions.*.savings_contribution.required' => 'Kontribusi simpanan wajib diisi.',
            'shu_distributions.*.savings_contribution.numeric' => 'Kontribusi simpanan harus berupa angka.',
            'shu_distributions.*.savings_contribution.min' => 'Kontribusi simpanan tidak boleh negatif.',
            'shu_distributions.*.transaction_contribution.required' => 'Kontribusi transaksi wajib diisi.',
            'shu_distributions.*.transaction_contribution.numeric' => 'Kontribusi transaksi harus berupa angka.',
            'shu_distributions.*.transaction_contribution.min' => 'Kontribusi transaksi tidak boleh negatif.',
            'shu_distributions.*.shu_from_savings.required' => 'SHU dari simpanan wajib diisi.',
            'shu_distributions.*.shu_from_savings.numeric' => 'SHU dari simpanan harus berupa angka.',
            'shu_distributions.*.shu_from_savings.min' => 'SHU dari simpanan tidak boleh negatif.',
            'shu_distributions.*.shu_from_transactions.required' => 'SHU dari transaksi wajib diisi.',
            'shu_distributions.*.shu_from_transactions.numeric' => 'SHU dari transaksi harus berupa angka.',
            'shu_distributions.*.shu_from_transactions.min' => 'SHU dari transaksi tidak boleh negatif.',
            'shu_distributions.*.total_shu_received.required' => 'Total SHU yang diterima wajib diisi.',
            'shu_distributions.*.total_shu_received.numeric' => 'Total SHU yang diterima harus berupa angka.',
            'shu_distributions.*.total_shu_received.min' => 'Total SHU yang diterima tidak boleh negatif.',
            'shu_distributions.*.tax_deduction.numeric' => 'Potongan pajak harus berupa angka.',
            'shu_distributions.*.tax_deduction.min' => 'Potongan pajak tidak boleh negatif.',
            'shu_distributions.*.net_shu_received.required' => 'SHU bersih yang diterima wajib diisi.',
            'shu_distributions.*.net_shu_received.numeric' => 'SHU bersih yang diterima harus berupa angka.',
            'shu_distributions.*.net_shu_received.min' => 'SHU bersih yang diterima tidak boleh negatif.',
            'shu_distributions.*.payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'shu_distributions.*.payment_method.in' => 'Metode pembayaran tidak valid.',
            'shu_distributions.*.payment_status.required' => 'Status pembayaran wajib dipilih.',
            'shu_distributions.*.payment_status.in' => 'Status pembayaran tidak valid.',
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
            // Validate member uniqueness
            $this->validateMemberUniqueness($validator);

            // Validate SHU calculation
            $this->validateSHUCalculation($validator);

            // Validate total SHU distribution
            $this->validateTotalSHUDistribution($validator);

            // Validate tax deduction
            $this->validateTaxDeduction($validator);
        });
    }

    /**
     * Validate member uniqueness within the request.
     */
    private function validateMemberUniqueness($validator): void
    {
        $shuDistributions = $this->input('shu_distributions', []);
        $memberIds = array_column($shuDistributions, 'member_id');
        $duplicates = array_diff_assoc($memberIds, array_unique($memberIds));

        if (!empty($duplicates)) {
            $validator->errors()->add(
                'shu_distributions',
                'ID anggota tidak boleh duplikat: ' . implode(', ', array_unique($duplicates))
            );
        }
    }

    /**
     * Validate SHU calculation for each member.
     */
    private function validateSHUCalculation($validator): void
    {
        $shuDistributions = $this->input('shu_distributions', []);

        foreach ($shuDistributions as $index => $distribution) {
            $shuFromSavings = (float) ($distribution['shu_from_savings'] ?? 0);
            $shuFromTransactions = (float) ($distribution['shu_from_transactions'] ?? 0);
            $totalShuReceived = (float) ($distribution['total_shu_received'] ?? 0);
            $taxDeduction = (float) ($distribution['tax_deduction'] ?? 0);
            $netShuReceived = (float) ($distribution['net_shu_received'] ?? 0);

            // Validate total SHU calculation
            $calculatedTotalShu = $shuFromSavings + $shuFromTransactions;
            $difference = abs($totalShuReceived - $calculatedTotalShu);

            if ($difference > 1) {
                $validator->errors()->add(
                    "shu_distributions.{$index}.total_shu_received",
                    "Total SHU tidak sesuai perhitungan. Seharusnya: Rp " . number_format($calculatedTotalShu, 2) .
                        " (SHU dari Simpanan + SHU dari Transaksi)"
                );
            }

            // Validate net SHU calculation
            $calculatedNetShu = $totalShuReceived - $taxDeduction;
            $netDifference = abs($netShuReceived - $calculatedNetShu);

            if ($netDifference > 1) {
                $validator->errors()->add(
                    "shu_distributions.{$index}.net_shu_received",
                    "SHU bersih tidak sesuai perhitungan. Seharusnya: Rp " . number_format($calculatedNetShu, 2) .
                        " (Total SHU - Potongan Pajak)"
                );
            }
        }
    }

    /**
     * Validate total SHU distribution.
     */
    private function validateTotalSHUDistribution($validator): void
    {
        $totalShu = (float) $this->input('total_shu', 0);
        $shuDistributions = $this->input('shu_distributions', []);

        $totalDistributed = 0;
        foreach ($shuDistributions as $distribution) {
            $totalDistributed += (float) ($distribution['total_shu_received'] ?? 0);
        }

        $difference = abs($totalShu - $totalDistributed);

        // Allow small rounding differences (10 rupiah for total)
        if ($difference > 10) {
            $validator->errors()->add(
                'total_shu',
                "Total SHU tidak sesuai dengan jumlah distribusi. Total SHU: Rp " . number_format($totalShu, 2) .
                    ", Total Distribusi: Rp " . number_format($totalDistributed, 2) .
                    ". Selisih: Rp " . number_format($difference, 2)
            );
        }
    }

    /**
     * Validate tax deduction.
     */
    private function validateTaxDeduction($validator): void
    {
        $shuDistributions = $this->input('shu_distributions', []);

        foreach ($shuDistributions as $index => $distribution) {
            $totalShuReceived = (float) ($distribution['total_shu_received'] ?? 0);
            $taxDeduction = (float) ($distribution['tax_deduction'] ?? 0);

            // Tax deduction should not exceed total SHU
            if ($taxDeduction > $totalShuReceived) {
                $validator->errors()->add(
                    "shu_distributions.{$index}.tax_deduction",
                    'Potongan pajak tidak boleh lebih besar dari total SHU yang diterima.'
                );
            }

            // Validate tax rate (assuming maximum 25% tax rate)
            if ($totalShuReceived > 0) {
                $taxRate = ($taxDeduction / $totalShuReceived) * 100;
                if ($taxRate > 25) {
                    $validator->errors()->add(
                        "shu_distributions.{$index}.tax_deduction",
                        'Potongan pajak terlalu tinggi. Maksimal 25% dari total SHU.'
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
        $validated['report_type'] = 'shu_distribution';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
