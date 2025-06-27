<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_id',
        'transaction_type',
        'amount',
        'balance_after',
        'transaction_date',
        'description',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /**
     * Get the savings that owns the transaction.
     */
    public function savings()
    {
        return $this->belongsTo(Savings::class);
    }

    /**
     * Get the user who processed the transaction.
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
