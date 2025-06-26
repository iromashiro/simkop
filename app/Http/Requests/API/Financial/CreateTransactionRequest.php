<?php
// app/Http/Requests/API/Financial/CreateTransactionRequest.php
namespace App\Http\Requests\API\Financial;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Financial\Models\JournalEntry::class);
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:savings,loan,deposit,withdrawal,shu,fee',
            'amount' => 'required|numeric|min:0.01|max:1000000000',
            'member_id' => 'required|integer|exists:members,id',
            'description' => 'required|string|max:500',
            'reference_number' => 'nullable|string|max:50|unique:journal_entries,reference_number',
            'transaction_date' => 'nullable|date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis transaksi wajib dipilih.',
            'type.in' => 'Jenis transaksi tidak valid.',
            'amount.required' => 'Jumlah transaksi wajib diisi.',
            'amount.min' => 'Jumlah transaksi minimal Rp 0,01.',
            'amount.max' => 'Jumlah transaksi maksimal Rp 1.000.000.000.',
            'member_id.exists' => 'Anggota tidak ditemukan.',
            'description.required' => 'Deskripsi transaksi wajib diisi.',
            'reference_number.unique' => 'Nomor referensi sudah digunakan.',
            'transaction_date.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
        ];
    }
}
