<?php
// app/Domain/Member/Models/Savings.php
namespace App\Domain\Member\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Savings model for tracking member savings transactions
 *
 * Supports 4 types of savings as per Indonesian cooperative standards:
 * - Pokok (Share Capital): Fixed amount paid once
 * - Wajib (Mandatory): Regular monthly contributions
 * - Khusus (Special): Special purpose savings
 * - Sukarela (Voluntary): Optional additional savings
 */
class Savings extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'member_id',
        'type',
        'transaction_date',
        'amount',
        'description',
        'reference',
        'created_by',
    ];

    // SECURITY: Guard balance_after from mass assignment
    protected $guarded = [
        'balance_after',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Boot the model with race condition protection
     */
    protected static function booted(): void
    {
        // SECURITY FIX: Calculate balance with database locking
        static::creating(function ($savings) {
            $savings->calculateBalanceAfterWithLocking();
        });

        // Validate business rules
        static::saving(function ($savings) {
            $savings->validateSavingsRules();
            $savings->validateFinancialAmounts();
        });
    }

    /**
     * SECURITY FIX: Calculate balance with database locking to prevent race conditions
     */
    private function calculateBalanceAfterWithLocking(): void
    {
        DB::transaction(function () {
            // Lock the member record to prevent concurrent balance calculations
            $this->member()->lockForUpdate()->first();

            $previousBalance = $this->getPreviousBalanceWithLocking();
            $this->balance_after = $previousBalance + $this->amount;

            // Additional validation after calculation
            if ($this->balance_after < 0) {
                throw new \Exception("Insufficient balance. Current: {$previousBalance}, Transaction: {$this->amount}");
            }
        });
    }

    /**
     * SECURITY: Get previous balance with proper locking
     */
    private function getPreviousBalanceWithLocking(): float
    {
        $lastSaving = static::where('member_id', $this->member_id)
            ->where('type', $this->type)
            ->where('transaction_date', '<=', $this->transaction_date)
            ->where('id', '!=', $this->id)
            ->lockForUpdate()
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastSaving ? (float) $lastSaving->balance_after : 0;
    }

    /**
     * SECURITY: Enhanced financial amount validation
     */
    private function validateFinancialAmounts(): void
    {
        // Validate decimal precision
        if (bccomp(round($this->amount, 2), $this->amount, 10) !== 0) {
            throw new \InvalidArgumentException('Amount cannot have more than 2 decimal places');
        }

        // Validate amount is not zero
        if ($this->amount == 0) {
            throw new \InvalidArgumentException('Transaction amount cannot be zero');
        }

        // Validate reasonable limits
        $maxAmount = 999999999.99;
        if (abs($this->amount) > $maxAmount) {
            throw new \InvalidArgumentException('Amount exceeds maximum allowed value');
        }
    }

    /**
     * Enhanced savings business rules validation
     */
    private function validateSavingsRules(): void
    {
        // Simpanan Pokok can only be deposited once
        if ($this->type === 'pokok' && $this->amount > 0) {
            $existingPokok = static::where('member_id', $this->member_id)
                ->where('type', 'pokok')
                ->where('amount', '>', 0)
                ->where('id', '!=', $this->id)
                ->exists();

            if ($existingPokok) {
                throw new \Exception('Simpanan Pokok can only be deposited once per member');
            }
        }

        // Validate withdrawal limits for mandatory savings
        if ($this->type === 'wajib' && $this->amount < 0) {
            $this->validateMandatorySavingsWithdrawal();
        }

        // SECURITY: Validate transaction date is not in the future
        if ($this->transaction_date > now()->toDateString()) {
            throw new \InvalidArgumentException('Transaction date cannot be in the future');
        }

        // SECURITY: Validate transaction date is not too old (configurable)
        $maxDaysBack = config('app.max_transaction_days_back', 365);
        if ($this->transaction_date < now()->subDays($maxDaysBack)->toDateString()) {
            throw new \InvalidArgumentException("Transaction date cannot be more than {$maxDaysBack} days in the past");
        }
    }

    /**
     * Validate mandatory savings withdrawal rules
     */
    private function validateMandatorySavingsWithdrawal(): void
    {
        // Business rule: Can only withdraw mandatory savings when leaving cooperative
        $member = $this->member;

        if ($member->status === 'active') {
            throw new \Exception('Mandatory savings can only be withdrawn when member leaves the cooperative');
        }

        // Must not have outstanding loans
        if ($member->getTotalLoanBalance() > 0) {
            throw new \Exception('Cannot withdraw mandatory savings while having outstanding loans');
        }
    }

    /**
     * SECURITY: Thread-safe deposit creation
     */
    public static function createDeposit(
        Member $member,
        string $type,
        float $amount,
        string $description = null,
        string $reference = null
    ): self {
        return DB::transaction(function () use ($member, $type, $amount, $description, $reference) {
            return static::create([
                'member_id' => $member->id,
                'type' => $type,
                'transaction_date' => now()->toDateString(),
                'amount' => abs($amount), // Ensure positive for deposits
                'description' => $description ?? "Deposit {$type} savings",
                'reference' => $reference,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * SECURITY: Thread-safe withdrawal creation
     */
    public static function createWithdrawal(
        Member $member,
        string $type,
        float $amount,
        string $description = null,
        string $reference = null
    ): self {
        return DB::transaction(function () use ($member, $type, $amount, $description, $reference) {
            return static::create([
                'member_id' => $member->id,
                'type' => $type,
                'transaction_date' => now()->toDateString(),
                'amount' => -abs($amount), // Ensure negative for withdrawals
                'description' => $description ?? "Withdrawal {$type} savings",
                'reference' => $reference,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Scope for deposits only
     */
    public function scopeDeposits($query)
    {
        return $query->where('amount', '>', 0);
    }

    /**
     * Scope for withdrawals only
     */
    public function scopeWithdrawals($query)
    {
        return $query->where('amount', '<', 0);
    }

    /**
     * Scope for specific savings type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'amount', 'balance_after', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get formatted transaction type
     */
    public function getTransactionTypeAttribute(): string
    {
        return $this->amount > 0 ? 'Deposit' : 'Withdrawal';
    }

    /**
     * Get savings type label
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
