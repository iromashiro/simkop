<?php
// app/Domain/Member/Models/Member.php
namespace App\Domain\Member\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Domain\Member\Models\Savings;
use App\Domain\Member\Models\Loan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Domain\Loan\Models\LoanAccount;
use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Auth\Models\User;
use App\Domain\Cooperative\Models\Cooperative;

/**
 * Member model for cooperative member management
 *
 * Handles member registration, savings tracking, and loan management
 * following Indonesian cooperative standards
 */
class Member extends TenantModel
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'cooperative_id',
        'member_number',
        'name',
        'id_number',
        'address',
        'phone',
        'email',
        'join_date',
        'status',
        'additional_info',
    ];

    protected $casts = [
        'join_date' => 'date',
        'additional_info' => 'array',
    ];

    /**
     * Member status options
     */
    public const STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Tidak Aktif',
        'suspended' => 'Ditangguhkan',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Generate member number automatically
        static::creating(function ($member) {
            if (!$member->member_number) {
                $member->member_number = $member->generateMemberNumber();
            }
        });
    }

    /**
     * Get member savings
     */
    public function savings()
    {
        return $this->hasMany(Savings::class)->orderBy('transaction_date', 'desc');
    }

    /**
     * Get member loans
     */
    public function loans()
    {
        return $this->hasMany(Loan::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get active loans
     */
    public function activeLoans()
    {
        return $this->loans()->whereIn('status', ['approved', 'disbursed', 'active']);
    }

    /**
     * Get savings by type
     */
    public function savingsByType(string $type)
    {
        return $this->savings()->where('type', $type);
    }

    /**
     * Get current savings balance by type
     */
    public function getSavingsBalance(string $type): float
    {
        $latestSaving = $this->savingsByType($type)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latestSaving ? (float) $latestSaving->balance_after : 0;
    }

    /**
     * Get total savings across all types
     */
    public function getTotalSavings(): float
    {
        $types = ['pokok', 'wajib', 'khusus', 'sukarela'];
        $total = 0;

        foreach ($types as $type) {
            $total += $this->getSavingsBalance($type);
        }

        return $total;
    }

    /**
     * Get total outstanding loan balance
     */
    public function getTotalLoanBalance(): float
    {
        return (float) $this->activeLoans()->sum('current_balance');
    }

    /**
     * Generate unique member number
     */
    private function generateMemberNumber(): string
    {
        $year = $this->join_date ? $this->join_date->format('Y') : date('Y');
        $prefix = "M{$year}";

        $lastMember = static::where('cooperative_id', $this->cooperative_id)
            ->where('member_number', 'like', "{$prefix}%")
            ->orderBy('member_number', 'desc')
            ->first();

        if ($lastMember) {
            $lastNumber = (int) substr($lastMember->member_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if member can take new loan
     */
    public function canTakeNewLoan(): bool
    {
        // Business rule: Maximum 2 active loans per member
        $activeLoanCount = $this->activeLoans()->count();

        return $activeLoanCount < 2 && $this->status === 'active';
    }

    /**
     * Get member's loan eligibility amount
     */
    public function getLoanEligibilityAmount(): float
    {
        // Business rule: Can borrow up to 3x total savings
        $totalSavings = $this->getTotalSavings();
        $currentLoanBalance = $this->getTotalLoanBalance();

        $maxLoanAmount = $totalSavings * 3;
        $availableAmount = $maxLoanAmount - $currentLoanBalance;

        return max(0, $availableAmount);
    }

    /**
     * Scope for active members
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for members joined in specific year
     */
    public function scopeJoinedInYear($query, int $year)
    {
        return $query->whereYear('join_date', $year);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['member_number', 'name', 'status', 'phone', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get member display name with number
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->member_number} - {$this->name}";
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Get member's user account
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get member loan accounts
     */
    public function loanAccounts()
    {
        return $this->hasMany(LoanAccount::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get member savings accounts
     */
    public function savingsAccounts()
    {
        return $this->hasMany(SavingsAccount::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get active loan accounts
     */
    public function activeLoanAccounts()
    {
        return $this->loanAccounts()->whereIn('status', ['approved', 'disbursed', 'active']);
    }

    /**
     * Get active savings accounts
     */
    public function activeSavingsAccounts()
    {
        return $this->savingsAccounts()->where('status', 'active');
    }

    /**
     * Get total savings balance
     */
    public function getTotalSavingsBalance(): float
    {
        return (float) $this->activeSavingsAccounts()->sum('balance');
    }

    /**
     * Get member's net worth (savings - loans)
     */
    public function getNetWorth(): float
    {
        return $this->getTotalSavingsBalance() - $this->getTotalLoanBalance();
    }

    /**
     * Check if member has outstanding loans
     */
    public function hasOutstandingLoans(): bool
    {
        return $this->getTotalLoanBalance() > 0;
    }

    /**
     * Check if member is eligible for loans
     */
    public function isEligibleForLoan(): bool
    {
        return $this->status === 'active' &&
            $this->getTotalSavingsBalance() >= $this->cooperative->getSetting('loan_settings.minimum_savings', 100000);
    }
}
