<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'payment_number',
        'payment_date',
        'amount_paid',
        'principal_amount',
        'interest_amount',
        'remaining_balance',
        'payment_method',
        'notes',
        'processed_by',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'payment_date' => 'date',
        'payment_number' => 'integer',
    ];

    /**
     * Get the loan that owns the payment.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user who processed the payment.
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
