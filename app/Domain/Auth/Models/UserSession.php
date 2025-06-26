<?php
// app/Domain/Auth/Models/UserSession.php
namespace App\Domain\Auth\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends TenantModel
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'cooperative_id',
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'location',
        'is_active',
        'last_activity',
        'login_at',
        'logout_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'location' => 'array',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class);
    }

    public function markAsLoggedOut(): void
    {
        $this->update([
            'is_active' => false,
            'logout_at' => now(),
        ]);
    }

    public function updateActivity(): void
    {
        $this->update(['last_activity' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeExpired($query, int $timeoutMinutes = 120)
    {
        return $query->where('last_activity', '<', now()->subMinutes($timeoutMinutes));
    }
}
