<?php
// app/Domain/Financial/Models/Account.php
namespace App\Domain\Financial\Models;

use App\Infrastructure\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Chart of Accounts model for Indonesian cooperative accounting
 *
 * Implements hierarchical account structure following Indonesian
 * cooperative accounting standards with proper double-entry validation
 */
class Account extends TenantModel
{
    use HasFactory, SoftDeletes, LogsActivity;

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
        'opening_balance',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'opening_balance' => 'decimal:2',
        'level' => 'integer',
    ];

    /**
     * Account types for Indonesian cooperative accounting
     */
    public const TYPES = [
        'asset' => 'Aset',
        'liability' => 'Liabilitas',
        'equity' => 'Ekuitas',
        'revenue' => 'Pendapatan',
        'expense' => 'Beban',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Auto-calculate level based on parent
        static::creating(function ($account) {
            if ($account->parent_id) {
                $parent = static::find($account->parent_id);
                $account->level = $parent ? $parent->level + 1 : 1;
            }
        });

        // Prevent deletion of system accounts
        static::deleting(function ($account) {
            if ($account->is_system) {
                throw new \Exception('System accounts cannot be deleted');
            }

            // Check if account has children
            if ($account->children()->exists()) {
                throw new \Exception('Cannot delete account with child accounts');
            }

            // Check if account has transactions
            if ($account->journalLines()->exists()) {
                throw new \Exception('Cannot delete account with existing transactions');
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
     * Get journal lines for this account
     */
    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Get account balance as of specific date
     */
    public function getBalanceAsOf(string $date): float
    {
        $balance = $this->journalLines()
            ->whereHas('journalEntry', function ($query) use ($date) {
                $query->where('transaction_date', '<=', $date)
                    ->where('is_approved', true);
            })
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?? 0;

        return (float) $balance;
    }

    /**
     * Get current balance
     */
    public function getCurrentBalance(): float
    {
        return $this->getBalanceAsOf(now()->toDateString());
    }

    /**
     * Check if account is debit normal
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense']);
    }

    /**
     * Check if account is credit normal
     */
    public function isCreditNormal(): bool
    {
        return in_array($this->type, ['liability', 'equity', 'revenue']);
    }

    /**
     * Get account hierarchy path
     */
    public function getHierarchyPath(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Scope for root accounts (level 1)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('code');
    }

    /**
     * Scope for specific account type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for active accounts only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'type', 'parent_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get formatted account code with name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
