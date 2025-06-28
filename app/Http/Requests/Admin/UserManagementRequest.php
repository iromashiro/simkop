<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserManagementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin_dinas');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('user');
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId)
            ],
            'role' => [
                'required',
                'string',
                'in:admin_dinas,admin_koperasi'
            ],
            'is_active' => 'sometimes|boolean',

            // Profile Information
            'phone' => [
                'nullable',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/'
            ],
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'employee_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users')->ignore($userId)
            ],
            'hire_date' => 'nullable|date|before_or_equal:today',

            // Address Information
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postal_code' => [
                'nullable',
                'string',
                'regex:/^[0-9]{5}$/'
            ],

            // Emergency Contact
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => [
                'nullable',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/'
            ],
            'emergency_contact_relationship' => 'nullable|string|max:100',

            // Additional Information
            'notes' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:1024'
        ];

        // Password rules
        if (!$isUpdate) {
            // Creating new user - password required
            $rules['password'] = [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ];
        } else {
            // Updating user - password optional
            $rules['password'] = [
                'nullable',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ];
        }

        // Cooperative assignment for admin_koperasi
        if ($this->input('role') === 'admin_koperasi') {
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
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'role.required' => 'Role wajib dipilih.',
            'role.in' => 'Role tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'employee_id.unique' => 'ID karyawan sudah digunakan.',
            'hire_date.date' => 'Tanggal masuk harus berupa tanggal yang valid.',
            'hire_date.before_or_equal' => 'Tanggal masuk tidak boleh di masa depan.',
            'postal_code.regex' => 'Kode pos harus 5 digit angka.',
            'emergency_contact_phone.regex' => 'Format nomor telepon kontak darurat tidak valid.',
            'avatar.image' => 'Avatar harus berupa gambar.',
            'avatar.mimes' => 'Avatar harus berformat JPEG, PNG, atau JPG.',
            'avatar.max' => 'Ukuran avatar maksimal 1MB.',
            'cooperative_id.required' => 'Koperasi wajib dipilih untuk admin koperasi.',
            'cooperative_id.exists' => 'Koperasi tidak ditemukan.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate role-specific requirements
            $this->validateRoleRequirements($validator);

            // Validate cooperative assignment
            $this->validateCooperativeAssignment($validator);

            // Validate contact information consistency
            $this->validateContactConsistency($validator);
        });
    }

    /**
     * Validate role-specific requirements.
     */
    private function validateRoleRequirements($validator): void
    {
        $role = $this->input('role');
        $position = $this->input('position');
        $department = $this->input('department');

        if ($role === 'admin_dinas') {
            // Admin dinas should have position and department
            if (empty($position)) {
                $validator->errors()->add(
                    'position',
                    'Jabatan wajib diisi untuk admin dinas.'
                );
            }

            if (empty($department)) {
                $validator->errors()->add(
                    'department',
                    'Departemen wajib diisi untuk admin dinas.'
                );
            }
        }
    }

    /**
     * Validate cooperative assignment.
     */
    private function validateCooperativeAssignment($validator): void
    {
        $role = $this->input('role');
        $cooperativeId = $this->input('cooperative_id');
        $userId = $this->route('user');

        if ($role === 'admin_koperasi' && $cooperativeId) {
            // Check if cooperative already has an admin (except current user)
            $existingAdmin = \App\Models\User::where('cooperative_id', $cooperativeId)
                ->where('id', '!=', $userId)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'admin_koperasi');
                })
                ->first();

            if ($existingAdmin) {
                $validator->errors()->add(
                    'cooperative_id',
                    "Koperasi ini sudah memiliki admin: {$existingAdmin->name}."
                );
            }
        }

        if ($role === 'admin_dinas' && $cooperativeId) {
            $validator->errors()->add(
                'cooperative_id',
                'Admin dinas tidak boleh ditetapkan ke koperasi tertentu.'
            );
        }
    }

    /**
     * Validate contact information consistency.
     */
    private function validateContactConsistency($validator): void
    {
        $phone = $this->input('phone');
        $emergencyContactPhone = $this->input('emergency_contact_phone');

        // Phone and emergency contact phone should be different
        if ($phone && $emergencyContactPhone && $phone === $emergencyContactPhone) {
            $validator->errors()->add(
                'emergency_contact_phone',
                'Nomor telepon kontak darurat harus berbeda dengan nomor telepon pribadi.'
            );
        }

        // If emergency contact info is provided, all fields should be filled
        $emergencyName = $this->input('emergency_contact_name');
        $emergencyRelationship = $this->input('emergency_contact_relationship');

        $emergencyFields = [$emergencyName, $emergencyContactPhone, $emergencyRelationship];
        $filledFields = array_filter($emergencyFields);

        if (!empty($filledFields) && count($filledFields) < 3) {
            $validator->errors()->add(
                'emergency_contact_name',
                'Jika mengisi kontak darurat, semua field (nama, telepon, hubungan) harus diisi.'
            );
        }
    }

    /**
     * Get validated data with additional processing.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Remove password if empty (for updates)
        if (isset($validated['password']) && empty($validated['password'])) {
            unset($validated['password']);
        }

        // Set default values
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        // Remove cooperative_id for admin_dinas
        if ($validated['role'] === 'admin_dinas') {
            unset($validated['cooperative_id']);
        }

        return $validated;
    }

    /**
     * Handle file upload for avatar.
     */
    public function handleAvatarUpload(): ?string
    {
        if ($this->hasFile('avatar')) {
            $avatar = $this->file('avatar');
            $filename = time() . '_' . $avatar->getClientOriginalName();
            $path = $avatar->storeAs('users/avatars', $filename, 'public');
            return $path;
        }

        return null;
    }

    /**
     * Get user creation data with role assignment.
     */
    public function getUserCreationData(): array
    {
        $validated = $this->validated();
        $role = $validated['role'];
        unset($validated['role']);

        return [
            'user_data' => $validated,
            'role' => $role
        ];
    }
}
