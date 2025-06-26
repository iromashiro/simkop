<?php
// app/Domain/Notification/Models/NotificationLog.php
namespace App\Domain\Notification\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends TenantModel
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'cooperative_id',
        'notification_id',
        'channel',
        'recipient',
        'status',
        'sent_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'provider_response',
        'cost',
        'retry_count',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'provider_response' => 'array',
        'cost' => 'decimal:4',
        'retry_count' => 'integer',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function markAsDelivered(): void
    {
        $this->update(['delivered_at' => now(), 'status' => 'delivered']);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'failed_at' => now(),
            'failure_reason' => $reason,
            'status' => 'failed',
        ]);
    }

    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }
}
