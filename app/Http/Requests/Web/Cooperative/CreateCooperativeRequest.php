<?php
// CreateCooperativeRequest.php
namespace App\Http\Requests\Web\Cooperative;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'code' => ['required', 'string', 'min:2', 'max:10', 'unique:cooperatives,code', 'alpha_num'],
            'registration_number' => ['required', 'string', 'max:50', 'unique:cooperatives,registration_number'],
            'address' => ['required', 'string', 'min:10', 'max:500'],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255', 'unique:cooperatives,email'],
            'website' => ['nullable', 'url', 'max:255'],
            'established_date' => ['required', 'date', 'before_or_equal:today'],
            'legal_entity_type' => ['required', 'string', Rule::in(['primer', 'sekunder', 'tersier'])],
            'business_type' => ['required', 'string', 'max:100'],
            'chairman_name' => ['required', 'string', 'min:2', 'max:255'],
            'secretary_name' => ['required', 'string', 'min:2', 'max:255'],
            'treasurer_name' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}

// UpdateCooperativeRequest.php
namespace App\Http\Requests\Web\Cooperative;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cooperative = $this->route('cooperative');
        return $this->user()->can('update', $cooperative);
    }

    public function rules(): array
    {
        $cooperative = $this->route('cooperative');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'address' => ['sometimes', 'required', 'string', 'min:10', 'max:500'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:255', "unique:cooperatives,email,{$cooperative->id}"],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'chairman_name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'secretary_name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'treasurer_name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
        ];
    }
}
