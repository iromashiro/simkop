<?php
// app/Http/Requests/API/Financial/CreateJournalEntryRequest.php
namespace App\Http\Requests\API\Financial;

use Illuminate\Foundation\Http\FormRequest;

class CreateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Financial\Models\JournalEntry::class);
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:500',
            'reference_number' => 'nullable|string|max:50|unique:journal_entries,reference_number',
            'transaction_date' => 'nullable|date|before_or_equal:today',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:accounts,id',
            'lines.*.debit_amount' => 'nullable|numeric|min:0',
            'lines.*.credit_amount' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Deskripsi jurnal wajib diisi.',
            'lines.required' => 'Baris jurnal wajib diisi.',
            'lines.min' => 'Minimal 2 baris jurnal diperlukan.',
            'lines.*.account_id.exists' => 'Akun tidak ditemukan.',
            'lines.*.debit_amount.min' => 'Jumlah debit tidak boleh negatif.',
            'lines.*.credit_amount.min' => 'Jumlah kredit tidak boleh negatif.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $lines = $this->input('lines', []);

            foreach ($lines as $index => $line) {
                $debit = $line['debit_amount'] ?? 0;
                $credit = $line['credit_amount'] ?? 0;

                if ($debit == 0 && $credit == 0) {
                    $validator->errors()->add("lines.{$index}", 'Salah satu dari debit atau kredit harus diisi.');
                }

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("lines.{$index}", 'Tidak boleh mengisi debit dan kredit bersamaan.');
                }
            }

            $totalDebits = collect($lines)->sum('debit_amount');
            $totalCredits = collect($lines)->sum('credit_amount');

            if (abs($totalDebits - $totalCredits) > 0.01) {
                $validator->errors()->add('lines', 'Total debit harus sama dengan total kredit.');
            }
        });
    }
}
