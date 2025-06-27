<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Savings extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'account_number',
        'type',
        'balance',
        'interest_rate',
        'status',
        'opened_date',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'opened_date' => 'date',
    ];

    /**
     * Get the member that owns the savings.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the transactions for the savings.
     */
    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class);
    }
}
