<?php

namespace App\Domain\Cooperative\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Cooperative User Pivot Model
 *
 * Manages many-to-many relationship between cooperatives and users
 * Handles user roles and permissions within cooperatives
 *
 * @package App\Domain\Cooperative\Models
 * @author Mateen (Senior Software Engineer)
 */
class CooperativeUser extends Pivot
{
    use HasFactory, LogsActivity;

    /**
     * The table associated with the model.
     */
    protected $table = 'cooperative_users';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'cooperative_id',
        'user_id',
        'role',
        'is_active',
        'joined_at',
        'left_at',
        'notes',
        'permissions',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'permissions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User roles within cooperative
     */
    public const ROLES = [
        'admin' => 'Administrator',
        'manager' => 'Manajer',
        'staff' => 'Staff',
        'teller' => 'Teller',
        'auditor' => 'Auditor',
        'member' => 'Anggota',
    ];

    /**
     * Role permissions mapping
     */
    public const ROLE_PERMISSIONS = [
        'admin' => [
            'manage_users',
            'manage_members',
            'manage_loans',
            'manage_savings',
            'manage_accounting',
            'view_reports',
            'manage_settings',
        ],
        'manager' => [
            'manage_members',
            'manage_loans',
            'manage_savings',
            'view_reports',
        ],
        'staff' => [
            'view_members',
            'process_transactions',
            'view_basic_reports',
        ],
        'teller' => [
            'process_transactions',
            'view_member_accounts',
        ],
        'auditor' => [
            'view_all_data',
            'view_reports',
            'export_data',
        ],
        'member' => [
            'view_own_data',
        ],
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Set default permissions based on role
        static::creating(function ($cooperativeUser) {
            if (!$cooperativeUser->permissions && $cooperativeUser->role) {
                $cooperativeUser->permissions = self::ROLE_PERMISSIONS[$cooperativeUser->role] ?? [];
            }

            if (!$cooperativeUser->joined_at) {
                $cooperativeUser->joined_at = now();
            }
        });

        // Update permissions when role changes
        static::updating(function ($cooperativeUser) {
            if ($cooperativeUser->isDirty('role')) {
                $cooperativeUser->permissions = self::ROLE_PERMISSIONS[$cooperativeUser->role] ?? [];
            }
        });
    }

    /**
     * Get cooperative
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Get user
     */
    public function user()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class);
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Add permission to user
     */
    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];

        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    /**
     * Remove permission from user
     */
    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_diff($permissions, [$permission]);

        $this->update(['permissions' => array_values($permissions)]);
    }

    /**
     * Set user permissions
     */
    public function setPermissions(array $permissions): void
    {
        $this->update(['permissions' => $permissions]);
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /**
     * Check if membership is active
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->left_at;
    }

    /**
     * Activate membership
     */
    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'left_at' => null,
        ]);
    }

    /**
     * Deactivate membership
     */
    public function deactivate(string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'left_at' => now(),
            'notes' => $reason,
        ]);
    }

    /**
     * Scope for active memberships
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('left_at');
    }

    /**
     * Scope by role
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope for administrators
     */
    public function scopeAdministrators($query)
    {
        return $query->whereIn('role', ['admin', 'manager']);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['role', 'is_active', 'permissions'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Cooperative membership {$eventName}");
    }
}
