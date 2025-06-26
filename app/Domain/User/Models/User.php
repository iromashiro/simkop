<?php
// app/Domain/User/Models/User.php
namespace App\Domain\User\Models;

use App\Domain\Cooperative\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * User model with multi-tenant role-based access control
 *
 * Supports both super admins (no cooperative_id) and
 * cooperative-specific users with tenant-aware permissions
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'cooperative_id',
        'is_active',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        // Log login activity
        static::updating(function ($user) {
            if ($user->isDirty('last_login_at')) {
                activity()
                    ->causedBy($user)
                    ->withProperties(['ip' => request()->ip()])
                    ->log('User logged in');
            }
        });
    }

    /**
     * Get the cooperative this user belongs to
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return is_null($this->cooperative_id) && $this->hasRole('Super Admin');
    }

    /**
     * Check if user belongs to specific cooperative
     */
    public function belongsToCooperative(int $cooperativeId): bool
    {
        return $this->cooperative_id === $cooperativeId;
    }

    /**
     * Get user's permissions for specific cooperative
     */
    public function getCooperativePermissions(int $cooperativeId): array
    {
        if ($this->isSuperAdmin()) {
            return ['*']; // All permissions
        }

        if (!$this->belongsToCooperative($cooperativeId)) {
            return [];
        }

        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Check if user can access cooperative
     */
    public function canAccessCooperative(int $cooperativeId): bool
    {
        return $this->isSuperAdmin() || $this->belongsToCooperative($cooperativeId);
    }

    /**
     * Scope query to specific cooperative
     */
    public function scopeForCooperative($query, int $cooperativeId)
    {
        return $query->where('cooperative_id', $cooperativeId);
    }

    /**
     * Scope query to super admins only
     */
    public function scopeSuperAdmins($query)
    {
        return $query->whereNull('cooperative_id');
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'cooperative_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get user's full name with cooperative context
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->name;

        if ($this->cooperative) {
            $name .= " ({$this->cooperative->name})";
        } elseif ($this->isSuperAdmin()) {
            $name .= " (Super Admin)";
        }

        return $name;
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
