<?php
// app/Domain/SHU/Models/ShuPlan.php
namespace App\Domain\SHU\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Domain\Financial\Models\FiscalPeriod;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * SECURITY HARDENED: SHU Distribution Plan Model with comprehensive validation
 */
class ShuPlan extends TenantModel
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'fiscal_period_id',
        'plan_name',
        'total_shu_amount',
        'savings_percentage',
        'transaction_percentage',
        'activity_percentage',
        'membership_percentage',
        'minimum_shu_amount',
        'maximum_shu_amount',
        'distribution_date',
        'notes',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $guarded = [
        'id',
        'cooperative_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'total_shu_amount' => 'decimal:2',
        'savings_percentage' => 'decimal:2',
        'transaction_percentage' => 'decimal:2',
        'activity_percentage' => 'decimal:2',
        'membership_percentage' => 'decimal:2',
        'minimum_shu_amount' => 'decimal:2',
        'maximum_shu_amount' => 'decimal:2',
        'distribution_date' => 'date',
        'approved_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_CALCULATING = 'calculating';
    const STATUS_CALCULATED = 'calculated';
    const STATUS_APPROVED = 'approved';
    const STATUS_DISTRIBUTED = 'distributed';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_CALCULATING => 'Calculating',
        self::STATUS_CALCULATED => 'Calculated',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_DISTRIBUTED => 'Distributed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * SECURITY FIX: Enhanced validation on model events
     */
    protected static function booted(): void
    {
        parent::booted();

        static::saving(function ($plan) {
            $plan->validateShuPlan();
        });

        static::updating(function ($plan) {
            $plan->validateStatusTransition();
        });

        static::deleting(function ($plan) {
            if ($plan->status === self::STATUS_DISTRIBUTED) {
                throw new \Exception('Cannot delete distributed SHU plan');
            }
        });
    }

    /**
     * SECURITY FIX: Comprehensive SHU plan validation
     */
    private function validateShuPlan(): void
    {
        // Enhanced percentage validation
        $totalPercentage = $this->savings_percentage +
            $this->transaction_percentage +
            $this->activity_percentage +
            $this->membership_percentage;

        if (abs($totalPercentage - 100) > 0.01) { // Allow for floating point precision
            throw new \InvalidArgumentException(
                "Total distribution percentages must equal 100%. Current total: {$totalPercentage}%"
            );
        }

        // Validate individual percentages
        $percentages = [
            'savings_percentage' => $this->savings_percentage,
            'transaction_percentage' => $this->transaction_percentage,
            'activity_percentage' => $this->activity_percentage,
            'membership_percentage' => $this->membership_percentage,
        ];

        foreach ($percentages as $field => $value) {
            if ($value < 0 || $value > 100) {
                throw new \InvalidArgumentException("{$field} must be between 0 and 100");
            }

            // Validate decimal precision
            if (bccomp(round($value, 2), $value, 10) !== 0) {
                throw new \InvalidArgumentException("{$field} cannot have more than 2 decimal places");
            }
        }

        // Validate SHU amounts
        if ($this->total_shu_amount <= 0) {
            throw new \InvalidArgumentException('Total SHU amount must be positive');
        }

        if ($this->minimum_shu_amount && $this->minimum_shu_amount < 0) {
            throw new \InvalidArgumentException('Minimum SHU amount cannot be negative');
        }

        if ($this->maximum_shu_amount && $this->maximum_shu_amount < $this->minimum_shu_amount) {
            throw new \InvalidArgumentException('Maximum SHU amount cannot be less than minimum');
        }

        // SECURITY: Validate reasonable limits
        if ($this->total_shu_amount > 999999999999.99) {
            throw new \InvalidArgumentException('Total SHU amount exceeds maximum allowed value');
        }

        // Validate decimal precision for amounts
        $amounts = [
            'total_shu_amount' => $this->total_shu_amount,
            'minimum_shu_amount' => $this->minimum_shu_amount,
            'maximum_shu_amount' => $this->maximum_shu_amount,
        ];

        foreach ($amounts as $field => $value) {
            if ($value && bccomp(round($value, 2), $value, 10) !== 0) {
                throw new \InvalidArgumentException("{$field} cannot have more than 2 decimal places");
            }
        }

        // Validate plan name
        if (empty(trim($this->plan_name))) {
            throw new \InvalidArgumentException('Plan name is required');
        }

        if (strlen($this->plan_name) > 255) {
            throw new \InvalidArgumentException('Plan name cannot exceed 255 characters');
        }

        // Validate distribution date
        if ($this->distribution_date && $this->distribution_date->lt(now()->toDateString())) {
            throw new \InvalidArgumentException('Distribution date cannot be in the past');
        }

        // Validate fiscal period exists and belongs to cooperative
        if ($this->fiscal_period_id) {
            $fiscalPeriod = FiscalPeriod::where('id', $this->fiscal_period_id)
                ->where('cooperative_id', $this->cooperative_id)
                ->first();

            if (!$fiscalPeriod) {
                throw new \InvalidArgumentException('Invalid fiscal period selected');
            }

            // Check if fiscal period is closed
            if ($fiscalPeriod->status !== 'closed') {
                throw new \InvalidArgumentException('SHU can only be planned for closed fiscal periods');
            }
        }

        // Validate status
        if (!in_array($this->status, array_keys(self::STATUSES))) {
            throw new \InvalidArgumentException('Invalid status');
        }
    }

    /**
     * Validate status transitions
     */
    private function validateStatusTransition(): void
    {
        if (!$this->isDirty('status')) {
            return;
        }

        $oldStatus = $this->getOriginal('status');
        $newStatus = $this->status;

        $allowedTransitions = [
            self::STATUS_DRAFT => [self::STATUS_CALCULATING, self::STATUS_CANCELLED],
            self::STATUS_CALCULATING => [self::STATUS_CALCULATED, self::STATUS_DRAFT],
            self::STATUS_CALCULATED => [self::STATUS_APPROVED, self::STATUS_DRAFT],
            self::STATUS_APPROVED => [self::STATUS_DISTRIBUTED, self::STATUS_CANCELLED],
            self::STATUS_DISTRIBUTED => [], // Final state
            self::STATUS_CANCELLED => [self::STATUS_DRAFT], // Can be reactivated
        ];

        if (
            !isset($allowedTransitions[$oldStatus]) ||
            !in_array($newStatus, $allowedTransitions[$oldStatus])
        ) {
            throw new \InvalidArgumentException(
                "Invalid status transition from {$oldStatus} to {$newStatus}"
            );
        }

        // Additional validation for specific transitions
        if ($newStatus === self::STATUS_APPROVED && !$this->approved_by) {
            $this->approved_by = auth()->id();
            $this->approved_at = now();
        }

        if ($newStatus === self::STATUS_DISTRIBUTED) {
            // Ensure all calculations exist
            $calculationCount = $this->memberCalculations()->count();
            if ($calculationCount === 0) {
                throw new \InvalidArgumentException('Cannot distribute SHU without member calculations');
            }
        }
    }

    // Relationships
    public function fiscalPeriod()
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function memberCalculations()
    {
        return $this->hasMany(ShuMemberCalculation::class);
    }

    // Helper methods
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDistributed(): bool
    {
        return $this->status === self::STATUS_DISTRIBUTED;
    }

    public function canBeModified(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CALCULATED]);
    }

    public function canBeCalculated(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_CALCULATED;
    }

    public function canBeDistributed(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    public function getTotalDistributedAttribute(): float
    {
        return (float) $this->memberCalculations()->sum('total_shu_amount');
    }

    public function getMemberCountAttribute(): int
    {
        return $this->memberCalculations()->count();
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'plan_name',
                'total_shu_amount',
                'savings_percentage',
                'transaction_percentage',
                'activity_percentage',
                'membership_percentage',
                'status',
                'distribution_date'
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "SHU plan {$eventName}")
            ->useLogName('shu_management')
            ->dontSubmitEmptyLogs();
    }
}
