<?php

namespace App\Domain\Cooperative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Domain\Member\Models\Member;
use App\Domain\Auth\Models\User;

/**
 * Cooperative Model
 *
 * Central entity for multi-tenant cooperative management
 * Handles cooperative registration, settings, and member relationships
 *
 * @package App\Domain\Cooperative\Models
 * @author Mateen (Senior Software Engineer)
 */
class Cooperative extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'legal_entity_type',
        'registration_number',
        'registration_date',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'website',
        'established_date',
        'business_field',
        'chairman_name',
        'secretary_name',
        'treasurer_name',
        'is_active',
        'settings',
        'logo_path',
        'description',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'registration_date' => 'date',
        'established_date' => 'date',
        'is_active' => 'boolean',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Legal entity types for Indonesian cooperatives
     */
    public const LEGAL_ENTITY_TYPES = [
        'primer' => 'Koperasi Primer',
        'sekunder' => 'Koperasi Sekunder',
        'tersier' => 'Koperasi Tersier',
    ];

    /**
     * Business field options for Indonesian cooperatives
     */
    public const BUSINESS_FIELDS = [
        'simpan_pinjam' => 'Simpan Pinjam',
        'konsumen' => 'Konsumen',
        'produsen' => 'Produsen',
        'jasa' => 'Jasa',
        'serba_usaha' => 'Serba Usaha',
        'pemasaran' => 'Pemasaran',
        'kredit' => 'Kredit',
    ];

    /**
     * Default cooperative settings
     */
    public const DEFAULT_SETTINGS = [
        'currency' => 'IDR',
        'timezone' => 'Asia/Jakarta',
        'fiscal_year_start' => '01-01',
        'member_number_format' => 'AUTO',
        'loan_settings' => [
            'max_loan_amount' => 50000000,
            'max_loan_term' => 60,
            'default_interest_rate' => 12.0,
        ],
        'savings_settings' => [
            'minimum_balance' => 50000,
            'default_interest_rate' => 6.0,
        ],
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Generate cooperative code automatically if not provided
        static::creating(function ($cooperative) {
            if (!$cooperative->code) {
                $cooperative->code = $cooperative->generateCooperativeCode();
            }

            // Set default settings
            if (!$cooperative->settings) {
                $cooperative->settings = self::DEFAULT_SETTINGS;
            }
        });
    }

    /**
     * Get cooperative members
     */
    public function members()
    {
        return $this->hasMany(Member::class)->orderBy('member_number');
    }

    /**
     * Get cooperative users
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'cooperative_users')
            ->withPivot(['role', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Get active members
     */
    public function activeMembers()
    {
        return $this->members()->where('status', 'active');
    }

    /**
     * Get active users
     */
    public function activeUsers()
    {
        return $this->users()->wherePivot('is_active', true);
    }

    /**
     * Get cooperative administrators
     */
    public function administrators()
    {
        return $this->users()->wherePivot('role', 'admin')->wherePivot('is_active', true);
    }

    /**
     * Get cooperative loan accounts
     */
    public function loanAccounts()
    {
        return $this->hasMany(\App\Domain\Loan\Models\LoanAccount::class);
    }

    /**
     * Get cooperative savings accounts
     */
    public function savingsAccounts()
    {
        return $this->hasMany(\App\Domain\Savings\Models\SavingsAccount::class);
    }

    /**
     * Get cooperative accounts (chart of accounts)
     */
    public function accounts()
    {
        return $this->hasMany(\App\Domain\Accounting\Models\Account::class);
    }

    /**
     * Get cooperative fiscal periods
     */
    public function fiscalPeriods()
    {
        return $this->hasMany(\App\Domain\Accounting\Models\FiscalPeriod::class);
    }

    /**
     * Get cooperative statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_members' => $this->members()->count(),
            'active_members' => $this->activeMembers()->count(),
            'total_savings' => $this->savingsAccounts()->sum('balance'),
            'total_loans' => $this->loanAccounts()->sum('outstanding_balance'),
            'total_users' => $this->activeUsers()->count(),
        ];
    }

    /**
     * Get financial summary
     */
    public function getFinancialSummary(): array
    {
        $savingsTotal = $this->savingsAccounts()->where('status', 'active')->sum('balance');
        $loansTotal = $this->loanAccounts()->where('status', 'active')->sum('outstanding_balance');
        $overdueLoans = $this->loanAccounts()->where('status', 'overdue')->sum('outstanding_balance');

        return [
            'total_savings' => $savingsTotal,
            'total_loans' => $loansTotal,
            'overdue_loans' => $overdueLoans,
            'net_position' => $savingsTotal - $loansTotal,
        ];
    }

    /**
     * Generate unique cooperative code
     */
    private function generateCooperativeCode(): string
    {
        $year = $this->established_date ? $this->established_date->format('Y') : date('Y');
        $prefix = "KOP{$year}";

        $lastCooperative = static::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->first();

        if ($lastCooperative) {
            $lastNumber = (int) substr($lastCooperative->code, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get cooperative setting value
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? self::DEFAULT_SETTINGS;
        return data_get($settings, $key, $default);
    }

    /**
     * Set cooperative setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? self::DEFAULT_SETTINGS;
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Check if cooperative is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Activate cooperative
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate cooperative
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Scope for active cooperatives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by business field
     */
    public function scopeByBusinessField($query, string $businessField)
    {
        return $query->where('business_field', $businessField);
    }

    /**
     * Scope by legal entity type
     */
    public function scopeByLegalEntityType($query, string $legalEntityType)
    {
        return $query->where('legal_entity_type', $legalEntityType);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'legal_entity_type', 'is_active', 'business_field'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Cooperative {$eventName}");
    }

    /**
     * Get display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Get cooperative logo URL
     */
    public function getLogoUrlAttribute(): string
    {
        if ($this->logo_path) {
            return asset('storage/' . $this->logo_path);
        }

        // Generate default logo using cooperative initials
        $initials = collect(explode(' ', $this->name))
            ->map(fn($word) => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->implode('');

        return "https://ui-avatars.com/api/?name=" . urlencode($initials) .
            "&color=059669&background=D1FAE5&size=128";
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->province,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }
}
