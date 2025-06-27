<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class NonPerformingReceivable extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'member_name',
        'member_number',
        'reporting_year',
        'dana_sp_internal',
        'dana_ptba',
        'dana_map',
        'overdue_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dana_sp_internal' => 'decimal:2',
            'dana_ptba' => 'decimal:2',
            'dana_map' => 'decimal:2',
            'overdue_days' => 'integer',
        ];
    }

    // Scopes
    public function scopeByYear($query, int $year)
    {
        return $query->where('reporting_year', $year);
    }

    public function scopeByMember($query, string $memberName)
    {
        return $query->where('member_name', 'like', "%{$memberName}%");
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('member_name');
    }

    // Helper methods
    public function getTotalReceivable(): float
    {
        return $this->dana_sp_internal + $this->dana_ptba + $this->dana_map;
    }

    public function getMemberDisplayName(): string
    {
        return $this->member_number ? "{$this->member_name} ({$this->member_number})" : $this->member_name;
    }

    public function getRiskCategory(): string
    {
        if ($this->overdue_days <= 90) {
            return 'Kurang Lancar';
        } elseif ($this->overdue_days <= 180) {
            return 'Diragukan';
        } else {
            return 'Macet';
        }
    }

    public function getRiskCategoryClass(): string
    {
        if ($this->overdue_days <= 90) {
            return 'badge-warning';
        } elseif ($this->overdue_days <= 180) {
            return 'badge-danger';
        } else {
            return 'badge-dark';
        }
    }
}
