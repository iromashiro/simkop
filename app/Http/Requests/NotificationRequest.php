<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['admin_koperasi', 'admin_dinas']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => [
                'required',
                'string',
                'in:info,success,warning,error,report_submitted,report_approved,report_rejected,system_maintenance'
            ],
            'priority' => [
                'required',
                'string',
                'in:low,normal,high,urgent'
            ],
            'recipient_type' => [
                'required',
                'string',
                'in:specific_user,role_based,cooperative_based,broadcast'
            ],
            'scheduled_at' => 'nullable|date|after:now',
            'expires_at' => 'nullable|date|after:scheduled_at',
            'action_url' => 'nullable|url|max:500',
            'action_text' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
            'is_persistent' => 'sometimes|boolean',
            'requires_acknowledgment' => 'sometimes|boolean'
        ];

        // Recipient-specific validation
        switch ($this->input('recipient_type')) {
            case 'specific_user':
                $rules['recipient_user_ids'] = 'required|array|min:1';
                $rules['recipient_user_ids.*'] = 'exists:users,id';
                break;

            case 'role_based':
                $rules['recipient_roles'] = 'required|array|min:1';
                $rules['recipient_roles.*'] = 'string|in:admin_dinas,admin_koperasi';
                break;

            case 'cooperative_based':
                $rules['recipient_cooperative_ids'] = 'required|array|min:1';
                $rules['recipient_cooperative_ids.*'] = 'exists:cooperatives,id';
                break;
        }

        // Admin dinas can send to any recipient
        // Admin koperasi can only send to their own cooperative or admin dinas
        if ($this->user()->hasRole('admin_koperasi')) {
            if ($this->input('recipient_type') === 'cooperative_based') {
                $rules['recipient_cooperative_ids.*'] = [
                    'exists:cooperatives,id',
                    Rule::in([$this->user()->cooperative_id])
                ];
            }
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul notifikasi wajib diisi.',
            'title.max' => 'Judul notifikasi maksimal 255 karakter.',
            'message.required' => 'Pesan notifikasi wajib diisi.',
            'message.max' => 'Pesan notifikasi maksimal 1000 karakter.',
            'type.required' => 'Jenis notifikasi wajib dipilih.',
            'type.in' => 'Jenis notifikasi tidak valid.',
            'priority.required' => 'Prioritas notifikasi wajib dipilih.',
            'priority.in' => 'Prioritas notifikasi tidak valid.',
            'recipient_type.required' => 'Jenis penerima wajib dipilih.',
            'recipient_type.in' => 'Jenis penerima tidak valid.',
            'scheduled_at.date' => 'Waktu penjadwalan harus berupa tanggal yang valid.',
            'scheduled_at.after' => 'Waktu penjadwalan harus di masa depan.',
            'expires_at.date' => 'Waktu kedaluwarsa harus berupa tanggal yang valid.',
            'expires_at.after' => 'Waktu kedaluwarsa harus setelah waktu penjadwalan.',
            'action_url.url' => 'URL aksi harus berupa URL yang valid.',
            'action_url.max' => 'URL aksi maksimal 500 karakter.',
            'action_text.max' => 'Teks aksi maksimal 100 karakter.',
            'recipient_user_ids.required' => 'Minimal harus memilih satu pengguna.',
            'recipient_user_ids.*.exists' => 'Pengguna tidak ditemukan.',
            'recipient_roles.required' => 'Minimal harus memilih satu role.',
            'recipient_roles.*.in' => 'Role tidak valid.',
            'recipient_cooperative_ids.required' => 'Minimal harus memilih satu koperasi.',
            'recipient_cooperative_ids.*.exists' => 'Koperasi tidak ditemukan.',
            'recipient_cooperative_ids.*.in' => 'Anda hanya dapat mengirim notifikasi ke koperasi Anda sendiri.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate notification scheduling
            $this->validateNotificationScheduling($validator);

            // Validate action requirements
            $this->validateActionRequirements($validator);

            // Validate metadata structure
            $this->validateMetadata($validator);

            // Validate recipient permissions
            $this->validateRecipientPermissions($validator);
        });
    }

    /**
     * Validate notification scheduling.
     */
    private function validateNotificationScheduling($validator): void
    {
        $scheduledAt = $this->input('scheduled_at');
        $expiresAt = $this->input('expires_at');
        $type = $this->input('type');

        // System maintenance notifications should be scheduled
        if ($type === 'system_maintenance' && !$scheduledAt) {
            $validator->errors()->add(
                'scheduled_at',
                'Notifikasi pemeliharaan sistem harus dijadwalkan.'
            );
        }

        // Urgent notifications should not be scheduled too far in advance
        if ($this->input('priority') === 'urgent' && $scheduledAt) {
            $scheduledTime = strtotime($scheduledAt);
            $maxAdvanceTime = strtotime('+24 hours');

            if ($scheduledTime > $maxAdvanceTime) {
                $validator->errors()->add(
                    'scheduled_at',
                    'Notifikasi urgent tidak boleh dijadwalkan lebih dari 24 jam ke depan.'
                );
            }
        }

        // Validate expiration time
        if ($expiresAt && $scheduledAt) {
            $scheduledTime = strtotime($scheduledAt);
            $expiresTime = strtotime($expiresAt);
            $minDuration = 3600; // 1 hour minimum

            if (($expiresTime - $scheduledTime) < $minDuration) {
                $validator->errors()->add(
                    'expires_at',
                    'Notifikasi harus aktif minimal 1 jam.'
                );
            }
        }
    }

    /**
     * Validate action requirements.
     */
    private function validateActionRequirements($validator): void
    {
        $actionUrl = $this->input('action_url');
        $actionText = $this->input('action_text');
        $type = $this->input('type');

        // If action URL is provided, action text should also be provided
        if ($actionUrl && !$actionText) {
            $validator->errors()->add(
                'action_text',
                'Teks aksi wajib diisi jika URL aksi disediakan.'
            );
        }

        // Report-related notifications should have action URL
        $reportTypes = ['report_submitted', 'report_approved', 'report_rejected'];
        if (in_array($type, $reportTypes) && !$actionUrl) {
            $validator->errors()->add(
                'action_url',
                'Notifikasi terkait laporan harus memiliki URL aksi.'
            );
        }
    }

    /**
     * Validate metadata structure.
     */
    private function validateMetadata($validator): void
    {
        $metadata = $this->input('metadata', []);
        $type = $this->input('type');

        // Validate metadata based on notification type
        switch ($type) {
            case 'report_submitted':
            case 'report_approved':
            case 'report_rejected':
                if (!isset($metadata['report_id'])) {
                    $validator->errors()->add(
                        'metadata.report_id',
                        'ID laporan wajib disertakan dalam metadata untuk notifikasi laporan.'
                    );
                }

                if (!isset($metadata['report_type'])) {
                    $validator->errors()->add(
                        'metadata.report_type',
                        'Jenis laporan wajib disertakan dalam metadata untuk notifikasi laporan.'
                    );
                }
                break;

            case 'system_maintenance':
                if (!isset($metadata['maintenance_start']) || !isset($metadata['maintenance_end'])) {
                    $validator->errors()->add(
                        'metadata',
                        'Waktu mulai dan selesai pemeliharaan wajib disertakan dalam metadata.'
                    );
                }
                break;
        }
    }

    /**
     * Validate recipient permissions.
     */
    private function validateRecipientPermissions($validator): void
    {
        $recipientType = $this->input('recipient_type');
        $user = $this->user();

        // Admin koperasi restrictions
        if ($user->hasRole('admin_koperasi')) {
            // Cannot send broadcast notifications
            if ($recipientType === 'broadcast') {
                $validator->errors()->add(
                    'recipient_type',
                    'Admin koperasi tidak dapat mengirim notifikasi broadcast.'
                );
            }

            // Cannot send to other cooperatives
            if ($recipientType === 'cooperative_based') {
                $cooperativeIds = $this->input('recipient_cooperative_ids', []);
                if (!in_array($user->cooperative_id, $cooperativeIds)) {
                    $validator->errors()->add(
                        'recipient_cooperative_ids',
                        'Admin koperasi hanya dapat mengirim notifikasi ke koperasi sendiri.'
                    );
                }
            }

            // Cannot send to specific users outside their cooperative
            if ($recipientType === 'specific_user') {
                $userIds = $this->input('recipient_user_ids', []);
                $invalidUsers = \App\Models\User::whereIn('id', $userIds)
                    ->where('cooperative_id', '!=', $user->cooperative_id)
                    ->whereDoesntHave('roles', function ($query) {
                        $query->where('name', 'admin_dinas');
                    })
                    ->exists();

                if ($invalidUsers) {
                    $validator->errors()->add(
                        'recipient_user_ids',
                        'Admin koperasi hanya dapat mengirim notifikasi ke anggota koperasi sendiri atau admin dinas.'
                    );
                }
            }
        }
    }

    /**
     * Get validated data with additional processing.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Set sender information
        $validated['sender_id'] = $this->user()->id;

        // Set default values
        if (!isset($validated['is_persistent'])) {
            $validated['is_persistent'] = false;
        }

        if (!isset($validated['requires_acknowledgment'])) {
            $validated['requires_acknowledgment'] = false;
        }

        // Set default scheduled_at to now if not provided
        if (!isset($validated['scheduled_at'])) {
            $validated['scheduled_at'] = now();
        }

        // Set default expires_at based on priority if not provided
        if (!isset($validated['expires_at'])) {
            $defaultExpiration = match ($validated['priority']) {
                'urgent' => now()->addDays(1),
                'high' => now()->addDays(3),
                'normal' => now()->addWeeks(1),
                'low' => now()->addWeeks(2),
                default => now()->addWeeks(1)
            };
            $validated['expires_at'] = $defaultExpiration;
        }

        return $validated;
    }

    /**
     * Get recipient user IDs based on recipient type.
     */
    public function getRecipientUserIds(): array
    {
        $recipientType = $this->input('recipient_type');

        switch ($recipientType) {
            case 'specific_user':
                return $this->input('recipient_user_ids', []);

            case 'role_based':
                $roles = $this->input('recipient_roles', []);
                return \App\Models\User::whereHas('roles', function ($query) use ($roles) {
                    $query->whereIn('name', $roles);
                })->pluck('id')->toArray();

            case 'cooperative_based':
                $cooperativeIds = $this->input('recipient_cooperative_ids', []);
                return \App\Models\User::whereIn('cooperative_id', $cooperativeIds)
                    ->pluck('id')->toArray();

            case 'broadcast':
                return \App\Models\User::where('is_active', true)
                    ->pluck('id')->toArray();

            default:
                return [];
        }
    }
}
