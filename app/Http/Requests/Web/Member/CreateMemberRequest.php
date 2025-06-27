<?php

namespace App\Http\Requests\Web\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create Member Form Request
 *
 * Validates member creation data with comprehensive business rules
 * Ensures data integrity and security for member registration
 *
 * @package App\Http\Requests\Web\Member
 * @author Mateen (Senior Software Engineer)
 */
class CreateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Member\Models\Member::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cooperative_id' => [
                'required',
                'integer',
                'exists:cooperatives,id',
                function ($attribute, $value, $fail) {
                    if (!$this->user()->hasCooperativeAccess($value)) {
                        $fail('You do not have access to this cooperative.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\.\']+$/', // Only letters, spaces, dots, apostrophes
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                Rule::unique('members', 'email')->where(function ($query) {
                    return $query->where('cooperative_id', $this->cooperative_id);
                }),
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/', // Indonesian phone format
                Rule::unique('members', 'phone')->where(function ($query) {
                    return $query->where('cooperative_id', $this->cooperative_id);
                }),
            ],
            'address' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
            'id_number' => [
                'required',
                'string',
                'min:10',
                'max:20',
                Rule::unique('members', 'id_number')->where(function ($query) {
                    return $query->where('cooperative_id', $this->cooperative_id);
                }),
            ],
            'id_type' => [
                'required',
                'string',
                Rule::in(['KTP', 'SIM', 'PASSPORT']),
            ],
            'date_of_birth' => [
                'required',
                'date',
                'before:' . now()->subYears(17)->format('Y-m-d'), // Minimum 17 years old
                'after:' . now()->subYears(100)->format('Y-m-d'), // Maximum 100 years old
            ],
            'gender' => [
                'required',
                'string',
                Rule::in(['male', 'female']),
            ],
            'occupation' => [
                'nullable',
                'string',
                'max:100',
            ],
            'monthly_income' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99', // 12 digits + 2 decimals
            ],
            'emergency_contact_name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\.\']+$/',
            ],
            'emergency_contact_phone' => [
                'required',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/',
            ],
            'join_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
                'after:' . now()->subYears(50)->format('Y-m-d'),
            ],
            'create_user_account' => [
                'boolean',
            ],
            'password' => [
                'required_if:create_user_account,true',
                'nullable',
                'string',
                'min:8',
                'max:255',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', // Strong password
            ],
            'password_confirmation' => [
                'required_if:create_user_account,true',
                'same:password',
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
            'date_of_birth.before' => 'Member must be at least 17 years old.',
            'date_of_birth.after' => 'Invalid date of birth.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'id_number.unique' => 'This ID number is already registered in the cooperative.',
            'email.unique' => 'This email is already registered in the cooperative.',
            'phone.unique' => 'This phone number is already registered in the cooperative.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'cooperative_id' => 'cooperative',
            'id_number' => 'ID number',
            'id_type' => 'ID type',
            'date_of_birth' => 'date of birth',
            'monthly_income' => 'monthly income',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_phone' => 'emergency contact phone',
            'join_date' => 'join date',
            'create_user_account' => 'create user account',
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

        // Set default join date
        if (!$this->has('join_date') || !$this->join_date) {
            $this->merge([
                'join_date' => now()->format('Y-m-d'),
            ]);
        }
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert to Indonesian format
        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            return '62' . $phone;
        }

        return $phone;
    }

    /**
     * Handle a failed validation attempt
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->expectsJson()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }
}
