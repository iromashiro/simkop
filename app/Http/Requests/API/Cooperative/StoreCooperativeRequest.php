<?php
// app/Http/Requests/API/Cooperative/StoreCooperativeRequest.php
namespace App\Http\Requests\API\Cooperative;

use Illuminate\Foundation\Http\FormRequest;

class StoreCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:cooperatives,name',
            'type' => 'required|string|in:simpan_pinjam,konsumen,produksi,jasa',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:20|regex:/^[0-9\+\-\(\)\s]+$/',
            'email' => 'required|email|max:255|unique:cooperatives,email',
            'registration_number' => 'required|string|max:50|unique:cooperatives,registration_number',
            'establishment_date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama koperasi wajib diisi.',
            'name.unique' => 'Nama koperasi sudah digunakan.',
            'type.required' => 'Jenis koperasi wajib dipilih.',
            'type.in' => 'Jenis koperasi tidak valid.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'registration_number.unique' => 'Nomor registrasi sudah digunakan.',
            'establishment_date.before_or_equal' => 'Tanggal pendirian tidak boleh di masa depan.',
            'logo.image' => 'File logo harus berupa gambar.',
            'logo.max' => 'Ukuran file logo maksimal 2MB.',
        ];
    }
}
