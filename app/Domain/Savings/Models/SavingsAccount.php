<?php

namespace App\Domain\Savings\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Domain\Member\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Savings Account Model
 *
 * Manages member savings accounts with Indonesian cooperative standards
 * Handles different types of savings (pokok, wajib, khusus, sukarela)
 *
 * @package App\Domain\Savings\Models
 * @author Mateen (Senior Software Engineer)
 */
class SavingsAccount extends TenantModel
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'cooperative_id',
        'member_id',
        'account_number',
        'account_type',
        'balance',
        'minimum_balance',
        'interest_rate',
        'status',
        'opened_date',
        'closed_date',
        'notes',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'minimum_balance' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'opened_date' => 'date',
        'closed_date' => 'date',
    ];

    /**
     * Savings account types for Indonesian cooperatives
     */
    public const ACCOUNT_TYPES = [
        'pokok' => 'Simpanan Pokok',
        'wajib' => 'Simpanan Wajib',
        'khusus' => 'Simpanan Khusus',
        'sukarela' => 'Simpanan Sukarela',
    ];

    /**
     * Account statuses
     */
    public const STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Tidak Aktif',
        'closed' => 'Ditutup',
        'frozen' => 'Dibekukan',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Generate account number automatically
        static::creating(function ($account) {
            if (!$account->account_number) {
                $account->account_number = $account->generateAccountNumber();
            }
        });
    }

    /**
     * Get account member
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get savings transactions
     */
    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class)->orderBy('transaction_date', 'desc');
    }

    /**
     * Generate unique account number
     */
    private function generateAccountNumber(): string
    {
        $year = $this->opened_date ? $this->opened_date->format('Y') : date('Y');
        $typeCode = strtoupper(substr($this->account_type, 0, 1));
        $prefix = "S{$typeCode}{$year}";

        $lastAccount = static::where('cooperative_id', $this->cooperative_id)
            ->where('account_number', 'like', "{$prefix}%")
            ->orderBy('account_number', 'desc')
            ->first();

        if ($lastAccount) {
            $lastNumber = (int) substr($lastAccount->account_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope by account type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['account_number', 'balance', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
