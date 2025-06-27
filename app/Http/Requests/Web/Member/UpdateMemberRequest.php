<?php

namespace App\Http\Requests\Web\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Member Form Request
 *
 * Validates member update data with business rules
 * Only allows updating specific fields for security
 *
 * @package App\Http\Requests\Web\Member
 * @author Mateen (Senior Software Engineer)
 */
class UpdateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $member = $this->route('member');
        return $this->user()->can('update', $member);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\.\']+$/',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email:rfc,dns',
                'max:255',
                Rule::unique('members', 'email')
                    ->where('cooperative_id', $member->cooperative_id)
                    ->ignore($member->id),
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/',
                Rule::unique('members', 'phone')
                    ->where('cooperative_id', $member->cooperative_id)
                    ->ignore($member->id),
            ],
            'address' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:500',
            ],
            'occupation' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'monthly_income' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
            'emergency_contact_name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\.\']+$/',
            ],
            'emergency_contact_phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/',
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Name can only contain letters, spaces, dots, and apostrophes.',
            'phone.regex' => 'Phone number must be a valid Indonesian number.',
            'emergency_contact_phone.regex' => 'Emergency contact phone must be a valid Indonesian number.',
            'email.unique' => 'This email is already registered in the cooperative.',
            'phone.unique' => 'This phone number is already registered in the cooperative.',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Normalize phone numbers
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhone($this->phone),
            ]);
        }

        if ($this->has('emergency_contact_phone')) {
            $this->merge([
                'emergency_contact_phone' => $this->normalizePhone($this->emergency_contact_phone),
            ]);
        }

        // Normalize email
        if ($this->has('email') && $this->email) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            return '62' . $phone;
        }

        return $phone;
    }
}
