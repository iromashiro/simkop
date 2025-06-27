<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Laravel\Sanctum\HasApiTokens;
use App\Domain\Cooperative\Models\Cooperative;
use App\Domain\Member\Models\Member;

/**
 * User Model
 *
 * Manages system users with role-based access control
 * Supports multi-tenant cooperative access and member integration
 *
 * @package App\Domain\Auth\Models
 * @author Mateen (Senior Software Engineer)
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'phone_verified_at',
        'is_active',
        'last_login_at',
        'avatar_path',
        'settings',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
        'password' => 'hashed',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * User types for Indonesian cooperative system
     */
    public const TYPES = [
        'super_admin' => 'Super Administrator',
        'cooperative_admin' => 'Administrator Koperasi',
        'manager' => 'Manajer',
        'staff' => 'Staff',
        'member' => 'Anggota',
        'auditor' => 'Auditor',
    ];

    /**
     * Default user settings
     */
    public const DEFAULT_SETTINGS = [
        'language' => 'id',
        'timezone' => 'Asia/Jakarta',
        'date_format' => 'd/m/Y',
        'currency_format' => 'IDR',
        'notifications' => [
            'email' => true,
            'sms' => false,
            'push' => true,
        ],
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Set default settings when creating user
        static::creating(function ($user) {
            if (!$user->settings) {
                $user->settings = self::DEFAULT_SETTINGS;
            }
        });

        // Update last login when user logs in (without updating timestamps)
        static::updating(function ($user) {
            if ($user->isDirty('last_login_at')) {
                $user->timestamps = false;
            }
        });
    }

    /**
     * Get user cooperatives (many-to-many relationship)
     */
    public function cooperatives()
    {
        return $this->belongsToMany(Cooperative::class, 'cooperative_users')
            ->withPivot(['role', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Get user member profile (if user is a member)
     */
    public function member()
    {
        return $this->hasOne(Member::class);
    }

    /**
     * Get user's created records
     */
    public function createdRecords()
    {
        return $this->hasMany(static::class, 'created_by');
    }

    /**
     * Get primary cooperative (first active cooperative)
     */
    public function primaryCooperative(): ?Cooperative
    {
        return $this->cooperatives()
            ->wherePivot('is_active', true)
            ->orderBy('cooperative_users.joined_at')
            ->first();
    }

    /**
     * Check if user has access to specific cooperative
     */
    public function hasCooperativeAccess(int $cooperativeId): bool
    {
        // Super admin has access to all cooperatives
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Check if user is associated with the cooperative
        return $this->cooperatives()
            ->where('cooperatives.id', $cooperativeId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Get accessible cooperative IDs for current user
     */
    public function getAccessibleCooperativeIds(): array
    {
        // Super admin can access all cooperatives
        if ($this->hasRole('super_admin')) {
            return Cooperative::pluck('id')->toArray();
        }

        // Return user's associated cooperative IDs
        return $this->cooperatives()
            ->wherePivot('is_active', true)
            ->pluck('cooperatives.id')
            ->toArray();
    }

    /**
     * Check if user is a member
     */
    public function isMember(): bool
    {
        return $this->member()->exists();
    }

    /**
     * Check if user is cooperative admin
     */
    public function isCooperativeAdmin(int $cooperativeId = null): bool
    {
        // Super admin is always admin
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $query = $this->cooperatives()->wherePivot('role', 'admin');

        if ($cooperativeId) {
            $query->where('cooperatives.id', $cooperativeId);
        }

        return $query->exists();
    }

    /**
     * Get user's role in specific cooperative
     */
    public function getCooperativeRole(int $cooperativeId): ?string
    {
        $cooperative = $this->cooperatives()
            ->where('cooperatives.id', $cooperativeId)
            ->first();

        return $cooperative ? $cooperative->pivot->role : null;
    }

    /**
     * Join a cooperative with specific role
     */
    public function joinCooperative(int $cooperativeId, string $role = 'member'): void
    {
        $this->cooperatives()->syncWithoutDetaching([
            $cooperativeId => [
                'role' => $role,
                'is_active' => true,
                'joined_at' => now(),
            ]
        ]);
    }

    /**
     * Leave a cooperative
     */
    public function leaveCooperative(int $cooperativeId): void
    {
        $this->cooperatives()->updateExistingPivot($cooperativeId, [
            'is_active' => false,
            'left_at' => now(),
        ]);
    }

    /**
     * Update user's role in cooperative
     */
    public function updateCooperativeRole(int $cooperativeId, string $role): void
    {
        $this->cooperatives()->updateExistingPivot($cooperativeId, [
            'role' => $role,
        ]);
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for verified users
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope by role
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->role($role);
    }

    /**
     * Scope for cooperative users
     */
    public function scopeForCooperative($query, int $cooperativeId)
    {
        return $query->whereHas('cooperatives', function ($q) use ($cooperativeId) {
            $q->where('cooperatives.id', $cooperativeId)
                ->wherePivot('is_active', true);
        });
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active', 'last_login_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "User {$eventName}");
    }

    /**
     * Get user's display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get user's initials for avatar
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        $initials = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }

        return $initials ?: 'U';
    }

    /**
     * Get user's avatar URL
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return asset('storage/' . $this->avatar_path);
        }

        // Generate avatar using initials
        return "https://ui-avatars.com/api/?name=" . urlencode($this->initials) .
            "&color=7F9CF5&background=EBF4FF&size=128";
    }

    /**
     * Check if user is online (logged in within last 5 minutes)
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->last_login_at && $this->last_login_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->timestamps = false;
        $this->update(['last_login_at' => now()]);
        $this->timestamps = true;
    }

    /**
     * Get user setting value
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? self::DEFAULT_SETTINGS;
        return data_get($settings, $key, $default);
    }

    /**
     * Set user setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? self::DEFAULT_SETTINGS;
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Check if user can perform action on resource
     */
    public function canPerform(string $action, $resource = null): bool
    {
        // Super admin can do everything
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Check specific permissions
        return $this->can($action, $resource);
    }
}
