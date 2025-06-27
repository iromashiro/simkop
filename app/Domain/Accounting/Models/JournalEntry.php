<?php

namespace App\Domain\Accounting\Models;

use App\Infrastructure\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Journal Entry Model
 *
 * Double-entry bookkeeping journal entries
 *
 * @package App\Domain\Accounting\Models
 * @author Mateen (Senior Software Engineer)
 */
class JournalEntry extends TenantModel
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'cooperative_id',
        'fiscal_period_id',
        'reference_number',
        'transaction_date',
        'description',
        'total_debit',
        'total_credit',
        'is_approved',
        'approved_by',
        'approved_at',
        'source_type',
        'source_id',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Get fiscal period
     */
    public function fiscalPeriod()
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /**
     * Get journal lines
     */
    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference_number', 'total_debit', 'total_credit', 'is_approved'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
