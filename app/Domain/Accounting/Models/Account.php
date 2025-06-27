<?php

namespace App\Domain\Accounting\Models;

use App\Infrastructure\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Account Model for Chart of Accounts
 *
 * Manages the chart of accounts for double-entry bookkeeping
 * Supports hierarchical account structure for Indonesian cooperative accounting
 *
 * @package App\Domain\Accounting\Models
 * @author Mateen (Senior Software Engineer)
 */
class Account extends TenantModel
{
    use SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'cooperative_id',
        'code',
        'name',
        'type',
        'parent_id',
        'level',
        'is_active',
        'is_system',
        'description',
        'normal_balance',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'level' => 'integer',
    ];

    /**
     * Account types for Indonesian cooperative accounting
     */
    public const TYPES = [
        'asset' => 'Aset',
        'liability' => 'Kewajiban',
        'equity' => 'Ekuitas',
        'revenue' => 'Pendapatan',
        'expense' => 'Beban',
    ];

    /**
     * Normal balance sides for each account type
     */
    public const NORMAL_BALANCES = [
        'debit' => 'Debit',
        'credit' => 'Kredit',
    ];

    /**
     * Account type to normal balance mapping
     */
    public const TYPE_NORMAL_BALANCE = [
        'asset' => 'debit',
        'expense' => 'debit',
        'liability' => 'credit',
        'equity' => 'credit',
        'revenue' => 'credit',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Set level and normal balance when creating
        static::creating(function ($account) {
            // Set level based on parent
            if ($account->parent_id) {
                $parent = static::find($account->parent_id);
                $account->level = $parent ? $parent->level + 1 : 1;
            } else {
                $account->level = 1;
            }

            // Set normal balance based on account type if not provided
            if (!$account->normal_balance && isset(self::TYPE_NORMAL_BALANCE[$account->type])) {
                $account->normal_balance = self::TYPE_NORMAL_BALANCE[$account->type];
            }
        });

        // Update children levels when parent changes
        static::updated(function ($account) {
            if ($account->wasChanged('level')) {
                $account->updateChildrenLevels();
            }
        });
    }

    /**
     * Get parent account
     */
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Get child accounts
     */
    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('code');
    }

    /**
     * Get all descendants recursively
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get journal entry lines for this account
     */
    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Calculate account balance as of specific date
     */
    public function getBalance(string $asOfDate = null): float
    {
        $asOfDate = $asOfDate ?: now()->format('Y-m-d');

        $lines = $this->journalEntryLines()
            ->whereHas('journalEntry', function ($query) use ($asOfDate) {
                $query->where('transaction_date', '<=', $asOfDate)
                    ->where('is_approved', true);
            })
            ->selectRaw('SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit')
            ->first();

        $debitTotal = (float) ($lines->total_debit ?? 0);
        $creditTotal = (float) ($lines->total_credit ?? 0);

        // Return balance based on normal balance side
        return $this->normal_balance === 'debit'
            ? $debitTotal - $creditTotal
            : $creditTotal - $debitTotal;
    }

    /**
     * Get account balance including children
     */
    public function getBalanceWithChildren(string $asOfDate = null): float
    {
        $balance = $this->getBalance($asOfDate);

        foreach ($this->children as $child) {
            $balance += $child->getBalanceWithChildren($asOfDate);
        }

        return $balance;
    }

    /**
     * Get account hierarchy path
     */
    public function getHierarchyPath(): string
    {
        $path = [$this->name];
        $ancestors = $this->ancestors();

        foreach ($ancestors as $ancestor) {
            array_unshift($path, $ancestor->name);
        }

        return implode(' > ', $path);
    }

    /**
     * Get account code path
     */
    public function getCodePath(): string
    {
        $path = [$this->code];
        $ancestors = $this->ancestors();

        foreach ($ancestors as $ancestor) {
            array_unshift($path, $ancestor->code);
        }

        return implode('.', $path);
    }

    /**
     * Update children levels recursively
     */
    private function updateChildrenLevels(): void
    {
        foreach ($this->children as $child) {
            $child->update(['level' => $this->level + 1]);
        }
    }

    /**
     * Check if account has transactions
     */
    public function hasTransactions(): bool
    {
        return $this->journalEntryLines()->exists();
    }

    /**
     * Check if account can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->hasTransactions() &&
            !$this->children()->exists() &&
            !$this->is_system;
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by account type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for root accounts (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for leaf accounts (no children)
     */
    public function scopeLeaf($query)
    {
        return $query->whereDoesntHave('children');
    }

    /**
     * Scope for system accounts
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope by level
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'type', 'is_active', 'parent_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Account {$eventName}");
    }

    /**
     * Get display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Get full display name with hierarchy
     */
    public function getFullDisplayNameAttribute(): string
    {
        return $this->getHierarchyPath();
    }

    /**
     * Check if account is debit type
     */
    public function isDebitType(): bool
    {
        return $this->normal_balance === 'debit';
    }

    /**
     * Check if account is credit type
     */
    public function isCreditType(): bool
    {
        return $this->normal_balance === 'credit';
    }
}
