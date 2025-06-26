<?php
// app/Domain/Analytics/Models/DashboardWidget.php
namespace App\Domain\Analytics\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends TenantModel
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'cooperative_id',
        'user_id',
        'widget_type',
        'title',
        'configuration',
        'position_x',
        'position_y',
        'width',
        'height',
        'is_active',
        'refresh_interval',
        'last_refreshed_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'refresh_interval' => 'integer',
        'last_refreshed_at' => 'datetime',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class);
    }

    public function markAsRefreshed(): void
    {
        $this->update(['last_refreshed_at' => now()]);
    }

    public function needsRefresh(): bool
    {
        if (!$this->last_refreshed_at || !$this->refresh_interval) {
            return true;
        }

        return $this->last_refreshed_at->addMinutes($this->refresh_interval)->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('widget_type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
