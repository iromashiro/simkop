<?php
// app/Domain/Financial/Models/JournalEntry.php
namespace App\Domain\Financial\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Domain\User\Models\User;
use App\Traits\ValidatesDoubleEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Journal Entry model implementing strict double-entry accounting
 *
 * Ensures all financial transactions maintain the fundamental
 * accounting equation: Assets = Liabilities + Equity
 */
class JournalEntry extends TenantModel
{
    use HasFactory, SoftDeletes, LogsActivity, ValidatesDoubleEntry;

    // SECURITY FIX: Restricted fillable fields
    protected $fillable = [
        'fiscal_period_id',
        'transaction_date',
        'description',
        'reference',
    ];

    // SECURITY FIX: Guard sensitive fields from mass assignment
    protected $guarded = [
        'id',
        'cooperative_id',
        'entry_number',
        'total_debit',
        'total_credit',
        'is_balanced',
        'is_approved',
        'approved_by',
        'approved_at',
        'created_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Boot the model with enhanced security
     */
    protected static function booted(): void
    {
        parent::booted();

        // Generate entry number automatically
        static::creating(function ($entry) {
            if (!$entry->entry_number) {
                $entry->entry_number = $entry->generateEntryNumber();
            }

            // SECURITY: Auto-assign created_by
            if (!$entry->created_by && auth()->check()) {
                $entry->created_by = auth()->id();
            }
        });

        // Update totals when lines change
        static::saved(function ($entry) {
            $entry->updateTotals();
        });

        // SECURITY: Prevent modification of approved entries
        static::updating(function ($entry) {
            if ($entry->getOriginal('is_approved') && $entry->isDirty()) {
                $changedFields = array_keys($entry->getDirty());
                $allowedFields = ['description']; // Only description can be updated

                if (array_diff($changedFields, $allowedFields)) {
                    throw new \Exception('Cannot modify approved journal entries except description');
                }
            }
        });

        // SECURITY: Enhanced audit logging
        static::created(function ($model) {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($model)
                ->withProperties([
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'tenant_id' => app(\App\Infrastructure\Tenancy\TenantManager::class)->getCurrentTenantId(),
                ])
                ->log('Journal entry created');
        });
    }

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
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    /**
     * Get creator user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get approver user
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Generate sequential entry number
     */
    private function generateEntryNumber(): string
    {
        $fiscalYear = $this->fiscalPeriod->fiscal_year ?? date('Y');
        $prefix = "JE{$fiscalYear}";

        $lastEntry = static::where('cooperative_id', $this->cooperative_id)
            ->where('entry_number', 'like', "{$prefix}%")
            ->orderBy('entry_number', 'desc')
            ->first();

        if ($lastEntry) {
            $lastNumber = (int) substr($lastEntry->entry_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update total debit and credit amounts
     */
    public function updateTotals(): void
    {
        $totals = $this->lines()
            ->selectRaw('SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit')
            ->first();

        $this->updateQuietly([
            'total_debit' => $totals->total_debit ?? 0,
            'total_credit' => $totals->total_credit ?? 0,
        ]);
    }

    /**
     * Check if entry is balanced
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    /**
     * Approve the journal entry
     */
    public function approve(User $user): void
    {
        if ($this->is_approved) {
            throw new \Exception('Journal entry is already approved');
        }

        if (!$this->isBalanced()) {
            throw new \Exception('Cannot approve unbalanced journal entry');
        }

        $this->update([
            'is_approved' => true,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Scope for approved entries only
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope for entries in date range
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['entry_number', 'transaction_date', 'description', 'total_debit', 'total_credit', 'is_approved'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Journal entry {$eventName}")
            ->useLogName('financial_transactions')
            ->dontSubmitEmptyLogs();
    }
}
