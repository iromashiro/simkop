<?php
// app/Http/Requests/API/Member/StoreMemberRequest.php
namespace App\Http\Requests\API\Member;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Member\Models\Member::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:members,email',
            'phone' => 'required|string|max:20|regex:/^[0-9\+\-\(\)\s]+$/',
            'address' => 'required|string|max:500',
            'id_number' => 'required|string|max:20|unique:members,id_number',
            'birth_date' => 'required|date|before:today',
            'gender' => 'required|string|in:male,female',
            'occupation' => 'nullable|string|max:100',
            'membership_type' => 'required|string|in:regular,premium,honorary',
            'initial_deposit' => 'required|numeric|min:0|max:1000000000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggota wajib diisi.',
            'email.unique' => 'Email sudah digunakan.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'id_number.unique' => 'Nomor identitas sudah digunakan.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'membership_type.in' => 'Jenis keanggotaan tidak valid.',
            'initial_deposit.min' => 'Setoran awal minimal Rp 0.',
            'initial_deposit.max' => 'Setoran awal maksimal Rp 1.000.000.000.',
        ];
    }
}
