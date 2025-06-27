<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'account_id',
        'category',
        'description',
        'budget_amount',
        'actual_amount',
        'variance_amount',
        'variance_percentage',
        'notes',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
    ];

    /**
     * Get the budget that owns the item.
     */
    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Get the account for the item.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
