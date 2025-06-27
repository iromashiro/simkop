<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'fiscal_year',
        'name',
        'description',
        'total_revenue_budget',
        'total_expense_budget',
        'total_revenue_actual',
        'total_expense_actual',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'total_revenue_budget' => 'decimal:2',
        'total_expense_budget' => 'decimal:2',
        'total_revenue_actual' => 'decimal:2',
        'total_expense_actual' => 'decimal:2',
        'fiscal_year' => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the cooperative that owns the budget.
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Get the items for the budget.
     */
    public function items()
    {
        return $this->hasMany(BudgetItem::class);
    }

    /**
     * Get the user who approved the budget.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
