<?php

namespace App\Domain\Loan\Models;

use App\Infrastructure\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Loan Payment Model
 *
 * Records loan payment transactions and tracks payment history
 *
 * @package App\Domain\Loan\Models
 * @author Mateen (Senior Software Engineer)
 */
class LoanPayment extends TenantModel
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'cooperative_id',
        'loan_account_id',
        'payment_number',
        'payment_date',
        'payment_amount',
        'principal_amount',
        'interest_amount',
        'penalty_amount',
        'balance_after',
        'payment_method',
        'reference_number',
        'notes',
        'processed_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Payment methods
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Tunai',
        'transfer' => 'Transfer Bank',
        'deduction' => 'Potong Simpanan',
        'other' => 'Lainnya',
    ];

    /**
     * Get loan account
     */
    public function loanAccount()
    {
        return $this->belongsTo(LoanAccount::class);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_amount', 'payment_date', 'payment_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
