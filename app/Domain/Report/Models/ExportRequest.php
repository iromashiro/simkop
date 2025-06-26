<?php
// app/Domain/Report/Models/ExportRequest.php
namespace App\Domain\Report\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Export Request Model for tracking background exports
 */
class ExportRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'cooperative_id',
        'report_title',
        'format',
        'status',
        'estimated_size',
        'file_size',
        'file_path',
        'options',
        'filename',
        'error_message',
        'execution_time',
        'started_at',
        'completed_at',
        'failed_at',
        'expires_at',
    ];

    protected $casts = [
        'options' => 'array',
        'estimated_size' => 'integer',
        'file_size' => 'integer',
        'execution_time' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';

    protected static function booted(): void
    {
        static::creating(function ($exportRequest) {
            // Set expiration date (7 days from creation)
            $exportRequest->expires_at = now()->addDays(7);
        });

        static::deleting(function ($exportRequest) {
            // Clean up file when record is deleted
            if ($exportRequest->file_path && Storage::disk('local')->exists($exportRequest->file_path)) {
                Storage::disk('local')->delete($exportRequest->file_path);
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getDownloadUrl(): ?string
    {
        if (!$this->isCompleted() || !$this->file_path) {
            return null;
        }

        return route('exports.download', $this->id);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_EXPIRED => 'Expired',
            default => 'Unknown'
        };
    }

    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function getFormattedExecutionTime(): string
    {
        if (!$this->execution_time) {
            return 'Unknown';
        }

        if ($this->execution_time < 60) {
            return round($this->execution_time, 1) . ' seconds';
        }

        return round($this->execution_time / 60, 1) . ' minutes';
    }

    /**
     * Scope for user's exports
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope for cooperative's exports
     */
    public function scopeForCooperative($query, int $cooperativeId)
    {
        return $query->where('cooperative_id', $cooperativeId);
    }

    /**
     * Scope for active exports (not expired)
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
