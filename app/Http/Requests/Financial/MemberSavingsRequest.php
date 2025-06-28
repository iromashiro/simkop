<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberSavingsRequest extends FormRequest
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
                    ->where('report_type', 'member_savings')
                    ->ignore($this->route('member_savings'))
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

            // Member Savings
            'member_savings' => 'required|array|min:1',
            'member_savings.*.member_id' => 'required|string|max:50',
            'member_savings.*.member_name' => 'required|string|max:255',
            'member_savings.*.savings_type' => [
                'required',
                'string',
                'in:simpanan_pokok,simpanan_wajib,simpanan_sukarela'
            ],
            'member_savings.*.beginning_balance' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_savings.*.deposits' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_savings.*.withdrawals' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_savings.*.ending_balance' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_savings.*.interest_earned' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'member_savings.*.note_reference' => 'nullable|string|max:50'
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
            'reporting_year.unique' => 'Laporan simpanan anggota untuk tahun ini sudah ada.',
            'reporting_year.min' => 'Tahun pelaporan tidak boleh kurang dari 2020.',
            'reporting_year.max' => 'Tahun pelaporan tidak boleh lebih dari tahun depan.',
            'member_savings.required' => 'Minimal harus ada satu data simpanan anggota.',
            'member_savings.*.member_id.required' => 'ID anggota wajib diisi.',
            'member_savings.*.member_name.required' => 'Nama anggota wajib diisi.',
            'member_savings.*.savings_type.required' => 'Jenis simpanan wajib dipilih.',
            'member_savings.*.savings_type.in' => 'Jenis simpanan harus salah satu dari: Simpanan Pokok, Simpanan Wajib, atau Simpanan Sukarela.',
            'member_savings.*.beginning_balance.required' => 'Saldo awal wajib diisi.',
            'member_savings.*.beginning_balance.numeric' => 'Saldo awal harus berupa angka.',
            'member_savings.*.beginning_balance.min' => 'Saldo awal tidak boleh negatif.',
            'member_savings.*.deposits.numeric' => 'Setoran harus berupa angka.',
            'member_savings.*.deposits.min' => 'Setoran tidak boleh negatif.',
            'member_savings.*.withdrawals.numeric' => 'Penarikan harus berupa angka.',
            'member_savings.*.withdrawals.min' => 'Penarikan tidak boleh negatif.',
            'member_savings.*.ending_balance.required' => 'Saldo akhir wajib diisi.',
            'member_savings.*.ending_balance.numeric' => 'Saldo akhir harus berupa angka.',
            'member_savings.*.ending_balance.min' => 'Saldo akhir tidak boleh negatif.',
            'member_savings.*.interest_earned.numeric' => 'Bunga yang diperoleh harus berupa angka.',
            'member_savings.*.interest_earned.min' => 'Bunga yang diperoleh tidak boleh negatif.',
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
            // Validate member savings calculation
            $this->validateMemberSavingsCalculation($validator);

            // Validate member uniqueness per savings type
            $this->validateMemberUniqueness($validator);
        });
    }

    /**
     * Validate member savings calculation.
     */
    private function validateMemberSavingsCalculation($validator): void
    {
        $memberSavings = $this->input('member_savings', []);

        foreach ($memberSavings as $index => $saving) {
            $beginningBalance = (float) ($saving['beginning_balance'] ?? 0);
            $deposits = (float) ($saving['deposits'] ?? 0);
            $withdrawals = (float) ($saving['withdrawals'] ?? 0);
            $interestEarned = (float) ($saving['interest_earned'] ?? 0);
            $endingBalance = (float) ($saving['ending_balance'] ?? 0);

            $calculatedEndingBalance = $beginningBalance + $deposits - $withdrawals + $interestEarned;
            $difference = abs($endingBalance - $calculatedEndingBalance);

            // Allow small rounding differences (1 rupiah)
            if ($difference > 1) {
                $validator->errors()->add(
                    "member_savings.{$index}.ending_balance",
                    "Saldo akhir tidak sesuai perhitungan. Seharusnya: Rp " . number_format($calculatedEndingBalance, 2) .
                        " (Saldo Awal + Setoran - Penarikan + Bunga)"
                );
            }
        }
    }

    /**
     * Validate member uniqueness per savings type.
     */
    private function validateMemberUniqueness($validator): void
    {
        $memberSavings = $this->input('member_savings', []);
        $memberSavingsKeys = [];

        foreach ($memberSavings as $index => $saving) {
            $key = $saving['member_id'] . '|' . $saving['savings_type'];

            if (in_array($key, $memberSavingsKeys)) {
                $validator->errors()->add(
                    "member_savings.{$index}",
                    "Anggota {$saving['member_name']} sudah memiliki data untuk jenis simpanan {$saving['savings_type']}."
                );
            }

            $memberSavingsKeys[] = $key;
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
        $validated['report_type'] = 'member_savings';

        // Set default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }
}
