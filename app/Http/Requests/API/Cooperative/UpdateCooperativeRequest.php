<?php
// app/Http/Requests/API/Cooperative/UpdateCooperativeRequest.php
namespace App\Http\Requests\API\Cooperative;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cooperative = $this->route('cooperative');
        return $this->user()->can('update', $cooperative);
    }

    public function rules(): array
    {
        $cooperativeId = $this->route('cooperative')->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('cooperatives', 'name')->ignore($cooperativeId)
            ],
            'type' => 'sometimes|string|in:simpan_pinjam,konsumen,produksi,jasa',
            'address' => 'sometimes|string|max:500',
            'phone' => 'sometimes|string|max:20|regex:/^[0-9\+\-\(\)\s]+$/',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('cooperatives', 'email')->ignore($cooperativeId)
            ],
            'registration_number' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('cooperatives', 'registration_number')->ignore($cooperativeId)
            ],
            'establishment_date' => 'sometimes|date|before_or_equal:today',
            'description' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}
