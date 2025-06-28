<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberReceivablesRequest extends FormRequest
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
                    ->where('report_type', 'member_receivables')
                    ->ignore($this->route('member_receivables'))
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

            // Member Receivables
            'member_receivables' => 'required|array|min:1',
            'member_receivables.*.member_id' => 'required|string|max:50',
            'member_receivables.*.member_name' => 'required|string|max:255',
            'member_receivables.*.loan_type' => [
                'required',
                'string',
                'in:kredit_konsumsi,kredit_produktif,kredit_modal_kerja,kredit_investasi'
            ],
            'member_receivables.*.loan_number' => 'required|string|max:50',
            'member_receivables.*.loan_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_receivables.*.outstanding_balance' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_receivables.*.interest_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100'
            ],
            'member_receivables.*.loan_term_months' => [
                'required',
                'integer',
                'min:1',
                'max:360'
            ],
            'member_receivables.*.disbursement_date' => 'required|date|before_or_equal:today',
            'member_receivables.*.maturity_date' => 'required|date|after:disbursement_date',
            'member_receivables.*.payment_status' => [
                'required',
                'string',
                'in:current,past_due_30,past_due_60,past_due_90,past_due_over_90'
            ],
            'member_receivables.*.collateral_type' => 'nullable|string|max:100',
            'member_receivables.*.collateral_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_receivables.*.note_reference' => 'nullable|string|max:50'
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
            'reporting_year.unique' => 'Laporan piutang anggota untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun pelaporan tidak boleh kurang dari 2020.',
            'reporting_year.max' => 'Tahun pelaporan tidak boleh lebih dari tahun depan.',
            'member_receivables.required' => 'Minimal harus ada satu data piutang anggota.',
            'member_receivables.*.member_id.required' => 'ID anggota wajib diisi.',
            'member_receivables.*.member_name.required' => 'Nama anggota wajib diisi.',
            'member_receivables.*.loan_type.required' => 'Jenis kredit wajib dipilih.',
            'member_receivables.*.loan_type.in' => 'Jenis kredit harus salah satu dari: Kredit Konsumsi, Kredit Produktif, Kredit Modal Kerja, atau Kredit Investasi.',
            'member_receivables.*.loan_number.required' => 'Nomor kredit wajib diisi.',
            'member_receivables.*.loan_amount.required' => 'Jumlah kredit wajib diisi.',
            'member_receivables.*.loan_amount.numeric' => 'Jumlah kredit harus berupa angka.',
            'member_receivables.*.loan_amount.min' => 'Jumlah kredit tidak boleh negatif.',
            'member_receivables.*.outstanding_balance.required' => 'Saldo piutang wajib diisi.',
            'member_receivables.*.outstanding_balance.numeric' => 'Saldo piutang harus berupa angka.',
            'member_receivables.*.outstanding_balance.min' => 'Saldo piutang tidak boleh negatif.',
            'member_receivables.*.interest_rate.required' => 'Suku bunga wajib diisi.',
            'member_receivables.*.interest_rate.numeric' => 'Suku bunga harus berupa angka.',
            'member_receivables.*.interest_rate.min' => 'Suku bunga tidak boleh negatif.',
            'member_receivables.*.interest_rate.max' => 'Suku bunga tidak boleh lebih dari 100%.',
            'member_receivables.*.loan_term_months.required' => 'Jangka waktu kredit wajib diisi.',
            'member_receivables.*.loan_term_months.integer' => 'Jangka waktu kredit harus berupa angka bulat.',
            'member_receivables.*.loan_term_months.min' => 'Jangka waktu kredit minimal 1 bulan.',
            'member_receivables.*.loan_term_months.max' => 'Jangka waktu kredit maksimal 360 bulan.',
            'member_receivables.*.disbursement_date.required' => 'Tanggal pencairan wajib diisi.',
            'member_receivables.*.disbursement_date.date' => 'Tanggal pencairan harus berupa tanggal yang valid.',
            'member_receivables.*.disbursement_date.before_or_equal' => 'Tanggal pencairan tidak boleh di masa depan.',
            'member_receivables.*.maturity_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'member_receivables.*.maturity_date.date' => 'Tanggal jatuh tempo harus berupa tanggal yang valid.',
            'member_receivables.*.maturity_date.after' => 'Tanggal jatuh tempo harus setelah tanggal pencairan.',
            'member_receivables.*.payment_status.required' => 'Status pembayaran wajib dipilih.',
            'member_receivables.*.payment_status.in' => 'Status pembayaran tidak valid.',
            'member_receivables.*.collateral_value.numeric' => 'Nilai jaminan harus berupa angka.',
            'member_receivables.*.collateral_value.min' => 'Nilai jaminan tidak boleh negatif.',
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

            // Validate outstanding balance vs loan amount
            $this->validateOutstandingBalance($validator);

            // Validate collateral requirements
            $this->validateCollateralRequirements($validator);
        });
    }

    /**
     * Validate loan number uniqueness within the request.
     */
    private function validateLoanNumberUniqueness($validator): void
    {
        $memberReceivables = $this->input('member_receivables', []);
        $loanNumbers = array_column($memberReceivables, 'loan_number');
        $duplicates = array_diff_assoc($loanNumbers, array_unique($loanNumbers));

        if (!empty($duplicates)) {
            $validator->errors()->add(
                'member_receivables',
                'Nomor kredit tidak boleh duplikat: ' . implode(', ', array_unique($duplicates))
            );
        }
    }

    /**
     * Validate outstanding balance vs loan amount.
     */
    private function validateOutstandingBalance($validator): void
    {
        $memberReceivables = $this->input('member_receivables', []);

        foreach ($memberReceivables as $index => $receivable) {
            $loanAmount = (float) ($receivable['loan_amount'] ?? 0);
            $outstandingBalance = (float) ($receivable['outstanding_balance'] ?? 0);

            if ($outstandingBalance > $loanAmount) {
                $validator->errors()->add(
                    "member_receivables.{$index}.outstanding_balance",
                    'Saldo piutang tidak boleh lebih besar dari jumlah kredit.'
                );
            }
        }
    }

    /**
     * Validate collateral requirements.
     */
    private function validateCollateralRequirements($validator): void
    {
        $memberReceivables = $this->input('member_receivables', []);

        foreach ($memberReceivables as $index => $receivable) {
            $loanAmount = (float) ($receivable['loan_amount'] ?? 0);
            $loanType = $receivable['loan_type'] ?? '';

            // Require collateral for loans above certain amount or specific types
            if ($loanAmount > 50000000 || in_array($loanType, ['kredit_produktif', 'kredit_investasi'])) {
                if (empty($receivable['collateral_type'])) {
                    $validator->errors()->add(
                        "member_receivables.{$index}.collateral_type",
                        'Jenis jaminan wajib diisi untuk kredit dengan jumlah besar atau kredit produktif/investasi.'
                    );
                }

                if (empty($receivable['collateral_value']) || $receivable['collateral_value'] <= 0) {
                    $validator->errors()->add(
                        "member_receivables.{$index}.collateral_value",
                        'Nilai jaminan wajib diisi untuk kredit dengan jumlah besar atau kredit produktif/investasi.'
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
        $validated['report_type'] = 'member_receivables';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
