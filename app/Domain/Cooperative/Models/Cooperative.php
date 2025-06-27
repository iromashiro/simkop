<?php

namespace App\Domain\Cooperative\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Domain\Member\Models\Member;
use App\Domain\User\Models\User;

/**
 * Cooperative Model
 *
 * Central entity for multi-tenant cooperative management
 * Handles cooperative registration, settings, and relationships
 *
 * @package App\Domain\Cooperative\Models
 * @author Mateen (Senior Software Engineer)
 */
class Cooperative extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'legal_entity_type',
        'registration_number',
        'registration_date',
        'address',
        'phone',
        'email',
        'website',
        'established_date',
        'business_field',
        'is_active',
        'settings',
        'logo_path',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'established_date' => 'date',
        'is_active' => 'boolean',
        'settings' => 'array',
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
     * Business field options
     */
    public const BUSINESS_FIELDS = [
        'simpan_pinjam' => 'Simpan Pinjam',
        'konsumen' => 'Konsumen',
        'produsen' => 'Produsen',
        'jasa' => 'Jasa',
        'serba_usaha' => 'Serba Usaha',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Generate cooperative code automatically
        static::creating(function ($cooperative) {
            if (!$cooperative->code) {
                $cooperative->code = $cooperative->generateCooperativeCode();
            }
        });
    }

    /**
     * Get cooperative members
     */
    public function members()
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get cooperative users
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get active members
     */
    public function activeMembers()
    {
        return $this->members()->where('status', 'active');
    }

    /**
     * Get cooperative statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_members' => $this->members()->count(),
            'active_members' => $this->activeMembers()->count(),
            'total_savings' => $this->members()->sum('total_savings'),
            'total_loans' => $this->members()->sum('total_loans'),
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
     * Scope for active cooperatives
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
            ->logOnly(['code', 'name', 'legal_entity_type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
