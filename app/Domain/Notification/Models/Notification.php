<?php
// app/Domain/Notification/Models/Notification.php
namespace App\Domain\Notification\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends TenantModel
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'cooperative_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'channels',
        'notifiable_type',
        'notifiable_id',
        'read_at',
        'sent_at',
        'failed_at',
        'failure_reason',
        'priority',
        'scheduled_at',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'channels' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class);
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsSent(): void
    {
        $this->update(['sent_at' => now()]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('sent_at')->whereNull('failed_at');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }
}
