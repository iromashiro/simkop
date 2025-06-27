<?php

namespace App\Domain\Loan\Models;

use App\Infrastructure\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Loan Schedule Model
 *
 * Manages loan payment schedules and tracks payment status
 *
 * @package App\Domain\Loan\Models
 * @author Mateen (Senior Software Engineer)
 */
class LoanSchedule extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'loan_account_id',
        'installment_number',
        'due_date',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'paid_amount',
        'balance_after',
        'status',
        'paid_date',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'installment_number' => 'integer',
    ];

    /**
     * Schedule statuses
     */
    public const STATUSES = [
        'pending' => 'Belum Jatuh Tempo',
        'due' => 'Jatuh Tempo',
        'paid' => 'Lunas',
        'overdue' => 'Menunggak',
    ];

    /**
     * Get loan account
     */
    public function loanAccount()
    {
        return $this->belongsTo(LoanAccount::class);
    }

    /**
     * Scope for overdue schedules
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', 'paid');
    }

    /**
     * Scope for due today
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today())
            ->where('status', '!=', 'paid');
    }
}
