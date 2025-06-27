<?php

namespace App\Domain\Loan\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Domain\Member\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Loan Account Model
 *
 * Manages member loan accounts with Indonesian cooperative standards
 * Handles loan disbursement, payments, and interest calculations
 *
 * @package App\Domain\Loan\Models
 * @author Mateen (Senior Software Engineer)
 */
class LoanAccount extends TenantModel
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'cooperative_id',
        'member_id',
        'loan_number',
        'loan_type',
        'principal_amount',
        'interest_rate',
        'term_months',
        'monthly_payment',
        'outstanding_balance',
        'disbursement_date',
        'maturity_date',
        'status',
        'purpose',
        'collateral_description',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'disbursement_date' => 'date',
        'maturity_date' => 'date',
        'approved_at' => 'datetime',
        'term_months' => 'integer',
    ];

    /**
     * Loan types
     */
    public const LOAN_TYPES = [
        'regular' => 'Pinjaman Reguler',
        'emergency' => 'Pinjaman Darurat',
        'productive' => 'Pinjaman Produktif',
        'consumptive' => 'Pinjaman Konsumtif',
    ];

    /**
     * Loan statuses
     */
    public const STATUSES = [
        'pending' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'disbursed' => 'Dicairkan',
        'active' => 'Aktif',
        'paid_off' => 'Lunas',
        'overdue' => 'Menunggak',
        'written_off' => 'Dihapusbukukan',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Generate loan number automatically
        static::creating(function ($loan) {
            if (!$loan->loan_number) {
                $loan->loan_number = $loan->generateLoanNumber();
            }
        });
    }

    /**
     * Get loan member
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get loan payments
     */
    public function payments()
    {
        return $this->hasMany(LoanPayment::class)->orderBy('payment_date', 'desc');
    }

    /**
     * Get loan schedules
     */
    public function schedules()
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('due_date');
    }

    /**
     * Calculate monthly payment amount
     */
    public function calculateMonthlyPayment(): float
    {
        if ($this->term_months <= 0 || $this->interest_rate <= 0) {
            return 0;
        }

        $monthlyRate = $this->interest_rate / 100 / 12;
        $payment = $this->principal_amount *
            ($monthlyRate * pow(1 + $monthlyRate, $this->term_months)) /
            (pow(1 + $monthlyRate, $this->term_months) - 1);

        return round($payment, 2);
    }

    /**
     * Generate unique loan number
     */
    private function generateLoanNumber(): string
    {
        $year = $this->disbursement_date ? $this->disbursement_date->format('Y') : date('Y');
        $month = $this->disbursement_date ? $this->disbursement_date->format('m') : date('m');
        $prefix = "L{$year}{$month}";

        $lastLoan = static::where('cooperative_id', $this->cooperative_id)
            ->where('loan_number', 'like', "{$prefix}%")
            ->orderBy('loan_number', 'desc')
            ->first();

        if ($lastLoan) {
            $lastNumber = (int) substr($lastLoan->loan_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope for active loans
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['approved', 'disbursed', 'active']);
    }

    /**
     * Scope for overdue loans
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['loan_number', 'status', 'outstanding_balance'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
