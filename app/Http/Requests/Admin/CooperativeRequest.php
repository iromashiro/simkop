<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CooperativeRequest extends FormRequest
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
        $cooperativeId = $this->route('cooperative');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cooperatives')->ignore($cooperativeId)
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('cooperatives')->ignore($cooperativeId)
            ],
            'type' => [
                'required',
                'string',
                'in:simpan_pinjam,konsumsi,produksi,jasa,serba_usaha'
            ],
            'legal_entity_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cooperatives')->ignore($cooperativeId)
            ],
            'establishment_date' => [
                'required',
                'date',
                'before_or_equal:today'
            ],
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => [
                'required',
                'string',
                'regex:/^[0-9]{5}$/'
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/'
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('cooperatives')->ignore($cooperativeId)
            ],
            'website' => 'nullable|url|max:255',

            // Management Information
            'chairman_name' => 'required|string|max:255',
            'chairman_phone' => [
                'required',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/'
            ],
            'chairman_email' => 'required|email|max:255',
            'manager_name' => 'required|string|max:255',
            'manager_phone' => [
                'required',
                'string',
                'regex:/^(\+62|62|0)[0-9]{8,13}$/'
            ],
            'manager_email' => 'required|email|max:255',

            // Financial Information
            'initial_capital' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'current_assets' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'total_members' => [
                'required',
                'integer',
                'min:20' // Minimum members for cooperative
            ],
            'active_members' => [
                'required',
                'integer',
                'min:1',
                'lte:total_members'
            ],

            // Business Information
            'business_activities' => 'required|array|min:1',
            'business_activities.*' => 'string|max:255',
            'service_area' => 'required|string|max:255',
            'operational_status' => [
                'required',
                'string',
                'in:active,inactive,suspended,dissolved'
            ],

            // Compliance Information
            'license_number' => 'nullable|string|max:50',
            'license_expiry_date' => 'nullable|date|after:today',
            'tax_number' => [
                'nullable',
                'string',
                'regex:/^[0-9]{2}\.[0-9]{3}\.[0-9]{3}\.[0-9]\-[0-9]{3}\.[0-9]{3}$/'
            ],
            'bank_account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',

            // Additional Information
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_active' => 'sometimes|boolean'
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama koperasi wajib diisi.',
            'name.unique' => 'Nama koperasi sudah digunakan.',
            'code.required' => 'Kode koperasi wajib diisi.',
            'code.unique' => 'Kode koperasi sudah digunakan.',
            'code.regex' => 'Kode koperasi hanya boleh mengandung huruf besar, angka, dan tanda hubung.',
            'type.required' => 'Jenis koperasi wajib dipilih.',
            'type.in' => 'Jenis koperasi tidak valid.',
            'legal_entity_number.required' => 'Nomor badan hukum wajib diisi.',
            'legal_entity_number.unique' => 'Nomor badan hukum sudah digunakan.',
            'establishment_date.required' => 'Tanggal pendirian wajib diisi.',
            'establishment_date.date' => 'Tanggal pendirian harus berupa tanggal yang valid.',
            'establishment_date.before_or_equal' => 'Tanggal pendirian tidak boleh di masa depan.',
            'address.required' => 'Alamat wajib diisi.',
            'city.required' => 'Kota wajib diisi.',
            'postal_code.required' => 'Kode pos wajib diisi.',
            'postal_code.regex' => 'Kode pos harus 5 digit angka.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'website.url' => 'Format website tidak valid.',
            'chairman_name.required' => 'Nama ketua wajib diisi.',
            'chairman_phone.required' => 'Nomor telepon ketua wajib diisi.',
            'chairman_phone.regex' => 'Format nomor telepon ketua tidak valid.',
            'chairman_email.required' => 'Email ketua wajib diisi.',
            'chairman_email.email' => 'Format email ketua tidak valid.',
            'manager_name.required' => 'Nama manajer wajib diisi.',
            'manager_phone.required' => 'Nomor telepon manajer wajib diisi.',
            'manager_phone.regex' => 'Format nomor telepon manajer tidak valid.',
            'manager_email.required' => 'Email manajer wajib diisi.',
            'manager_email.email' => 'Format email manajer tidak valid.',
            'initial_capital.required' => 'Modal awal wajib diisi.',
            'initial_capital.numeric' => 'Modal awal harus berupa angka.',
            'initial_capital.min' => 'Modal awal tidak boleh negatif.',
            'current_assets.numeric' => 'Aset saat ini harus berupa angka.',
            'current_assets.min' => 'Aset saat ini tidak boleh negatif.',
            'total_members.required' => 'Jumlah anggota wajib diisi.',
            'total_members.integer' => 'Jumlah anggota harus berupa angka bulat.',
            'total_members.min' => 'Koperasi minimal harus memiliki 20 anggota.',
            'active_members.required' => 'Jumlah anggota aktif wajib diisi.',
            'active_members.integer' => 'Jumlah anggota aktif harus berupa angka bulat.',
            'active_members.min' => 'Minimal harus ada 1 anggota aktif.',
            'active_members.lte' => 'Jumlah anggota aktif tidak boleh lebih dari total anggota.',
            'business_activities.required' => 'Kegiatan usaha wajib diisi.',
            'business_activities.min' => 'Minimal harus ada satu kegiatan usaha.',
            'service_area.required' => 'Wilayah layanan wajib diisi.',
            'operational_status.required' => 'Status operasional wajib dipilih.',
            'operational_status.in' => 'Status operasional tidak valid.',
            'license_expiry_date.date' => 'Tanggal kadaluarsa izin harus berupa tanggal yang valid.',
            'license_expiry_date.after' => 'Tanggal kadaluarsa izin harus di masa depan.',
            'tax_number.regex' => 'Format NPWP tidak valid (contoh: 12.345.678.9-123.456).',
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.mimes' => 'Logo harus berformat JPEG, PNG, atau JPG.',
            'logo.max' => 'Ukuran logo maksimal 2MB.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate business activities
            $this->validateBusinessActivities($validator);

            // Validate management contact uniqueness
            $this->validateManagementContacts($validator);

            // Validate financial consistency
            $this->validateFinancialConsistency($validator);
        });
    }

    /**
     * Validate business activities.
     */
    private function validateBusinessActivities($validator): void
    {
        $businessActivities = $this->input('business_activities', []);
        $type = $this->input('type');

        // Validate activities based on cooperative type
        $requiredActivities = match ($type) {
            'simpan_pinjam' => ['simpanan', 'pinjaman'],
            'konsumsi' => ['penjualan_barang_konsumsi'],
            'produksi' => ['produksi', 'pengolahan'],
            'jasa' => ['layanan_jasa'],
            'serba_usaha' => [], // Can have any activities
            default => []
        };

        foreach ($requiredActivities as $required) {
            $found = false;
            foreach ($businessActivities as $activity) {
                if (stripos($activity, $required) !== false) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $validator->errors()->add(
                    'business_activities',
                    "Koperasi jenis {$type} harus memiliki kegiatan usaha yang berkaitan dengan {$required}."
                );
            }
        }
    }

    /**
     * Validate management contact uniqueness.
     */
    private function validateManagementContacts($validator): void
    {
        $chairmanEmail = $this->input('chairman_email');
        $managerEmail = $this->input('manager_email');
        $chairmanPhone = $this->input('chairman_phone');
        $managerPhone = $this->input('manager_phone');

        // Chairman and manager should have different contacts
        if ($chairmanEmail === $managerEmail) {
            $validator->errors()->add(
                'manager_email',
                'Email manajer harus berbeda dengan email ketua.'
            );
        }

        if ($chairmanPhone === $managerPhone) {
            $validator->errors()->add(
                'manager_phone',
                'Nomor telepon manajer harus berbeda dengan nomor telepon ketua.'
            );
        }
    }

    /**
     * Validate financial consistency.
     */
    private function validateFinancialConsistency($validator): void
    {
        $initialCapital = (float) $this->input('initial_capital', 0);
        $currentAssets = (float) $this->input('current_assets', 0);
        $totalMembers = (int) $this->input('total_members', 0);

        // Current assets should not be less than initial capital (unless there are losses)
        if ($currentAssets > 0 && $currentAssets < ($initialCapital * 0.5)) {
            $validator->errors()->add(
                'current_assets',
                'Aset saat ini tampak terlalu rendah dibandingkan modal awal. Mohon periksa kembali.'
            );
        }

        // Validate minimum capital per member
        if ($totalMembers > 0) {
            $capitalPerMember = $initialCapital / $totalMembers;
            if ($capitalPerMember < 100000) { // Minimum 100k per member
                $validator->errors()->add(
                    'initial_capital',
                    'Modal awal terlalu rendah. Minimal Rp 100.000 per anggota.'
                );
            }
        }
    }

    /**
     * Get validated data with additional processing.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Convert business activities array to JSON
        if (isset($validated['business_activities'])) {
            $validated['business_activities'] = json_encode($validated['business_activities']);
        }

        // Set default values
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        if (!isset($validated['operational_status'])) {
            $validated['operational_status'] = 'active';
        }

        return $validated;
    }

    /**
     * Handle file upload for logo.
     */
    public function handleLogoUpload(): ?string
    {
        if ($this->hasFile('logo')) {
            $logo = $this->file('logo');
            $filename = time() . '_' . $logo->getClientOriginalName();
            $path = $logo->storeAs('cooperatives/logos', $filename, 'public');
            return $path;
        }

        return null;
    }
}
