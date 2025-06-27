<?php

namespace App\Domain\Savings\Models;

use App\Infrastructure\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Savings Transaction Model
 *
 * Records all savings transactions (deposits, withdrawals, interest)
 *
 * @package App\Domain\Savings\Models
 * @author Mateen (Senior Software Engineer)
 */
class SavingsTransaction extends TenantModel
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'cooperative_id',
        'savings_account_id',
        'transaction_number',
        'transaction_date',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference_number',
        'processed_by',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Transaction types
     */
    public const TRANSACTION_TYPES = [
        'deposit' => 'Setoran',
        'withdrawal' => 'Penarikan',
        'interest' => 'Bunga',
        'penalty' => 'Denda',
        'transfer_in' => 'Transfer Masuk',
        'transfer_out' => 'Transfer Keluar',
        'correction' => 'Koreksi',
    ];

    /**
     * Get savings account
     */
    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    /**
     * Scope for deposits
     */
    public function scopeDeposits($query)
    {
        return $query->whereIn('transaction_type', ['deposit', 'interest', 'transfer_in']);
    }

    /**
     * Scope for withdrawals
     */
    public function scopeWithdrawals($query)
    {
        return $query->whereIn('transaction_type', ['withdrawal', 'penalty', 'transfer_out']);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['transaction_type', 'amount', 'balance_after'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
