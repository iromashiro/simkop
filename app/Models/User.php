<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\HasAuditLog;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasAuditLog;

    protected $fillable = [
        'name',
        'email',
        'password',
        'cooperative_id',
        'last_login',
        'last_login_ip',
        'user_agent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function createdReports()
    {
        return $this->hasMany(FinancialReport::class, 'created_by');
    }

    public function approvedReports()
    {
        return $this->hasMany(FinancialReport::class, 'approved_by');
    }

    // Scopes
    public function scopeAdminDinas($query)
    {
        return $query->role('admin_dinas');
    }

    public function scopeAdminKoperasi($query)
    {
        return $query->role('admin_koperasi');
    }

    public function scopeByCooperative($query, $cooperativeId)
    {
        return $query->where('cooperative_id', $cooperativeId);
    }

    // Helper methods
    public function isAdminDinas(): bool
    {
        return $this->hasRole('admin_dinas');
    }

    public function isAdminKoperasi(): bool
    {
        return $this->hasRole('admin_koperasi');
    }

    public function canAccessCooperative($cooperativeId): bool
    {
        return $this->isAdminDinas() || $this->cooperative_id == $cooperativeId;
    }

    public function getUnreadNotificationsCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }

    public function updateLastLogin(): void
    {
        $this->update([
            'last_login' => now(),
            'last_login_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
