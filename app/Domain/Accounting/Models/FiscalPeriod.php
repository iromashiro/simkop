<?php

namespace App\Domain\Accounting\Models;

use App\Infrastructure\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Fiscal Period Model
 *
 * Manages accounting periods for financial reporting
 *
 * @package App\Domain\Accounting\Models
 * @author Mateen (Senior Software Engineer)
 */
class FiscalPeriod extends TenantModel
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'cooperative_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'is_closed',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * Get journal entries for this period
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Scope for active period
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_closed', false);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_active', 'is_closed'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
