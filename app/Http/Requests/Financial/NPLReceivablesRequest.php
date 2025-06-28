<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NPLReceivablesRequest extends FormRequest
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
                    ->where('report_type', 'npl_receivables')
                    ->ignore($this->route('npl_receivables'))
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

            // NPL Receivables
            'npl_receivables' => 'required|array|min:1',
            'npl_receivables.*.member_id' => 'required|string|max:50',
            'npl_receivables.*.member_name' => 'required|string|max:255',
            'npl_receivables.*.loan_number' => 'required|string|max:50',
            'npl_receivables.*.original_loan_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'npl_receivables.*.outstanding_balance' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'npl_receivables.*.days_past_due' => [
                'required',
                'integer',
                'min:91' // NPL starts from 91 days
            ],
            'npl_receivables.*.npl_classification' => [
                'required',
                'string',
                'in:kurang_lancar,diragukan,macet'
            ],
            'npl_receivables.*.provision_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100'
            ],
            'npl_receivables.*.provision_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'npl_receivables.*.collateral_type' => 'nullable|string|max:100',
            'npl_receivables.*.collateral_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'npl_receivables.*.recovery_efforts' => 'nullable|string|max:1000',
            'npl_receivables.*.last_payment_date' => 'nullable|date|before_or_equal:today',
            'npl_receivables.*.restructuring_status' => [
                'nullable',
                'string',
                'in:none,rescheduling,reconditioning,restructuring'
            ],
            'npl_receivables.*.write_off_status' => [
                'nullable',
                'string',
                'in:none,partial,full'
            ],
            'npl_receivables.*.note_reference' => 'nullable|string|max:50'
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
            'reporting_year.unique' => 'Laporan piutang NPL untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun pelaporan tidak boleh kurang dari 2020.',
            'reporting_year.max' => 'Tahun pelaporan tidak boleh lebih dari tahun depan.',
            'npl_receivables.required' => 'Minimal harus ada satu data piutang NPL.',
            'npl_receivables.*.member_id.required' => 'ID anggota wajib diisi.',
            'npl_receivables.*.member_name.required' => 'Nama anggota wajib diisi.',
            'npl_receivables.*.loan_number.required' => 'Nomor kredit wajib diisi.',
            'npl_receivables.*.original_loan_amount.required' => 'Jumlah kredit awal wajib diisi.',
            'npl_receivables.*.original_loan_amount.numeric' => 'Jumlah kredit awal harus berupa angka.',
            'npl_receivables.*.original_loan_amount.min' => 'Jumlah kredit awal tidak boleh negatif.',
            'npl_receivables.*.outstanding_balance.required' => 'Saldo piutang wajib diisi.',
            'npl_receivables.*.outstanding_balance.numeric' => 'Saldo piutang harus berupa angka.',
            'npl_receivables.*.outstanding_balance.min' => 'Saldo piutang tidak boleh negatif.',
            'npl_receivables.*.days_past_due.required' => 'Hari tunggakan wajib diisi.',
            'npl_receivables.*.days_past_due.integer' => 'Hari tunggakan harus berupa angka bulat.',
            'npl_receivables.*.days_past_due.min' => 'NPL dimulai dari 91 hari tunggakan.',
            'npl_receivables.*.npl_classification.required' => 'Klasifikasi NPL wajib dipilih.',
            'npl_receivables.*.npl_classification.in' => 'Klasifikasi NPL harus salah satu dari: Kurang Lancar, Diragukan, atau Macet.',
            'npl_receivables.*.provision_percentage.required' => 'Persentase penyisihan wajib diisi.',
            'npl_receivables.*.provision_percentage.numeric' => 'Persentase penyisihan harus berupa angka.',
            'npl_receivables.*.provision_percentage.min' => 'Persentase penyisihan tidak boleh negatif.',
            'npl_receivables.*.provision_percentage.max' => 'Persentase penyisihan tidak boleh lebih dari 100%.',
            'npl_receivables.*.provision_amount.required' => 'Jumlah penyisihan wajib diisi.',
            'npl_receivables.*.provision_amount.numeric' => 'Jumlah penyisihan harus berupa angka.',
            'npl_receivables.*.provision_amount.min' => 'Jumlah penyisihan tidak boleh negatif.',
            'npl_receivables.*.collateral_value.numeric' => 'Nilai jaminan harus berupa angka.',
            'npl_receivables.*.collateral_value.min' => 'Nilai jaminan tidak boleh negatif.',
            'npl_receivables.*.last_payment_date.date' => 'Tanggal pembayaran terakhir harus berupa tanggal yang valid.',
            'npl_receivables.*.last_payment_date.before_or_equal' => 'Tanggal pembayaran terakhir tidak boleh di masa depan.',
            'npl_receivables.*.restructuring_status.in' => 'Status restrukturisasi tidak valid.',
            'npl_receivables.*.write_off_status.in' => 'Status penghapusbukuan tidak valid.',
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
            // Validate loan number uniqueness
            $this->validateLoanNumberUniqueness($validator);

            // Validate NPL classification vs days past due
            $this->validateNPLClassification($validator);

            // Validate provision calculation
            $this->validateProvisionCalculation($validator);

            // Validate outstanding balance vs original loan amount
            $this->validateOutstandingBalance($validator);
        });
    }

    /**
     * Validate loan number uniqueness within the request.
     */
    private function validateLoanNumberUniqueness($validator): void
    {
        $nplReceivables = $this->input('npl_receivables', []);
        $loanNumbers = array_column($nplReceivables, 'loan_number');
        $duplicates = array_diff_assoc($loanNumbers, array_unique($loanNumbers));

        if (!empty($duplicates)) {
            $validator->errors()->add(
                'npl_receivables',
                'Nomor kredit tidak boleh duplikat: ' . implode(', ', array_unique($duplicates))
            );
        }
    }

    /**
     * Validate NPL classification vs days past due.
     */
    private function validateNPLClassification($validator): void
    {
        $nplReceivables = $this->input('npl_receivables', []);

        foreach ($nplReceivables as $index => $npl) {
            $daysPastDue = (int) ($npl['days_past_due'] ?? 0);
            $classification = $npl['npl_classification'] ?? '';

            // Validate classification based on days past due
            if ($daysPastDue >= 91 && $daysPastDue <= 120 && $classification !== 'kurang_lancar') {
                $validator->errors()->add(
                    "npl_receivables.{$index}.npl_classification",
                    'Untuk tunggakan 91-120 hari, klasifikasi harus "Kurang Lancar".'
                );
            } elseif ($daysPastDue >= 121 && $daysPastDue <= 180 && $classification !== 'diragukan') {
                $validator->errors()->add(
                    "npl_receivables.{$index}.npl_classification",
                    'Untuk tunggakan 121-180 hari, klasifikasi harus "Diragukan".'
                );
            } elseif ($daysPastDue > 180 && $classification !== 'macet') {
                $validator->errors()->add(
                    "npl_receivables.{$index}.npl_classification",
                    'Untuk tunggakan lebih dari 180 hari, klasifikasi harus "Macet".'
                );
            }
        }
    }

    /**
     * Validate provision calculation.
     */
    private function validateProvisionCalculation($validator): void
    {
        $nplReceivables = $this->input('npl_receivables', []);

        foreach ($nplReceivables as $index => $npl) {
            $outstandingBalance = (float) ($npl['outstanding_balance'] ?? 0);
            $provisionPercentage = (float) ($npl['provision_percentage'] ?? 0);
            $provisionAmount = (float) ($npl['provision_amount'] ?? 0);
            $classification = $npl['npl_classification'] ?? '';

            // Validate minimum provision percentage based on classification
            $minProvisionPercentage = match ($classification) {
                'kurang_lancar' => 10,
                'diragukan' => 50,
                'macet' => 100,
                default => 0
            };

            if ($provisionPercentage < $minProvisionPercentage) {
                $validator->errors()->add(
                    "npl_receivables.{$index}.provision_percentage",
                    "Persentase penyisihan minimal untuk klasifikasi {$classification} adalah {$minProvisionPercentage}%."
                );
            }

            // Validate provision amount calculation
            $calculatedProvisionAmount = $outstandingBalance * ($provisionPercentage / 100);
            $difference = abs($provisionAmount - $calculatedProvisionAmount);

            // Allow small rounding differences (1 rupiah)
            if ($difference > 1) {
                $validator->errors()->add(
                    "npl_receivables.{$index}.provision_amount",
                    "Jumlah penyisihan tidak sesuai perhitungan. Seharusnya: Rp " . number_format($calculatedProvisionAmount, 2) .
                        " ({$provisionPercentage}% dari saldo piutang)"
                );
            }
        }
    }

    /**
     * Validate outstanding balance vs original loan amount.
     */
    private function validateOutstandingBalance($validator): void
    {
        $nplReceivables = $this->input('npl_receivables', []);

        foreach ($nplReceivables as $index => $npl) {
            $originalLoanAmount = (float) ($npl['original_loan_amount'] ?? 0);
            $outstandingBalance = (float) ($npl['outstanding_balance'] ?? 0);

            if ($outstandingBalance > $originalLoanAmount) {
                $validator->errors()->add(
                    "npl_receivables.{$index}.outstanding_balance",
                    'Saldo piutang tidak boleh lebih besar dari jumlah kredit awal.'
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
        $validated['report_type'] = 'npl_receivables';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
