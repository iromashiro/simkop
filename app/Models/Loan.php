<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'loan_number',
        'principal_amount',
        'interest_rate',
        'term_months',
        'monthly_payment',
        'outstanding_balance',
        'application_date',
        'approval_date',
        'disbursement_date',
        'purpose',
        'collateral_type',
        'collateral_value',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'collateral_value' => 'decimal:2',
        'application_date' => 'date',
        'approval_date' => 'date',
        'disbursement_date' => 'date',
        'term_months' => 'integer',
    ];

    /**
     * Get the member that owns the loan.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the payments for the loan.
     */
    public function payments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    /**
     * Get the user who approved the loan.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
